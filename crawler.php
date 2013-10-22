<?php

echo "Initiating...\n";

$test = new RSS_Crawler("http://chinese.engadget.com/rss.xml");
echo $test->getItemCount();

class RSS_Crawler {
	/* cURL option */
	private $feedURL = "";
	private $userAgent = "RSS crawler";

	/* content */
	private $header;
	private $item;
	private $count;

	public function __construct ($url) {
		$feedURL = $url;
		$count = 0;
		$header = new stdClass();
		$item[] = new stdClass();
		$this->capture($feedURL);
	}

	/**
	 *	capture	
	 *	Precondition: give an argument $URL
	 *		$URL: URL of RSS feed.
	 *	Postcondition: capture RSS feed content.
	*/
	private function capture($URL) {
		/* initialate */
		global $count;
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

		/* Source information 
		$header->title = $rss->channel->title;
		$header->link = $rss->channel->link;
		$header->description = $rss->channel->description;
		$header->pubDate = $rss->channel->pubDate;

		for($i = 0; $i < $cnt; $i++) {
			$item[$i] = clone $rss->channel->item[$i];
			//echo($item[$i]->guid);
		}
		*/

		/* Check local cache exist? */
		$this->checkExist($rss);
	}

	/**
	*	checkExist
	*	Precondition: give an argument rss which is an object transform from RSS XML
	*	Postcondition: check the file of RSS feed exist?
	*		Not exist: Store current RSS content.
	*		Exist: Compare local file and RSS content if pubDate are same?
	*/
	private function checkExist($rss) {
		echo "\n>> Compare to exist file\n";
		/* check site cache exist? */
		$filename = md5($rss->channel->link).".json";
		echo "Filename: ".$filename."\n";
		if (file_exists($filename) == false) {
			$fp = fopen($filename, "w");
			fwrite($fp, json_encode($rss->channel));
			fclose($fp);
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
	*	isUpdated
	*	Precondition: Give two argument	$local, $rss.
	*		$local: Object array of local cache file after json decode.
	*		$rss:	Object array of newest RSS feed content.
	*	Postcondition: Compare local and RSS feed newest pubDate
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

	public function getItemCount() {
		global $count;
		return $count;
	}
}
?>