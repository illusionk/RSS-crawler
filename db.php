<?php
class c2mysql {
	private $link;
	private $user_db;

	public function __construct() {
		global $link, $user_db;
		// Init
		$dbhost = "127.0.0.1";
		$dbuser = "";
		$dbpass = "";
		$dbname = "";			// RSS DB (REQUIRED)
		$user_db = "";			// Sidebar DB (REQUIRED)
		$dbport = "3306";

		$link = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname, $dbport);
		if (!$link) {
		    die('Could not connect: ' . mysqli_error($link));
		}
		mysqli_set_charset($link, "utf8");
		echo "Connected successfully\n";
	}

	public function getOldMaxLink($table) {
		global $link;
		$sql = "SELECT `link` FROM `".$table."` ORDER BY `pubDate` DESC LIMIT 1;";
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
		if ($this->checkTableExist($table) == true) {
			echo "Table already exists\n";
			return;
		}
		$sql="CREATE TABLE `".$table."`(doc SERIAL, id VARCHAR(32), title VARCHAR(500), link VARCHAR(2083), description TEXT, author VARCHAR(200), category VARCHAR(2000), comments VARCHAR(2083), enclosure TEXT, guid VARCHAR(2083), pubDate DATETIME, year VARCHAR(4), month VARCHAR(2), day VARCHAR(2), hour VARCHAR(2), minute VARCHAR(2), source VARCHAR(2083), summary TEXT, img VARCHAR(2083), dread BOOLEAN, dsave BOOLEAN) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;";

		$result = mysqli_query($link, $sql) or die("Couldn't create TABLE ".mysqli_error($link)."\n");
		echo "TABLE ".$table." created!\n";
	}

	public function createSidebarItem($table, $title) {
		global $link, $user_db;
		$sql = "INSERT INTO `".$user_db."`.`sidebar` (`doc`, `name`, `url`, `sourceId`) VALUES (NULL, '".$title."', '', '".$table."');";
		$result = mysqli_query($link, $sql) or die("Couldn't create SideBar item ".mysqli_error($link)."\n");
		echo "Sidebar ".$table." (".$title.") created!\n";
	}

	public function insertContent($type, $table, $title, $clink, $description, $author, $category, $comments, $enclosure, $guid, $pubDate, $source, $summary, $img) {
		global $link;
		// TABLE not exist
		if ($this->checkTableExist($table) == false) {
			echo "Table not exists, creating...\n";
			$this->createContentTable($table);
		}

		$sql = "SELECT `id` FROM `".$table."` WHERE `id` LIKE '".md5($clink)."' LIMIT 1;";
		$result = mysqli_query($link, $sql) or die('Insert: ' . mysqli_error($link));
		if(mysqli_num_rows($result) != 0) {
			echo "Article Exist!\n";
			return;
		}
		if ($type == "RSS"){
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

			$date = $time[3]."-".$time[2]."-".$time[1]." ".$time[4].":".$time[5].":".$time[6];

			// INSERT
			$sql = "INSERT INTO `".$table."` (`id`, `title`, `link`, `description`, `author`, `category`, `comments`, `enclosure`, `guid`, `pubDate`, `day`, `month`, `year`, `hour`, `minute`, `source`, `summary`, `img`, `dread`, `dsave`) VALUES ('".md5($clink)."', '".mysqli_real_escape_string($link, $title)."', '".$clink."', '".mysqli_real_escape_string($link, $description)."', '".$author."', '".$category."', '".$comments."', '".$enclosure."', '".$guid."', '".$date."', '".$time[1]."', '".$time[2]."', '".$time[3]."', '".$time[4]."', '".$time[5]."', '".$source."', '".mysqli_real_escape_string($link, $summary)."', '".$img."','0', '0');";
		} else if ($type == "ATOM") {
			preg_match("/([0-9]{4})-([0-9]{1,})-([0-9]{1,})T([0-9]{1,}):([0-9]{1,}):([0-9]{1,})/", $pubDate, $time);

			$date = $time[1]."-".$time[2]."-".$time[3]." ".$time[4].":".$time[5].":".$time[6];

			// INSERT
			$sql = "INSERT INTO `".$table."` (`id`, `title`, `link`, `description`, `author`, `category`, `comments`, `enclosure`, `guid`, `pubDate`, `day`, `month`, `year`, `hour`, `minute`, `source`, `summary`, `img`, `dread`, `dsave`) VALUES ('".md5($clink)."', '".mysqli_real_escape_string($link, $title)."', '".$clink."', '".mysqli_real_escape_string($link, $description)."', '".$author."', '".$category."', '".$comments."', '".$enclosure."', '".$guid."', '".$date."', '".$time[3]."', '".$time[2]."', '".$time[1]."', '".$time[4]."', '".$time[5]."', '".$source."', '".mysqli_real_escape_string($link, $summary)."', '".$img."','0', '0');";
		}
		
		$result = mysqli_query($link, $sql) or trigger_error('Insert: ' . mysqli_error($link));
		echo "INSERT complete!\n";
	}
}
?>
