<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * logout.php - kills user cookie, redirects to login page
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

ob_start();

include_once('fof-main.php');

if ($_GET['everywhere'] == 'yes'){
	fof_db_logout_everywhere();
}

fof_logout();

header('Location: login.php');

?>
