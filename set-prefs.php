<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * set-prefs.php - interface for changing prefs from javascript
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

if (!fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
	die('CSRF detected in set-prefs.php');
}

function sanityCheck($val, $pattern) {
	$ret = False;
	$type = gettype($pattern);
	switch ($type){
		case 'string':
		if (strlen($val) < intval($pattern)) {
			$ret = True;
		}
		break;
				
		case 'integer':
		$vc = intval($val);
		if ($vc < $pattern){
			$ret = True;
		}
		break;
				
		default:
		$x = "is_".$type;
		if ($x($val)) {
			$ret = True;
		}
	}
	return $ret;
}

$prefs =& FoF_Prefs::instance();
$allowedUserFields = array(
	'favicons' => True,
	'keyboard' => True,
	'newtabs' => True,
	'direction' => '4',
	'howmany' => 10000,
	'sharing' => '3',
	'feed_order' => '50',
	'feed_direction' => '3',
	'tzoffset' => 24,
	'order' => '4',
	'sharedname' => '100',
	'sharedurl' => '500'
);
$allowedAdminFields = array(
	'purge' => '3',
	'autotimeout' => 1000,
	'manualtimeout' => 1000,
	'logging' => True,
	'max_items_per_request' => 10000
);

//set any user prefs
foreach (array_intersect_key($_POST, $allowedUserFields) as $k => $v) {
	$pattern = $allowedUserFields[$k];
	if (sanityCheck($v, $pattern)) {
		$prefs->set($k, $v);
	}
}

//set any admin prefs if allowed
if (fof_is_admin()) {
	foreach (array_intersect_key($_POST, $allowedAdminFields) as $k => $v) {
		$pattern = $allowedAdminFields[$k];
		if (sanityCheck($v, $pattern)) {
			$prefs->setAdmin($k, $v);
		}
	}
}

$prefs->save();

?>
