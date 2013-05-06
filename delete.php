<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * delete.php - deletes a feed and all items
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
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
