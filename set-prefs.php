<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * set-prefs.php - interface for changing prefs from javascript
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$prefs =& FoF_Prefs::instance();
$allowedFields = array(
	'favicons' => True,
	'keyboard' => True,
	'direction' => '4',
	'howmany' => 10000,
	'sharing' => '3',
	'feed_order' => '50',
	'feed_direction' => '3',
	'purge' => '3',
	'autotimeout' => '3',
	'manualtimeout' => '3',
	'logging' => '3',
	'tzoffset' => 24,
	'order' => '4',
	'sharedname' => '100',
	'sharedurl' => '500'
);

foreach($_POST as $k => $v){
	if (array_key_exists($k, $allowedFields)){
		$type = gettype($allowedFields[$k]);
		switch ($type){
			case 'string':
			if (strlen($v) < intval($allowedFields[$k]))
				$prefs->set($k, strval($v));
			break;
				
			case 'integer':
			$vc = intval($v);
			if ($vc < $allowedFields[$k]){
				$prefs->set($k, $vc);
			}
			break;
				
			default:
			$x = "is_".$type;
			if ($x($v))
				$prefs->set($k, $v);
		}
    	
    }
}

$prefs->save();

?>
