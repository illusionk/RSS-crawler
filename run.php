<?php 

require("crawler.php");
$macrumor = new RSS_Crawler("http://feeds.macrumors.com/MacRumors-All?format=xml");
unset($macrumor);
sleep(2);
$inside = new RSS_Crawler("http://feeds.feedburner.com/inside-blog-taiwan?format=xml");
unset($inside);
sleep(2);
$fankudo = new RSS_Crawler("http://feeds.feedburner.com/fankudo/aZse?format=xml");
unset($fankudo);
sleep(2);
$web = new RSS_Crawler("http://feeds.feedburner.com/webresourcesdepot?format=xml");
unset($web);
sleep(2);
$googleNews = new RSS_Crawler("http://news.google.com.tw/news?pz=1&cf=all&ned=tw&hl=zh-TW&output=rss");
unset($googleNews);
sleep(2);
$UDN = new RSS_Crawler("http://udn.com/udnrss/BREAKINGNEWS1.xml");
unset($UDN);
sleep(2);
$engadget = new RSS_Crawler("http://chinese.engadget.com/rss.xml");
unset($engadget);
sleep(2);
$horny = new RSS_Crawler("http://feeds.feedburner.com/blogspot/VQFAg?format=xml");
unset($horny);
sleep(2);
$yahoo_baseball = new RSS_Crawler("http://tw.news.yahoo.com/rss/baseball");
unset($yahoo_baseball);
?>