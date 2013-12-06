<?php

header('Content-type: text/html; charset=utf-8'); 
header('Vary: Accept-Language'); 

require_once("db.php");
require_once("converter.php");

/* Connention test, should remove after publish */
//$test = new RSS_Crawler("http://news.google.com.tw/news?pz=1&cf=all&ned=tw&hl=zh-TW&output=rss");

//http://udn.com/udnrss/BREAKINGNEWS1.xml
//http://news.google.com.tw/news?pz=1&cf=all&ned=tw&hl=zh-TW&output=rss
//http://chinese.engadget.com/rss.xml

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
	private $tableName, $md5name;
	private $header;
	private $content;
	private $count;
	private $uncachedContent;
	private $mysql;

	public function __construct ($url) {
		global $count, $header, $content, $mysql;
		echo "\n-----------------------------------\n";
		echo "				Initiating...\n";
		echo "-----------------------------------\n";

		$feedURL = $url;

		if ($feedURL == NULL) {
			echo ">> Need an argument.\n Example: \$test = new RSS_Crawler(\"http://chinese.engadget.com/rss.xml\");";
		} else {
			$mysql = new c2mysql();
			$tableName = "";
			$count = 0;
			$header = new stdClass();
			$content[] = new stdClass();
			$uncachedContent[] = new stdClass();
			$this->capture($feedURL);
		}
	}

	public function __destruct() {
		global $link;
		mysqli_close($link);
		echo "Destoryed\n";
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
		global $count, $header, $content, $tableName;
		$ch = curl_init();

		$options = array(
			CURLOPT_URL=>$URL,
			CURLOPT_HEADER=>0,
			CURLOPT_VERBOSE=>0,
			CURLOPT_RETURNTRANSFER=>true,
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
		$tableName = md5($rss->channel->link);

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

		$this->savePage($rss);
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
		global $uncachedContent, $tableName, $content, $mysql;

		echo "\n>> Saving page...\n";
		echo "Compare to exist file\n";
		/* check site cache exist? */
		echo "Source: ".$tableName."\n";
		if ($mysql->checkTableExist($tableName) == false) {
			echo "Source not exist, create table.\n";
			$mysql->createContentTable($tableName);
			echo "Created!\n\n";

			echo "INSERT data...\n";
			for($i=count($content)-1; $i>=0; $i--) {
				$doc = new Reader();
				$doc->input($content[$i]->link);
				$doc->init();
				$strip_content = strip_tags($content[$i]->description);

				$img_arr = array();
				$img_arr = $doc->reImg();
				if(count($img_arr) == 0)
					$img = "";
				else 
					$img = $img_arr[0];

				$mysql->insertContent($tableName, $content[$i]->title, $content[$i]->link, $content[$i]->description, $content[$i]->author, $content[$i]->category, $content[$i]->comments, $content[$i]->enclosure, $content[$i]->guid, $content[$i]->pubDate, $content[$i]->source, $strip_content, $img);
			}
			echo "[EXIT]INSERT COMPELETE!\n\n";
		} else {
			echo "TABLE exists, getting uncached content...\n";
			$uncachedContent = $this->uncachedContent($rss);

			if($uncachedContent != NULL)
				$this->saveUncachedContent();
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
		global $uncachedContent, $tableName, $mysql;
		echo "\n>> Saving uncache contents\n";

		echo "INSERT data...\n";
		for($i=count($uncachedContent)-1; $i>=0; $i--) {
			$doc = new Reader();
			$doc->input($uncachedContent[$i]->link);
			$doc->init();
			$strip_content = strip_tags($uncachedContent[$i]->description);
			//$doc->getContent()

			$img_arr = array();
			$img_arr = $doc->reImg();

			if(count($img_arr) == 0)
				$img = "";
			else 
				$img = $img_arr[0];

			$mysql->insertContent($tableName, $uncachedContent[$i]->title, $uncachedContent[$i]->link, $uncachedContent[$i]->description, $uncachedContent[$i]->author, $uncachedContent[$i]->category, $uncachedContent[$i]->comments, $uncachedContent[$i]->enclosure, $uncachedContent[$i]->guid, $uncachedContent[$i]->pubDate, $uncachedContent[$i]->source, $strip_content, $img);
		}
		echo "INSERT COMPELETE!\n";
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
	private function isUpdated($rss) {
		global $tableName, $mysql;

		$oldMax = $mysql->getOldMaxLink($tableName);

		echo "\n\n Table: ".$tableName.", ".$rss->channel->item[0]->link;

		if ($oldMax != $rss->channel->item[0]->link) {
			return false;
		} else {
			return true;
		}
	}

	/**
	*	Check last update time to decide which content is not cached, and return.
	*
	*	Post:	Return an object array of RSS content which hasn't been cached. 
	*			Return NULL if nothing new.
	*/
	private function uncachedContent($rss) {
		global $count, $mysql, $tableName;
		$uncachedContent[] = new stdClass();
		echo ">> Find uncached content...\n";
		echo "Checking version...\n";
		if($this->isUpdated($rss) == true) {
			echo "[EXIT] All cached, cya!\n\n";
			return NULL;
		} else {
			echo "Seems new articles is published, Let's catch'em!\n";
			$oldMax = $mysql->getOldMaxLink($tableName);
			for ($i = 0; $i < $count; $i++) {
				if ($oldMax != $rss->channel->item[$i]->link) {
					$uncachedContent[$i] = clone $rss->channel->item[$i];
				} else {
					echo "New article has been picked!\n";
					return $uncachedContent;
				}
			}
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

	public function getSourceTitle() {
		global $header;
		return $header->title;
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