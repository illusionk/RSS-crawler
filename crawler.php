<?php

require_once("db.php");

echo "Initiating...\n";

/* Connention test, should remove after publish */
$test = new RSS_Crawler("http://chinese.engadget.com/rss.xml");
$mysql = new c2mysql();
//http://udn.com/udnrss/BREAKINGNEWS1.xml
//http://news.google.com.tw/news?pz=1&cf=all&ned=tw&hl=zh-TW&output=rss
//http://chinese.engadget.com/rss.xml

$content = $test->getUncachedContent();
$count = $test->getUncachedContentCount();

for($i=0 ; $i<$count; $i++) {
	$mysql->insertContent($test->getMd5Name(), mysql_real_escape_string($content[$i]->title), $content[$i]->link, mysql_real_escape_string($content[$i]->description), $content[$i]->author, $content[$i]->category, $content[$i]->comments, $content[$i]->enclosure, $content[$i]->guid, $content[$i]->pubDate, $content[$i]->source);
}

/* RSS FEED INFORMATION
echo "-- RSS information --\n";
echo "Title: ".$test->getHeader()->title."\n";
echo "Link: ".$test->getHeader()->link."\n";
echo "Description: ".$test->getHeader()->description."\n";
// Optional
if ($test->getHeader()->ttl != NULL)
	echo "TTL: ".$test->getHeader()->ttl." min\n";
if ($test->getHeader()->pubDate != NULL)
	echo "Last updated: ".$test->getHeader()->pubDate."\n";
// RSS CONTENT INFORMATION
echo "-- RSS content --\n";
echo "Amount: ".$test->getContentCount()."\n";
echo "New Articles: ";
if ($test->getUncachedContent() == NULL) {
	echo "Nothing new.\n";
} else {
	print_r($test->getUncachedContentCount());
}
/* Connection test end, should remove above after release */

class RSS_Crawler {
	/* cURL option */
	private $feedURL = "";
	private $userAgent = "RSS crawler";

	/* content */
	private $filename, $md5name;
	private $header;
	private $content;
	private $count;
	private $uncachedContent;

	public function __construct ($url) {
		global $count, $header, $content;
		$feedURL = $url;
		if ($feedURL == NULL) {
			echo ">> Need an argument.\n Example: \$test = new RSS_Crawler(\"http://chinese.engadget.com/rss.xml\");";
		} else {
			$filename = "";
			$count = 0;
			$header = new stdClass();
			$content[] = new stdClass();
			$uncachedContent[] = new stdClass();
			$this->capture($feedURL);
		}
	}

	/**
	*	Capture RSS feed content.
	*
	*	@param String URL of RSS feed
	*
	*	Postcondition: check local cache is newest?
	*/
	private function capture($URL) {
		/* initialate */
		global $count, $header, $content, $filename;
		$ch = curl_init();

		$options = array(
			CURLOPT_URL=>$URL,
			CURLOPT_HEADER=>0,
			CURLOPT_VERBOSE=>0,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_USERAGENT=>$userAgent,
			);
		curl_setopt_array($ch, $options);

		echo ">> Captureing...\n"."target: ".$URL."\n";

		/* Capture content */
		$result = curl_exec($ch);

		/* Close connection */ 
		curl_close($ch);
		echo ">> Captured!\n";

		/* Convert XML to object */
		$rss = simplexml_load_string($result);

		// Get filename
		$GLOBALS['md5name'] = md5($rss->channel->link);
		$filename = md5($rss->channel->link).".json";

		// Get content count.
		$count = count($rss->channel->item);

		/* Save Source information */
		$header->title = $rss->channel->title;
		$header->link = $rss->channel->link;
		$header->description = $rss->channel->description;
		// Optional
		$header->pubDate = $rss->channel->pubDate;
		$header->ttl = $rss->channel->ttl;

		/* Save Content */
		for($i = 0; $i < $count; $i++) {
			$content[$i] = clone $rss->channel->item[$i];
		}

		/* Check local cache exist? */
		$this->savePage($rss);
		$this->saveUncachedContent();
	}

