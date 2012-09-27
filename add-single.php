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

include_once("fof-main.php");

$url = $_POST['url'];
$unread = $_POST['unread'];
$hash = $_POST['CSRF_hash'];
if (fof_authenticate_CSRF_challenge($hash)){
	print(fof_subscribe(fof_current_user(), $url, $unread));
}
?>
