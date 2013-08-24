<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 * 
 * favicon.php - displays an image cached by SimplePie
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

//may kill off this file
$fn = './cache/' . md5($_GET[i]) . '.spi';
if (file_exists($fn)) {
	//is this a security risk? Review
	$item = unserialize(file_get_contents($fn));
	header('Content-type:' . $item['headers']['content-type']);
	echo $item['body'];
	exit;
	//deprecated
    //SimplePie_Misc::display_cached_file($_GET['i'], './cache', 'spi');
} else {
    header('Location: image/feed-icon.png');
}
?>