	/**
	*	check the file of RSS feed exist and store page.
	*
	*	@param object transform from RSS XML
	*
	*	Postcondition:
	*		Not exist: Store current RSS content.
	*		Exist: Compare local file and RSS content if pubDate are same?
	*/
	private function savePage($rss) {
		global $uncachedContent, $filename;

		echo "\n>> Saving page...\n";
		echo "Compare to exist file\n";
		/* check site cache exist? */
		echo "Filename: ".$filename."\n";
		if (file_exists($filename) == false) {
			echo "File not exist, saving cache.\n";
			$fp = fopen($filename, "w");
			fwrite($fp, json_encode($rss->channel));
			fclose($fp);
			echo "Saved!\n\n";
			$uncachedContent = $this->uncachedContent(NULL, $rss);
		} else {
			$json = json_decode(file_get_contents($filename), false);
			if ($this->isUpdated($json, $rss) == false) {
				$fp = fopen($filename, "w");
				fwrite($fp, json_encode($rss->channel));
				fclose($fp);
				echo "Local file has up to-date!\n\n";

				// Get uncached content.
				$uncachedContent = $this->uncachedContent($json, $rss);
			}
		}
	}

	/**
	*	Check content exist? Write uncache content to the begining of content file.
	*
	*	Postcondition:
	*		Not exist: Save current uncached RSS content.
	*		Exist: Write to the begining of the file.
	*/
	private function saveUncachedContent() {
		global $uncachedContent, $filename;
		echo "\n>> Saving uncache contents\n";
		if (file_exists("_content_".$filename) == true) {
			echo "Content file exists.\n";
			if ($uncachedContent == NULL) {
				echo "Local file is new.\n\n";
				return;
			}
			echo "Saving...\n";
			$data0 = json_decode(json_encode($uncachedContent));
			$data1 = json_decode(file_get_contents("_content_".$filename), false);
			$array = array_merge($data0, $data1);
			file_put_contents("_content_".$filename, json_encode($array));
			echo "Saved.\n\n";
		} else {
			echo "Content file NOT exist.\n";
			if ($uncachedContent == NULL) {
				echo "Local file is new.\n\n";
				return;
			}
			echo "Saving...\n";
			file_put_contents("_content_".$filename, json_encode($uncachedContent));
			echo "Saved.\n\n";
		}
	}

	/**
	*	Compare local and RSS feed newest pubDate
	*
	*	@param ObjectArray local cache file after json decode.
	*	@param ObjectArray newest RSS feed content.
	*
	*	Postcondition: 
	*		Same: return true. local and RSS feed are consistent.
	*		Different: return false. RSS feed has new content.
	*/
	private function isUpdated($local, $rss) {
		echo "Local: ".$local->item[0]->pubDate." // Newest: ".$rss->channel->item[0]->pubDate."\n";
		if ($local->item[0]->pubDate != $rss->channel->item[0]->pubDate) {
			echo "Local file is old\n";
			return false;
		} else {
			echo "Local file is new!\n";
			return true;
		}
	}

	/**
	*	Check last update time to decide which content is not cached, and return.
	*
	*	Post:	Return an object array of RSS content which hasn't been cached. 
	*			Return NULL if nothing new.
	*/
	private function uncachedContent($local, $rss) {
		global $count;
		$uncachedContent[] = new stdClass();
		echo ">> Find uncached content...\n";
		if($this->isUpdated($local, $rss) == true) {
			return NULL;
		} else {
			for ($i = 0; $i < $count; $i++) {
				if ($local->item[$i]->pubDate != $rss->channel->item[$i]->pubDate) {
					$uncachedContent[$i] = clone $rss->channel->item[$i];
				} else {
					break;
				}
			}
			echo "Compare complete!\n";
			return $uncachedContent;
		}
	}

	/**
	*	Get amount of RSS content
	*
	*	Post:	Return an interger of amount of RSS content
	*/
	public function getContentCount() {
		global $count;
		return $count;
	}

	/**
	*	Get RSS feed information
	*
	*	Post:	Return an object array of RSS feed information
	*/
	public function getHeader() {
		global $header;
		return $header;
	}

	/**
	*	Get RSS content
	*
	*	Post:	Return an object array of RSS content
	*/
	public function getContent() {
		global $content;
		return $content;
	}

	public function getMd5Name() {
		return $GLOBALS['md5name'];
	}

	/**
	*	Get uncached RSS content
	*
	*	Post:	Return an object array of RSS content which hasn't been cached. return NULL if nothing new.
	*/
	public function getUncachedContent() {
		global $uncachedContent;
		return $uncachedContent;
	}

	public function getUncachedContentCount() {
		global $uncachedContent;
		return count($uncachedContent);
	}
}
?>