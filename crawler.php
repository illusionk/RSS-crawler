<?php

header('Content-type: text/html; charset=utf-8'); 
header('Vary: Accept-Language'); 

require("db.php");
require("converter.php");

/* Connention test, should remove after publish */
//$test = new RSS_Crawler("http://feeds.feedburner.com/blogspot/VQFAg?format=xml");

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
/* Connection test end, should remove above after release */

class RSS_Crawler {
	/* cURL option */
	private $feedURL = "";
	private $userAgent = "RSS crawler";

	/* content */
	private $tableName, $md5name;
	private $header;
	private $content;
	private $uncachedContent;
	private $mysql;
	private $type;

	public function __construct ($url) {
		global $header, $content, $mysql, $type;
		echo "\n-----------------------------------\n";
		echo "	Initiating...\n";
		echo "-----------------------------------\n";

		$feedURL = $url;

		if ($feedURL == NULL) {
			echo ">> Need an argument.\n Example: \$test = new RSS_Crawler(\"http://chinese.engadget.com/rss.xml\");";
		} else {
			$mysql = new c2mysql();
			$tableName = "";
			$type = "";
			$header = new stdClass();
			$content[] = new stdClass();
			$uncachedContent[] = new stdClass();
			$this->capture($feedURL);
		}
	}

	public function __destruct() {
		global $link, $content, $tableName, $uncachedContent, $type, $mysql;
		mysqli_close($link);
		unset($link);
		foreach ($this as $key => $content) {
            unset($this->$key);
        }
		unset($content);
		unset($tableName);
		foreach ($this as $key => $uncachedContent) {
            unset($this->$key);
        }
		unset($content);
		unset($uncachedContent);
		unset($type);
		unset($mysql);
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
		global $header, $content, $tableName, $type;
		$ch = curl_init();

		$options = array(
			CURLOPT_URL=>$URL,
			CURLOPT_HEADER=>0,
			CURLOPT_VERBOSE=>0,
			CURLOPT_RETURNTRANSFER=>true
		);
		curl_setopt_array($ch, $options);

		echo ">> Captureing...\n"."target: ".$URL."\n";

		/* Capture content */
		$result = curl_exec($ch);

		/* Close connection */ 
		curl_close($ch);
		echo ">> Captured!\n";

		/* Convert XML to object */
		$parsed = simplexml_load_string($result);

		// ATOM or RSS
		if(count($parsed->channel) == 0) {
			$channel = $parsed;
			$channelTitle = $parsed->title;
			$type = "ATOM";
		}else {
			$channel = $parsed->channel;
			$channelTitle = $parsed->channel->title;
			$type = "RSS";
		}


		// Get filename
		if($type == "ATOM") {
			if(count($channel->link) > 1) {
				$GLOBALS['md5name'] = md5($channel->link[0]->attributes()->href);
				$tableName = md5($channel->link[0]->attributes()->href);
			}else {
				$GLOBALS['md5name'] = md5($channel->link->attributes()->href);
				$tableName = md5($channel->link->attributes()->href);
			}
		}else if($type == "RSS") {
			$GLOBALS['md5name'] = md5($channel->link);
			$tableName = md5($channel->link);
		}
		
		// Get content count.
		if($type == "ATOM") {
			$count = count($channel->entry);
		} else if ($type == "RSS") {
			$count = count($channel->item);
		}

		/* Save Source information 
		$header->title = $channel->title;
		$header->link = $channel->link;
		$header->description = $channel->description;
		// Optional
		$header->pubDate = $channel->pubDate;
		$header->ttl = $channel->ttl;
		*/

		/* Save Content */
		if($type == "ATOM") {
			for($i = 0, $j = 0; $i < $count; $i++) {
				if($channel->entry[$i]->title == "") {
					echo "No title, skip!\n";
					continue;
				}
				$content[$j] = clone $channel->entry[$i];
				$j++;
			}
		} else if ($type == "RSS") {
			for($i = 0, $j = 0; $i < $count; $i++) {
				if($channel->item[$i]->title == "") {
					echo "No title, skip!\n";
					continue;
				}
				$content[$j] = clone $channel->item[$i];
				$j++;
			}
		}

		$this->savePage($channel, $channelTitle);

		unset($parsed);
	}

