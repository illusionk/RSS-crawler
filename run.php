<?php 

require_once("crawler.php");

$googleNews = new RSS_Crawler("http://news.google.com.tw/news?pz=1&cf=all&ned=tw&hl=zh-TW&output=rss");
sleep(2);
$UDN = new RSS_Crawler("http://udn.com/udnrss/BREAKINGNEWS1.xml");
sleep(2);
$engadget = new RSS_Crawler("http://chinese.engadget.com/rss.xml");

?>