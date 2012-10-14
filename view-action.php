<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * view-action.php - marks selected items as read (or unread)
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once('fof-main.php');

while (list ($key, $val) = each ($_POST))
{
    $first = false;
    
    if($val == "checked")
    {
        $key = substr($key, 1);
        $items[] = $key;
    }    
}

if($_POST['deltag'] && fof_authenticate_CSRF_challenge($_POST['CSRF_hash']))
{
	fof_untag(fof_current_user(), $_REQUEST['deltag']);
}
else if($_POST['feed'])
{
	fof_db_mark_feed_read(fof_current_user(), $_POST['feed']);
}
else
{
	if($items)
	{
		if($_POST['action'] == 'read')
		{
			fof_db_mark_read(fof_current_user(), $items);
		}
		
		if($_POST['action'] == 'unread')
		{
			fof_db_mark_unread(fof_current_user(), $items);
		}
	}
    if (isset($_POST['return'])){
    	//prevent open redirect by checking return address
    	$regex = '/^https?:\/\/' . preg_replace('/\//','\\/',$FOF_BASE_URL) . '.*$/';
    	$url = urldecode($_POST['return']);
    	if (preg_match($regex, $url)){
    		header("Location: $url");
    	} else {
    		header('Location: index.php');
    	}
    } else {
    	header('Location: index.php');
    }
}
?>
