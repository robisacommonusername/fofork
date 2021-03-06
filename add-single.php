<?php
 /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 * add-single.php - adds a single feed
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

include_once('fof-main.php');

$url = $_POST['url'];
$unread = $_POST['unread'];
$hash = $_POST['CSRF_hash'];
$alreadySubscribed = False;

if (fof_authenticate_CSRF_challenge($hash)){
    if(!$url) {
    	die();
    }
    $user_id = fof_current_user();
    $url = fof_prepare_url($url);    
    $feed = fof_db_get_feed_by_url($url);
    
    if (!$feed) {
		//feed with specified url doesn't exist.  try to parse url given
		$rss = fof_parse($url);
    	if ($rss->error()) {
        	exit('Error: <b>' . htmlspecialchars($rss->error()) . '</b>');
		}
        $url = html_entity_decode($rss->subscribe_url(), ENT_QUOTES);
        $self = $rss->get_link(0, 'self');
        if ($self) {
        	$url = html_entity_decode($self, ENT_QUOTES);
        }
        $feed = fof_db_get_feed_by_url($url);
    }
    
    if ($feed) {
    	//subscribe from either specified or parsed url for existing feed
    	if (fof_db_is_subscribed($user_id, $feed['feed_url'])) {
			//Already subscribed - render the feed link
			$alreadySubscribed = True;
			$link = fof_sanitise_link($feed['feed_link']);
			$description = htmlspecialchars($feed['feed_description'], ENT_QUOTES);
			$title = htmlspecialchars($feed['feed_title'], ENT_QUOTES);
			$url = fof_sanitise_link($feed['feed_url']);
        } else {
			$url = $feed['feed_url'];
			$id = $feed['feed_id'];
			fof_db_add_subscription($user_id, $feed['feed_id']);
			if($unread != 'no') {
				fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);
			}
		}
    } else {
    	//need to add feed to db.  No feed exists with either the specified url
    	//or with the parsed url
    	$icon = 'favicon.php?url=' . urlencode($rss->get_link());
    	$id = fof_add_feed($url, $rss->get_title(), $rss->get_link(), $rss->get_description(), $icon);
        fof_update_feed($id);
        fof_db_add_subscription($user_id, $id);
        if($unread != 'no') {
        	fof_db_mark_feed_unread($user_id, $id, $unread);
        }
        fof_apply_plugin_tags($id, null, $user_id);
    }

//now output some html   
    if ($alreadySubscribed) { ?>
    	    You are already subscribed to <b><a href="<?php echo $link;?>" title="<?php echo $description;?>"><?php echo$title;?></a></b> <a href="<?php echo $url;?>">(rss)</a>
	<?php } else { 
		//successfully subscribed ?>
		<font color="green"><b>Subscribed.</b></font><br />
	<?php }
}
?>
