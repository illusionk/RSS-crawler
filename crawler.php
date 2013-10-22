<?php

echo "Initiating...\n";

/* Connention test, should remove after publish */
$test = new RSS_Crawler("http://udn.com/udnrss/BREAKINGNEWS1.xml");
print_r($test->getContentCount());

class RSS_Crawler {
	/* cURL option */
	private $feedURL = "";
	private $userAgent = "RSS crawler";

	/* content */
	private $header;
	private $content;
	private $count;

	public function __construct ($url) {
		$feedURL = $url;
		if ($feedURL == NULL) {
			echo ">> Need an argument.\n Example: \$test = new RSS_Crawler(\"http://chinese.engadget.com/rss.xml\");";
		} else {
			$count = 0;
			$header = new stdClass();
			$content[] = new stdClass();
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
		global $count, $header, $content;
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

		$count = count($rss->channel->item);

		/* Save Source information */
		$header->title = $rss->channel->title;
		$header->link = $rss->channel->link;
		$header->description = $rss->channel->description;
		$header->pubDate = $rss->channel->pubDate;

		/* Save Content */
		for($i = 0; $i < $count; $i++) {
			$content[$i] = clone $rss->channel->item[$i];
			//echo($item[$i]->guid);
		}

		/* Check local cache exist? */
		$this->checkExist($rss);
	}

	/**
	*	check the file of RSS feed exist?
	*
	*	@param object transform from RSS XML
	*
	*	Postcondition:
	*		Not exist: Store current RSS content.
	*		Exist: Compare local file and RSS content if pubDate are same?
	*/
	private function checkExist($rss) {
		echo "\n>> Compare to exist file\n";
		/* check site cache exist? */
		$filename = md5($rss->channel->link).".json";
		echo "Filename: ".$filename."\n";
		if (file_exists($filename) == false) {
			echo "File not exist, saving cache.\n";
			$fp = fopen($filename, "w");
			fwrite($fp, json_encode($rss->channel));
			fclose($fp);
			echo "Saved!\n";
		} else {
			$json = json_decode(file_get_contents($filename), false);
			if ($this->isUpdated($json, $rss) == false) {
				$fp = fopen($filename, "w");
				fwrite($fp, json_encode($rss->channel));
				fclose($fp);
				echo "Local file has been updated!\n";
			}
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
			echo "Local file is already updated!\n";
			return true;
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
}
?>