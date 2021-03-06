<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * ompl.php - exports subscription list as OPML
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2004-2007 Stephen Minutillo, 2012-2013 Robert Palmer
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

header("Content-Type: text/xml; charset=utf-8");
include_once("fof-main.php");

echo '<?xml version="1.0"?>';
?>

<opml version="1.1">
  <head>
    <title>Feed on Feeds Subscriptions</title>  
  </head>
  <body>
<?php
$result = fof_db_get_subscriptions(fof_current_user());

while($row = fof_db_get_row($result))
{
	$url = htmlspecialchars($row['feed_url']);
	$title = htmlspecialchars($row['feed_title']);
	$link = htmlspecialchars($row['feed_link']);

	echo <<<HEYO
    <outline type="rss"
             text="$title"
             title="$title"
             htmlUrl="$link"
             xmlUrl="$url"
    />

HEYO;
}
?>
  </body>
</opml>

