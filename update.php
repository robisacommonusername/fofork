<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * update.php - updates feeds with feedback
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

include("header.php");

print("<br />");

$feed = $_GET['feed']; #untrusted
$feeds = array();

$p =& FoF_Prefs::instance();
$admin_prefs = $p->adminPrefs();

if($feed)
{
    $feed = fof_db_get_feed_by_id($feed);
    $feeds[] = $feed;
}
else
{
    if($fof_user_id == 1)
    {
        $result = fof_db_get_feeds();
    }
    else
    {
        $result = fof_db_get_subscriptions(fof_current_user());
    }
    while($feed = fof_db_get_row($result))
    {
        if((time() - $feed["feed_cache_date"]) < ($admin_prefs["manualtimeout"] * 60))
        {
            $title = htmlspecialchars($feed['feed_title']);
            list($timestamp, ) = fof_nice_time_stamp($feed['feed_cache_date']);
            
            print "$title was just updated $timestamp!<br />";
        }
        else
        {
            $feeds[] = $feed;
        }
    }
}

$feeds = fof_multi_sort($feeds, 'feed_cache_attempt_date', false);

print("<script>\nwindow.onload = ajaxupdate;\nfeedslist = [");
    
foreach($feeds as $feed)
{
	$title = $feed['feed_title'];
	$id = $feed['feed_id'];
    
    $feedjson[] = "{'id': $id, 'title': '" . addslashes($title) . "'}";
}

print(join($feedjson, ", "));
print("];\n</script>");

include("footer.php");
?>

