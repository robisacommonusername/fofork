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

require('classes/IconDownloader.php');
$url = $_GET['url'];
$downloader = new IconDownloader($url);
$gd_img = $downloader->getIconImage();
//var_dump($gd_img);
//die();
header('Content-type: image/png');
imagepng($gd_img);
imagedestroy($gd_img);
?>
