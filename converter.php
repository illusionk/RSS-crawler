<?php 

	/* based on Readability.php of Arc90 */
	header('Content-type: text/html; charset=utf-8'); 
	header('Vary: Accept-Language'); 
	require_once('JSLikeHTMLElement.php');

	class Reader{

		public $convertLinksToFootnotes = false;
		public $revertForcedParagraphElements = true;
		public $articleContent;
		public $dom;
		public $url = null; 
		protected $body = null; 
		private $success;
		public $img = array();
		public $embedd = array();
		public $tempContent;
		public $allNodeTagScore = array();


	/**
	* All of the regular expressions in use within readability.
	**/
		public $regexps = array(
			'unlikelyCandidates' => '/combx|comment|community|disqus|extra|foot|header|menu|remark|rss|shoutbox|sidebar|sponsor|ad-break|agegate|pagination|pager|popup|tweet|twitter/i',
			'okMaybeItsACandidate' => '/and|article|body|column|main|shadow/i',
			'positive' => '/article|body|content|entry|hentry|main|page|pagination|post|text|blog|story/i',
			'negative' => '/combx|comment|com-|contact|foot|footer|footnote|masthead|media|meta|outbrain|promo|related|scroll|shoutbox|sidebar|sponsor|shopping|tags|tool|widget/i',
			'divToPElements' => '/<(a|blockquote|dl|div|img|ol|p|pre|table|ul)/i',
			'replaceBrs' => '/(<br[^>]*>[ \n\r\t]*){2,}/i',
			'replaceFonts' => '/<(\/?)font[^>]*>/i',
			'normalize' => '/\s{2,}/',
			'killBreaks' => '/(<br\s*\/?>(\s|&nbsp;?)*){1,}/',
			'video' => '/http:\/\/(www\.)?(youtube|vimeo)\.com/i',
		);

		function __construct(){
		}

		function get_Curl($url) {
   	 		$ch = curl_init();
    		curl_setopt($ch, CURLOPT_URL, $url);
    		curl_setopt($ch, CURLOPT_HEADER, false);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    		$data = curl_exec($ch);

	    	if($data == false){
    	  		$error = curl_error($ch);
    			curl_close($ch);
    			return false;
    		}

	    	curl_close($ch);

    		return $data;
  		}

		function input($url=null){
			if(empty($url)) return false;
			$html = $this->get_curl($url);
			preg_match("/charset=([\w|\-]+);?/", $html, $match);
			$charset = isset($match[1]) ? $match[1] : 'UTF-8';

			$html = mb_convert_encoding($html, 'HTML-ENTITIES', $charset);

			$this->url = null;
			$this->body = null;
  
			$html = preg_replace($this->regexps['replaceBrs'], '</p><p>', $html);
			$html = preg_replace($this->regexps['replaceFonts'], '<$1span>', $html);
			$this->dom = new DOMDocument();
			$this->dom->preserveWhiteSpace = false;
			$this->dom->registerNodeClass('DOMElement', 'JSLikeHTMLElement');
			if (trim($html) == '') $html = '<html></html>';
			@$this->dom->loadHTML($html);
			$this->url = $url;
		}

		public function getContent() {
			return preg_replace('/\s+|\t+|\n+/i', ' ', trim(strip_tags($this->articleContent->innerHTML)));
		}

		public function init(){
			if (!isset($this->dom->documentElement)) return false;

			$this->removeScripts($this->dom);

			$this->success = true;

			$bodyElems = $this->dom->getElementsByTagName('body');
			if($bodyElems->length > 0) {
				if ($this->body == null) {
					$this->body = $bodyElems->item(0);
				}
			}

			if($this->body == null){
				$this->body = $this->dom->createElement('body');
				$this->dom->documentElement->appendChild($this->body);
			}
			$this->body->setAttribute('id', 'readerBody');

			$styleTags = $this->dom->getElementsByTagName('style');
			for($i = $styleTags->length-1; $i >= 0; $i--){
				$styleTags->item($i)->parentNode->removeChild($styleTags->item($i));
			}

			/* Build readability's DOM tree */
			$overlay        = $this->dom->createElement('div');
			$innerDiv       = $this->dom->createElement('div');
			$articleContent = $this->grabArticle();

			if (!$articleContent) {
				$this->success = false;
				$articleContent = $this->dom->createElement('div');
				$articleContent->setAttribute('id', 'reader-content');
				$articleContent->innerHTML = '<p>Sorry, Error occurs.</p>';
			}

			$overlay->setAttribute('id', 'readOverlay');
			$innerDiv->setAttribute('id', 'readInner');

			$innerDiv->appendChild($articleContent);
			$overlay->appendChild($innerDiv);

			$this->body->innerHTML = '';
			$this->body->appendChild($overlay);
			$this->body->removeAttribute('style');

			$this->articleContent = $articleContent;

			return $this->success;
		}

		function revertReadabilityStyledElements($articleContent) {
			$xpath = new DOMXPath($articleContent->ownerDocument);
			$elems = $xpath->query('.//p[@class="reader-styled"]', $articleContent);
			for ($i = $elems->length-1; $i >= 0; $i--) {
				$e = $elems->item($i);
				$e->parentNode->replaceChild($articleContent->ownerDocument->createTextNode($e->textContent), $e);
			}
		}

		function prepArticle($articleContent) {
			$this->cleanStyles($articleContent);
			$this->killBreaks($articleContent);
			if ($this->revertForcedParagraphElements) {
				$this->revertReadabilityStyledElements($articleContent);
			}

			/* Clean out junk from the article content */
			$this->cleanConditionally($articleContent, 'form');
			$this->clean($articleContent, 'object');
			$this->clean($articleContent, 'h1');

			if ($articleContent->getElementsByTagName('h2')->length == 1) {
				$this->clean($articleContent, 'h2');
			}
			$this->clean($articleContent, 'iframe');
			$this->cleanHeaders($articleContent);

			$this->cleanConditionally($articleContent, 'table');
			$this->cleanConditionally($articleContent, 'ul');
			$this->cleanConditionally($articleContent, 'div');

			$articleParagraphs = $articleContent->getElementsByTagName('p');
			for ($i = $articleParagraphs->length-1; $i >= 0; $i--){
				$imgCount    = $articleParagraphs->item($i)->getElementsByTagName('img')->length;
				$embedCount  = $articleParagraphs->item($i)->getElementsByTagName('embed')->length;
				$objectCount = $articleParagraphs->item($i)->getElementsByTagName('object')->length;

				if ($imgCount === 0 && $embedCount === 0 && $objectCount === 0 && $this->getInnerText($articleParagraphs->item($i), false) == ''){
					$articleParagraphs->item($i)->parentNode->removeChild($articleParagraphs->item($i));
				}
			}	

			try {
				$articleContent->innerHTML = preg_replace('/<br[^>]*>\s*<p/i', '<p', $articleContent->innerHTML);
			}
			catch (Exception $e) {
			}
		}

		protected function initializeNode($node) {
			$readability = $this->dom->createAttribute('reader');
			$readability->value = 0; 
			$node->setAttributeNode($readability);

			switch (strtoupper($node->tagName)) {
				case 'DIV':
					$readability->value += 5;
					break;

				case 'PRE':
				case 'TD':
				case 'BLOCKQUOTE':
					$readability->value += 3;
					break;

				case 'ADDRESS':
				case 'OL':
				case 'UL':
				case 'DL':
				case 'DD':
				case 'DT':
				case 'LI':
				case 'FORM':
					$readability->value -= 3;
					break;

				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
				case 'TH':
					$readability->value -= 5;
					break;
			}
			$readability->value += $this->getClassWeight($node);
		}

		protected function grabArticle($page=null) {
			$stripUnlikelyCandidates = true;
			if (!$page) $page = $this->dom;
			$allElements = $page->getElementsByTagName('*');
		
			$node = null;
			$nodesToScore = array();
			for ($nodeIndex = 0; ($node = $allElements->item($nodeIndex)); $nodeIndex++) {
				$tagName = strtoupper($node->tagName);

				if ($stripUnlikelyCandidates) {
					$unlikelyMatchString = $node->getAttribute('class') . $node->getAttribute('id');
					if (
						preg_match($this->regexps['unlikelyCandidates'], $unlikelyMatchString) &&
						!preg_match($this->regexps['okMaybeItsACandidate'], $unlikelyMatchString) &&
						$tagName != 'BODY'
					)
					{
						$node->parentNode->removeChild($node);
						$nodeIndex--;
						continue;
					}
				}

				if ($tagName == 'P' || $tagName == 'TD' || $tagName == 'PRE') {
					$nodesToScore[] = $node;
				}

				if ($tagName == 'DIV') {
					if (!preg_match($this->regexps['divToPElements'], $node->innerHTML)) {
				
						$newNode = $this->dom->createElement('p');
						try {
							$newNode->innerHTML = $node->innerHTML;
							$node->parentNode->replaceChild($newNode, $node);
							$nodeIndex--;
							$nodesToScore[] = $node;
						}
						catch(Exception $e) {
						}
					}
					else{
					
						for ($i = 0, $il = $node->childNodes->length; $i < $il; $i++) {
							$childNode = $node->childNodes->item($i);
							if ($childNode->nodeType == 3) {
								$p = $this->dom->createElement('p');
								$p->innerHTML = $childNode->nodeValue;
								$p->setAttribute('style', 'display: inline;');
								$p->setAttribute('class', 'reader-styled');
								$childNode->parentNode->replaceChild($p, $childNode);
							}
						}
					}
				}
			}

			$candidates = array();
			for ($pt=0; $pt < count($nodesToScore); $pt++) {
				$parentNode      = $nodesToScore[$pt]->parentNode;
			
				$grandParentNode = !$parentNode ? null : (($parentNode->parentNode instanceof DOMElement) ? $parentNode->parentNode : null);
				$innerText       = $this->getInnerText($nodesToScore[$pt]);

				if (!$parentNode || !isset($parentNode->tagName)) {
					continue;
				}
				if(strlen($innerText) < 25) {
					continue;
				}	
				if (!$parentNode->hasAttribute('reader')){
					$this->initializeNode($parentNode);
					$candidates[] = $parentNode;
				}
				if ($grandParentNode && !$grandParentNode->hasAttribute('reader') && isset($grandParentNode->tagName)){
					$this->initializeNode($grandParentNode);
					$candidates[] = $grandParentNode;
				}

				$contentScore = 1;
				$contentScore += count(explode(',', $innerText));
				$contentScore += min(floor(strlen($innerText) / 100), 3);
				$parentNode->getAttributeNode('reader')->value += $contentScore;

				if ($grandParentNode) {
					$grandParentNode->getAttributeNode('reader')->value += $contentScore/2;
				}
			}

			$topCandidate = null;
			for ($c=0, $cl=count($candidates); $c < $cl; $c++){
		
				$readability = $candidates[$c]->getAttributeNode('reader');
				$readability->value = $readability->value * (1-$this->getLinkDensity($candidates[$c]));
				$this->allNodeTagScore[$c] = array("tag" => $candidates[$c]->tagName,"score" => round($readability->value,3));
			
				if (!$topCandidate || $readability->value > (int)$topCandidate->getAttribute('reader')) {
					$topCandidate = $candidates[$c];
				}
			}

			$this->tempContent = $topCandidate;

		//	print htmlspecialchars($this->tempContent->innerHTML);

			if ($topCandidate === null || strtoupper($topCandidate->tagName) == 'BODY'){
				$topCandidate = $this->dom->createElement('div');
				if ($page instanceof DOMDocument) {
					if (!isset($page->documentElement)) {
					} else {
						$topCandidate->innerHTML = $page->documentElement->innerHTML;
						$page->documentElement->innerHTML = '';
						$page->documentElement->appendChild($topCandidate);
					}
				} else {
					$topCandidate->innerHTML = $page->innerHTML;
					$page->innerHTML = '';
					$page->appendChild($topCandidate);
				}
				$this->initializeNode($topCandidate);
			}

			$articleContent        = $this->dom->createElement('div');
			$articleContent->setAttribute('id', 'reader-content');
			$siblingScoreThreshold = max(10, ((int)$topCandidate->getAttribute('reader')) * 0.2);
			$siblingNodes          = $topCandidate->parentNode->childNodes;
			if (!isset($siblingNodes)) {
				$siblingNodes = new stdClass;
				$siblingNodes->length = 0;
			}

			for ($s=0, $sl=$siblingNodes->length; $s < $sl; $s++){
				$siblingNode = $siblingNodes->item($s);
				$append      = false;

				if ($siblingNode === $topCandidate){
					$append = true;
				}

				$contentBonus = 0;
			
				if ($siblingNode->nodeType === XML_ELEMENT_NODE && $siblingNode->getAttribute('class') == $topCandidate->getAttribute('class') && $topCandidate->getAttribute('class') != '') {
					$contentBonus += ((int)$topCandidate->getAttribute('reader')) * 0.2;
				}

				if ($siblingNode->nodeType === XML_ELEMENT_NODE && $siblingNode->hasAttribute('reader') && (((int)$siblingNode->getAttribute('reader')) + $contentBonus) >= $siblingScoreThreshold){
					$append = true;
				}

				if (strtoupper($siblingNode->nodeName) == 'P') {
					$linkDensity = $this->getLinkDensity($siblingNode);
					$nodeContent = $this->getInnerText($siblingNode);
					$nodeLength  = strlen($nodeContent);

					if ($nodeLength > 80 && $linkDensity < 0.25){
						$append = true;
					}
					else if ($nodeLength < 80 && $linkDensity === 0 && preg_match('/\.( |$)/', $nodeContent)){
						$append = true;
					}
				}

				if ($append){
					$nodeToAppend = null;
					$sibNodeName = strtoupper($siblingNode->nodeName);
					if ($sibNodeName != 'DIV' && $sibNodeName != 'P') {			
						$nodeToAppend = $this->dom->createElement('div');
						try {
							$nodeToAppend->setAttribute('id', $siblingNode->getAttribute('id'));
							$nodeToAppend->innerHTML = $siblingNode->innerHTML;
						}
						catch(Exception $e){
							$nodeToAppend = $siblingNode;
							$s--;
							$sl--;
						}
					} else {
						$nodeToAppend = $siblingNode;
						$s--;
						$sl--;
					}

					$nodeToAppend->removeAttribute('class');
					$articleContent->appendChild($nodeToAppend);
				}
			}

			//print  htmlspecialchars($articleContent->innerHTML);

			$this->getImage($articleContent);
			$this->getEmbed($articleContent);
			$this->prepArticle($articleContent);
		
			return $articleContent;
		}

		public function getEmbed($articleContent){
			$tempEmbed = $articleContent->getElementsByTagName('embed');
			$iframe = $articleContent->getElementsByTagName('iframe');
	
			if($tempEmbed->length == 0 && $iframe->length ==0){
				return null;
			}

			$emb = array();
			for($i=0 ; $i < $tempEmbed->length; $i++){
				$temp = $tempEmbed->item($i)->getAttribute('src');
				$emb[]=$temp;
			}
			for($i=0; $i < $iframe->length; $i++){
				$temp = $iframe->item($i)->getAttribute('src');
				$emb[]=$temp;
			}



			$this->embedd = $emb;

		}

		public function getTagName(){
			return $this->allNodeTagName;
		}

		public function reEmbed(){
			return $this->embedd;
		}

		public function getImage($articleContent){

			$tempImg = $articleContent->getElementsByTagName('img');

			if($tempImg->length == 0){
				return null;
			}
			if(strstr("udn",$this->url) == 0){
				$l=$tempImg->length/2;
			}
			else
				$l = $tempImg->length;
	
			$image=array();
			
			for($i=0;$i<$l;$i++){
				if(preg_match('/blog.yam.com/',$this->url)){
					$temp = $tempImg->item($i)->getAttribute('data-src');
				}
				else{
					$temp = $tempImg->item($i)->getAttribute('src');
					
				}
				if(preg_match("/http/",$temp)){
					$image[]=$temp;
				}
				else{
					$image[]=dirname($this->url).'/'.$temp;
				}
			}

			$this->img = $image;
		}

		public function reImg(){
			return $this->img;
		}

		public function removeScripts($doc) {
			$scripts = $doc->getElementsByTagName('script');
			for($i = $scripts->length-1; $i >= 0; $i--){
				$scripts->item($i)->parentNode->removeChild($scripts->item($i));
			}
		}

		public function getInnerText($e, $normalizeSpaces=true) {
			$textContent = '';

			if (!isset($e->textContent) || $e->textContent == '') {
				return '';
			}

			$textContent = trim($e->textContent);

			if ($normalizeSpaces) {
				return preg_replace($this->regexps['normalize'], ' ', $textContent);
			} else {
				return $textContent;
			}
		}

		public function getCharCount($e, $s=',') {
			return substr_count($this->getInnerText($e), $s);
		}

		public function cleanStyles($e) {
			if (!is_object($e)) return;
			$elems = $e->getElementsByTagName('*');
			foreach ($elems as $elem) {
				$elem->removeAttribute('style');
			}
		}

		public function getLinkDensity($e) {
			$links      = $e->getElementsByTagName('a');
			$textLength = strlen($this->getInnerText($e));
			$linkLength = 0;
			for ($i=0, $il=$links->length; $i < $il; $i++){
				$linkLength += strlen($this->getInnerText($links->item($i)));
			}
			if ($textLength > 0) {
				return $linkLength / $textLength;
			} else {
				return 0;
			}
		}

		public function getClassWeight($e) {

			$weight = 0;
			if ($e->hasAttribute('class') && $e->getAttribute('class') != ''){
				if (preg_match($this->regexps['negative'], $e->getAttribute('class'))) {
					$weight -= 25;
				}
				if (preg_match($this->regexps['positive'], $e->getAttribute('class'))) {
					$weight += 25;
				}
			}

			if ($e->hasAttribute('id') && $e->getAttribute('id') != ''){
				if (preg_match($this->regexps['negative'], $e->getAttribute('id'))) {
					$weight -= 25;
				}
				if (preg_match($this->regexps['positive'], $e->getAttribute('id'))) {
					$weight += 25;
				}
			}
			return $weight;
		}


		public function killBreaks($node) {
			$html = $node->innerHTML;
			$html = preg_replace($this->regexps['killBreaks'], '<br />', $html);
			$node->innerHTML = $html;
		}


		public function clean($e, $tag) {
			$targetList = $e->getElementsByTagName($tag);
			$isEmbed = ($tag == 'object' || $tag == 'embed');

			for ($y=$targetList->length-1; $y >= 0; $y--) {
			
				if ($isEmbed) {
					$attributeValues = '';
					for ($i=0, $il=$targetList->item($y)->attributes->length; $i < $il; $i++) {
						$attributeValues .= $targetList->item($y)->attributes->item($i)->value . '|'; 
					}
					if (preg_match($this->regexps['video'], $attributeValues)) {
						continue;
					}
					if (preg_match($this->regexps['video'], $targetList->item($y)->innerHTML)) {
						continue;
					}
				}
				$targetList->item($y)->parentNode->removeChild($targetList->item($y));
			}
		}

	
		public function cleanConditionally($e, $tag) {

			$tagsList = $e->getElementsByTagName($tag);
			$curTagsLength = $tagsList->length;

			for ($i=$curTagsLength-1; $i >= 0; $i--) {
				$weight = $this->getClassWeight($tagsList->item($i));
				$contentScore = ($tagsList->item($i)->hasAttribute('reader')) ? (int)$tagsList->item($i)->getAttribute('reader') : 0;
	
				if ($weight + $contentScore < 0) {
					$tagsList->item($i)->parentNode->removeChild($tagsList->item($i));
				}
				else if ( $this->getCharCount($tagsList->item($i), ',') < 10) {

					$p      = $tagsList->item($i)->getElementsByTagName('p')->length;
					$img    = $tagsList->item($i)->getElementsByTagName('img')->length;
					$li     = $tagsList->item($i)->getElementsByTagName('li')->length-100;
					$input  = $tagsList->item($i)->getElementsByTagName('input')->length;

					$embedCount = 0;
					$embeds = $tagsList->item($i)->getElementsByTagName('embed');
					for ($ei=0, $il=$embeds->length; $ei < $il; $ei++) {
						if (preg_match($this->regexps['video'], $embeds->item($ei)->getAttribute('src'))) {
							$embedCount++;
						}
					}	

					$linkDensity   = $this->getLinkDensity($tagsList->item($i));
					$contentLength = strlen($this->getInnerText($tagsList->item($i)));
					$toRemove      = false;

					if ( $img > $p ) {
						$toRemove = true;
					} else if ($li > $p && $tag != 'ul' && $tag != 'ol') {
						$toRemove = true;
					} else if ( $input > floor($p/3) ) {
						$toRemove = true;
					} else if ($contentLength < 25 && ($img === 0 || $img > 2) ) {
						$toRemove = true;
					} else if($weight < 25 && $linkDensity > 0.2) {
						$toRemove = true;
					} else if($weight >= 25 && $linkDensity > 0.5) {
						$toRemove = true;
					} else if(($embedCount == 1 && $contentLength < 75) || $embedCount > 1) {
						$toRemove = true;
					}

					if ($toRemove) {
						$tagsList->item($i)->parentNode->removeChild($tagsList->item($i));
					}
				}
			}
		}

		public function getScore(){
			return $this->allNodeTagScore;
		}

		public function cleanHeaders($e) {
			for ($headerIndex = 1; $headerIndex < 3; $headerIndex++) {
				$headers = $e->getElementsByTagName('h' . $headerIndex);
				for ($i=$headers->length-1; $i >=0; $i--) {
					if ($this->getClassWeight($headers->item($i)) < 0 || $this->getLinkDensity($headers->item($i)) > 0.33) {
						$headers->item($i)->parentNode->removeChild($headers->item($i));
					}
				}
			}
		}

		public function getOrigContent(){

			$tempInner = $this->articleContent->innerHTML;
			$tpa = $this->articleContent->getElementsByTagName('a');
			for($i=0;$i<$tpa->length;$i++){
				$tpasrc = $tpa->item($i)->getAttribute('src');
				if(preg_match("/http/",$tpasrc)){
				}
				else{
					$tpasrc=dirname($this->url).'/'.$tpasrc;
				}
				$tpa->item($i)->setAttribute('a',$tpasrc);
			}
			$this->cleanStyles($this->articleContent);

			return $this->articleContent->innerHTML;
		}

	}
?>





