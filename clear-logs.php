<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * clear-logs.php - clears all entries in the log file
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2012-2013 Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */
 
require_once('fof-main.php');

if (!fof_is_admin()){
	die('Only admin may view the logs!');
}

if (isset($_POST['clear']) && isset($_POST['CSRF_hash'])){
	if ($_POST['clear'] == 'true' && fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
		$f = fopen('fof.log','w');
		fwrite($f, '');
		fclose($f);
		echo 'Log file cleared.';
		exit;
	}
}
echo 'Bad request to clear-logs.php.';
