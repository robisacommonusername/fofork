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

$url = $_REQUEST['url'];
$unread = $_REQUEST['unread'];

#WTF, I can subscribe you to anything I want.  hahaha
print(fof_subscribe(fof_current_user(), $url, $unread));
?>