	private function html2txt($document){ 
		$search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript 
		               '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags 
		               '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly 
		               '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments including CDATA
		); 
		$text = preg_replace($search, '', $document); 
		return $text; 
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
	private function savePage($channel, $channelTitle) {
		global $uncachedContent, $tableName, $content, $mysql, $type;

		echo "\n\n>> Saving page...\n";
		echo "Compare to exist file\n";
		/* check site cache exist? */
		echo "Source: ".$tableName."\n";
		if ($mysql->checkTableExist($tableName) == false) {
			echo "Source not exist, create table.\n";
			$mysql->createContentTable($tableName);
			echo "Created!\n\n";

			echo "Create Sidebar Item.\n";
			$mysql->createSidebarItem($tableName, $channelTitle);
			echo "Created!\n\n";

			echo "INSERT data...\n";

			//print_r($content);
			if ($type == "RSS") {
				for($i=count($content)-1; $i>=0; $i--) {
					if($content[$i]->link == NULL) {
						echo "Content error, skip\n";
						continue;
					}
					$doc = new Reader();
					$doc->input($content[$i]->link);
					$doc->init();
					$strip_content = $strip_content = $this->html2txt($content[$i]->description);

					$img_arr = array();
					$img_arr = $doc->reImg();
					if(count($img_arr) == 0)
						$img = "";
					else 
						$img = $img_arr[0];

					$mysql->insertContent($type, $tableName, $content[$i]->title, $content[$i]->link, $content[$i]->description, $content[$i]->author, $content[$i]->category, $content[$i]->comments, $content[$i]->enclosure, $content[$i]->guid, $content[$i]->pubDate, $content[$i]->source, mb_substr($strip_content,0,250,"utf8"), $img);
				}
			} else if ($type == "ATOM") {
				for($i=count($content)-1; $i>=0; $i--) {
					if($content[$i]->link == NULL) {
						echo "Content error, skip\n";
						continue;
					}

					// Get Article Link.
					$articleLink = "";
					if(count($content[$i]->link) > 1) {
						for($j = 0; $j < count($content[$i]->link); $j++){
							if ($content[$i]->link[$j]->attributes()->rel == "self") {
								$articleLink = $content[$i]->link[$j]->attributes()->href;
								break;
							}
						}
					} else {
						$articleLink = $content[$i]->link->attributes()->href;
					}

					$doc = new Reader();
					$doc->input($articleLink);
					$doc->init();
					$strip_content = $this->html2txt($content[$i]->content);

					$img_arr = array();
					$img_arr = $doc->reImg();
					if(count($img_arr) == 0)
						$img = "";
					else 
						$img = $img_arr[0];

					$mysql->insertContent($type, $tableName, $content[$i]->title, $articleLink, $content[$i]->content, $content[$i]->author->name, $content[$i]->category->attributes()->term, "", "", $content[$i]->id, $content[$i]->published, "", mb_substr($strip_content,0,250,"utf8"), $img);
				}
			}
			
			echo "\n\n[EXIT]INSERT COMPELETE!\n\n";
		} else {
			echo "TABLE exists, getting uncached content...\n";
			$uncachedContent = $this->returnUncachedContent($channel);

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
		global $uncachedContent, $tableName, $mysql, $type;
		echo "\n\n>> Saving uncache contents\n";

		echo "INSERT data...\n";
		if($type == "RSS") {
			for($i=count($uncachedContent)-1; $i>=0; $i--) {
				if($uncachedContent[$i]->link == NULL) {
					echo "Content error, skip\n";
					continue;
				}
				$doc = new Reader();
				$doc->input($uncachedContent[$i]->link);
				$doc->init();
				$strip_content = $this->html2txt($uncachedContent[$i]->description);
				//$doc->getContent()

				$img_arr = array();
				$img_arr = $doc->reImg();

				if(count($img_arr) == 0)
					$img = "";
				else 
					$img = $img_arr[0];

				$mysql->insertContent($type, $tableName, $uncachedContent[$i]->title, $uncachedContent[$i]->link, $uncachedContent[$i]->description, $uncachedContent[$i]->author, $uncachedContent[$i]->category, $uncachedContent[$i]->comments, $uncachedContent[$i]->enclosure, $uncachedContent[$i]->guid, $uncachedContent[$i]->pubDate, $uncachedContent[$i]->source, mb_substr($strip_content,0,250,"utf8"), $img);
			}
		} else if ($type == "ATOM") {
			for($i = count($uncachedContent)-1; $i >= 0; $i--) {

				if($uncachedContent[$i]->link == NULL) {
					echo "Content error, skip\n";
					continue;
				}

				// Get Article Link.
				$articleLink = "";
				echo count($uncachedContent[$i]->link);
				if(count($uncachedContent[$i]->link) > 1) {
					for($j = 0; $j < count($uncachedContent[$i]->link); $j++){
						if ($uncachedContent[$i]->link[$j]->attributes()->rel == "self") {
							$articleLink = $uncachedContent[$i]->link[$j]->attributes()->href;
							break;
						}
					}
				} else {
					$articleLink = $uncachedContent[$i]->link->attributes()->href;
				}

				$doc = new Reader();
				$doc->input($articleLink);
				$doc->init();
				$strip_content = $this->html2txt($uncachedContent[$i]->content);

				$img_arr = array();
				$img_arr = $doc->reImg();
				if(count($img_arr) == 0)
					$img = "";
				else 
					$img = $img_arr[0];

				$mysql->insertContent($type, $tableName, $uncachedContent[$i]->title, $articleLink, $uncachedContent[$i]->content, $uncachedContent[$i]->author->name, $uncachedContent[$i]->category->attributes()->term, "", "", $uncachedContent[$i]->id, $uncachedContent[$i]->published, "", mb_substr($strip_content,0,250,"utf8"), $img);
			}
		}
		
		echo "\n\n[EXIT] INSERT COMPELETE!\n";
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
	private function isUpdated($channel) {
		global $tableName, $mysql, $type;

		$oldMax = $mysql->getOldMaxLink($tableName);

		//echo "\n\n Table: ".$tableName.", ".$channel->item[0]->link;
		if($type == "RSS") {
			if ($oldMax != $channel->item[0]->link) {
				return false;
			} else {
				return true;
			}
		} else if ($type == "ATOM") {

			// Get Article Link.
			$articleLink = "";
			if(count($channel->entry[0]->link) > 1) {
				for($j = 0; $j < count($channel->entry[0]->link); $j++){
					if ($channel->entry[0]->link[$j]->attributes()->rel == "self") {
						$articleLink = $channel->entry[0]->link[$j]->attributes()->href;
						break;
					}
				}
			} else {
				$articleLink = $channel->entry[0]->link->attributes()->href;
			}

			if ($oldMax != $articleLink) {
				return false;
			} else {
				return true;
			}
		}

	}

	/**
	*	Check last update time to decide which content is not cached, and return.
	*
	*	Post:	Return an object array of RSS content which hasn't been cached. 
	*			Return NULL if nothing new.
	*/
	private function returnUncachedContent($channel) {
		global $mysql, $tableName, $type;
		$uncachedContent[] = new stdClass();
		echo ">> Find uncached content...\n";
		echo "Checking version...\n";
		if($this->isUpdated($channel) == true) {
			echo "\n[EXIT] All cached, cya!\n\n";
			return NULL;
		} else {
			echo "Seems new articles is published, Let's catch'em!\n";
			$oldMax = $mysql->getOldMaxLink($tableName);

			if ($type == "RSS") {
				$count = count($channel->item);

				for ($i = 0; $i < $count; $i++) {
					if ($oldMax != $channel->item[$i]->link) {
						$uncachedContent[$i] = clone $channel->item[$i];
					} else {
						break;
					}
				}
			} else if ($type == "ATOM") {
				$count = count($channel->entry);

				for ($i = 0; $i < $count; $i++) {

					$articleLink = "";
					if(count($channel->entry[$i]->link) > 1) {
						for($j = 0; $j < count($channel->entry[$i]->link); $j++){
							if ($channel->entry[$i]->link[$j]->attributes()->rel == "self") {
								$articleLink = $channel->entry[$i]->link[$j]->attributes()->href;
								break;
							}
						}
					} else {
						$articleLink = $channel->entry[$i]->link->attributes()->href;
					}

					if ($oldMax != $articleLink) {
						$uncachedContent[$i] = clone $channel->entry[$i];
					} else {
						break;
					}
				}
			}
			
			echo "New article has been picked!\n";
			return $uncachedContent;
		}
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