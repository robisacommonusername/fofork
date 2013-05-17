<?php
 /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 * 
 * delete.php - deletes a feed and all items
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

if (fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
	$feed_id = intval($_POST['feed_id']);
	fof_delete_subscription(fof_current_user(), $feed_id);	
}
