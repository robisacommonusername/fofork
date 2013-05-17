<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * update-single.php - updates a single feed
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

include_once("fof-main.php");

$feed = $_GET['feed'];

list ($count, $error) = fof_update_feed($feed);
	
if($count)
{
    print "<b><font color=red>$count new items</font></b>";
}

if($error)
{
    print " $error <br>";
}
else
{
    print " Done.<br>";
}

?>
