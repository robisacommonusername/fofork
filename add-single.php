<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add-single.php - adds a single feed
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
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
    	if (isset($rss->error())) {
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
			$stripper = new FofFeedSanitiser();
			$link = $stripper->sanitiseLink($feed['feed_link']);
			$description = htmlspecialchars($feed['feed_description'], ENT_QUOTES);
			$title = htmlspecialchars($feed['feed_title'], ENT_QUOTES);
			$url = $stripper->sanitiseLink($feed['feed_url']);
        } else {
			fof_db_add_subscription($user_id, $feed['feed_id']);
			if($unread != 'no') {
				fof_db_mark_feed_unread($user_id, $feed['feed_id'], $unread);
			}
		}
    } else {
    	//need to add feed to db.  No feed exists with either the specified url
    	//or with the parsed url
    	$id = fof_add_feed($url, $rss->get_title(), $rss->get_link(), $rss->get_description() );
		
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
