<?php
/*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * api.php - API for fofork, allow external applications to get feeds, etc
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2004-2007 Stephen Minutillo, 2012-2014 Robert Palmer
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = True;
require_once('fof-main.php');

$allowed_actions = array(
	'login' => 'fof_api_login',
	'get_feeds' => 'fof_api_get_feeds',
	'get_items' => 'fof_api_get_items',
	'star_item' => 'fof_api_star_items',
	'mark_read' => 'fof_api_mark_read',
	'tag_item' => 'fof_api_tag_items',
	'update_feed' => 'fof_api_update_feed'
);

$action = $_REQUEST['action'];

$ret = array();
if (array_key_exists($action, $allowed_actions)){
	$handler = $allowed_actions[$action];
	
	//check session token is valid
	if ($action != 'login') {
		if (!check_session()){
			output_json(array(
				'status' => 401,
				'message' => 'Invalid session or login token. Access denied.'));
			die();
		}
	}
	
	//run action
	list($status,$msg,$data) = call_user_func($handler);
	$ret['status'] = $status;
	$ret['message'] = $msg;
	$ret['data'] = $data;
	$ret['CSRF_hash'] = fof_compute_CSRF_challenge();	
	
} else {
	$ret = array(
	'status' => 404,
	'message' => 'Action not found',
	'CSRF_hash' => fof_compute_CSRF_challenge());
}
output_json($ret);
exit();

function output_json($ret){
	$json = json_encode($ret);
	if (json_last_error() == JSON_ERROR_NONE) {
		echo $json;
	} else {
		echo '{"status":500,"message":"Internal error"}';
	}
}


function fof_api_login(){
	//modifies state, use POST
	$username = $_POST['username'];
	$password = $_POST['password'];
	$persistent = $_POST['persistent'] == 'true';
	
	$ret = array(401, 'Incorrect username and password');
	$cookie_logon = fof_validate_cookie();
	if (fof_db_authenticate($username, $password) || $cookie_logon){
		$ret = array(200,'Authentication successful', fof_compute_CSRF_challenge());
		if ($persistent && !$cookie_logon){
			fof_place_cookie(fof_current_user());
		}
	}
	return $ret;
}

function fof_api_get_feeds(){
	$uid = fof_current_user();
	$order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'feed_title';
	$dirn = $_REQUEST['direction'] == 'asc' ? 'asc' : 'desc';
	return array(200,'',fof_get_feeds($uid, $order, $dirn));
}

function fof_api_get_items(){
	$uid = fof_current_user();
	list($feed,$what,$when,$which,$how,$howmany,$order,$search) =
		fof_safe_parse_item_constraints($_REQUEST);
	return array(200,'',fof_get_items($uid, $feed, $what, $when, 
		$which, $howmany, $order, $search));
	
}

function fof_api_tag_items(){
	//Requires CSRF check, only use POST
	//pass in list of items as JSON encoded array
	$ret = array(403,'CSRF attempt detected');
	if (fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
		$item_ids = json_decode($_POST['items']);
		if (json_last_error() == JSON_ERROR_NONE){
			$item_ids = array_map('intval', $item_ids);
			$tag = $_POST['tagname'];
			$uid = fof_current_user();
			fof_tag_item($uid,$item_ids,$tag);
			$ret = array(200);
		}
	} 
	return $ret;
}

function fof_api_star_items(){
	//Requires CSRF check, only use POST
	//pass in list of items as JSON encoded array
	$ret = array(403,'CSRF attempt detected');
	if (fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
		$item_ids = json_decode($_POST['items']);
		if (json_last_error() == JSON_ERROR_NONE){
			$item_ids = array_map('intval', $item_ids);
			$uid = fof_current_user();
			fof_tag_item($uid,$item_ids,'star');
			$ret = array(200);
		}
	}
	return $ret;
}

function fof_api_mark_read(){
	//Requires CSRF check, only use POST
	//pass in list of items as JSON encoded array
	$ret = array(403,'CSRF attempt detected');
	if (fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
		$item_ids = json_decode($_POST['items']);
		if (json_last_error() == JSON_ERROR_NONE){
			$item_ids = array_map('intval', $item_ids);
			$uid = fof_current_user();
			fof_db_mark_read($uid, $item_ids);
			$ret = array(200);
		}
	}
	return $ret;
}

function fof_api_update_feed(){
	$ret = array(403,'CSRF attempt detected');
	if (fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
		$fid = intval($_POST['feed_id']);
		list($count, $error) = fof_update_feed($fid);
		if (!$error){
			$ret = array(200,"$count new items",$count);
		} else {
			$ret = array(500,$error);
		}
	}
	return $ret;
}
?>
