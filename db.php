<?php

//  Connect
//$test = new c2mysql();
//$test->getOldMaxLink("75e1d25824145c6ff33aa8f3505f3eba");

class c2mysql {
	private $link;

	public function __construct() {
		global $link, $dbhost, $dbuser, $dbpass, $dbname;
		// Init
		$dbhost = "host";
		$dbuser = "user";
		$dbpass = "pass";
		$dbname = "database";

		$link = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
		if (!$link) {
		    die('Could not connect: ' . mysqli_error($link));
		}
		mysqli_set_charset($link, "utf8");
		echo "Connected successfully\n";
	}

	public function getOldMaxLink($table) {
		global $link;
		$sql = "SELECT `link` FROM `".$table."` ORDER BY `doc` DESC LIMIT 1;";
		$result = mysqli_query($link, $sql);
		$data = mysqli_fetch_array($result);

		return $data[0];
	}

	public function checkTableExist($table) {
		global $link;
		$result = mysqli_query($link, "SHOW TABLES LIKE '".$table."'") or die ('[ERROR]Query: ' . mysqli_error($link));
		if(mysqli_num_rows($result) == 1)
		    return true;
		else
			return false;
	}

	public function createContentTable($table) {
		global $link;
		if ($this->checkTableExist($table) == ture) {
			echo "Table already exists\n";
			return;
		}
		$sql="CREATE TABLE `".$table."`(doc SERIAL, id VARCHAR(32), title VARCHAR(500), link VARCHAR(2083), description TEXT, author VARCHAR(200), category VARCHAR(2000), comments VARCHAR(2083), enclosure TEXT, guid VARCHAR(2083), pubDate VARCHAR(100), year VARCHAR(4), month VARCHAR(2), day VARCHAR(2), hour VARCHAR(2), minute VARCHAR(2), source VARCHAR(2083), summary TEXT, img VARCHAR(2083), dread BOOLEAN, dsave BOOLEAN);";

		$result = mysqli_query($link, $sql) or die("Couldn't create TABLE ".mysqli_error($link)."\n");
		echo "TABLE ".$table." created!\n";
	}

	public function insertContent($table, $title, $clink, $description, $author, $category, $comments, $enclosure, $guid, $pubDate, $source, $summary, $img) {
		global $link;
		// TABLE not exist
		if ($this->checkTableExist($table) == false) {
			echo "Table not exists, creating...\n";
			$this->createContentTable($table);
		}

		preg_match("/.*, [0]*([0-9]{1,}) (.*) (.*) [0]*([0-9]{1,}):[0]*([0-9]{1,}):[0]*([0-9]{1,}) .*/", $pubDate, $time);

		switch($time[2]) {
			case "Dec":
				$time[2] = "12";
				break;
			case "Nov":
				$time[2] = "11";
				break;
			case "Oct":
				$time[2] = "10";
				break;
			case "Sep":
				$time[2] = "9";
				break;
			case "Aug":
				$time[2] = "8";
				break;
			case "Jul":
				$time[2] = "7";
				break;
			case "Jun":
				$time[2] = "6";
				break;
			case "May":
				$time[2] = "5";
				break;
			case "Apr":
				$time[2] = "4";
				break;
			case "Mar":
				$time[2] = "3";
				break;
			case "Feb":
				$time[2] = "2";
				break;
			case "Jan":
				$time[2] = "1";
				break;
		}

		// INSERT
		$sql = "INSERT INTO `".$table."` (`id`, `title`, `link`, `description`, `author`, `category`, `comments`, `enclosure`, `guid`, `pubDate`, `day`, `month`, `year`, `hour`, `minute`, `source`, `summary`, `img`, `dread`, `dsave`) VALUES ('".md5($clink)."', '".mysqli_real_escape_string($link, $title)."', '".$clink."', '".mysqli_real_escape_string($link, $description)."', '".$author."', '".$category."', '".$comments."', '".$enclosure."', '".$guid."', '".$time[0]."', '".$time[1]."', '".$time[2]."', '".$time[3]."', '".$time[4]."', '".$time[5]."', '".$source."', '".mysqli_real_escape_string($link, $summary)."', '".$img."','0', '0');";
		$result = mysqli_query($link, $sql) or die('Insert: ' . mysqli_error($link));
		echo "RSS INSERT complete!\n";
	}
}
?>
