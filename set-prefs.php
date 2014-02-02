<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * set-prefs.php - change user preferences.  Can be called in a XHR,
 * 					or used by the prefs.php interface
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
$prefs =& FoF_Prefs::instance();
$CSRF_hash = $_POST['CSRF_hash'];
if (fof_is_admin() 
	&& isset($_POST['adminprefs']) 
	&& fof_authenticate_CSRF_challenge($CSRF_hash)) 
{	
	$allowedKeys = array(
		'purge' => fof_int_validator(0,365),
		'manualtimeout' => fof_int_validator(1,1000),
		'autotimeout' => fof_int_validator(1,1000),
		'logging' => fof_bool_validator(),
		'max_items_per_request' => fof_int_validator(1,1000),
		'bcrypt_effort' => fof_int_validator(5,20),
		'open_registration' => fof_bool_validator()
	);
	//problem; for the checkboxes, if we untick them, nothing gets posted
	//fix that
	foreach (array('logging', 'open_registration') as $k){
		if (!isset($_POST[$k])) {
			$_POST[$k] = 'off';
		}
	}
	foreach (array_intersect_key($_POST, $allowedKeys) as $k => $v){
		$validator = $allowedKeys[$k];
		list($ok, $fixed) = $validator($v);
		if ($ok) {
			$prefs->setAdmin($k, $fixed);
		}
	}
	$prefs->save();
    	
	$message .= ' Saved admin prefs.';
    
    if($prefs->get('logging') && !@fopen("fof.log", 'a'))
    {
        $message .= ' Warning: could not write to log file!';
    }
}

if(isset($_POST['tagfeed']) && fof_authenticate_CSRF_challenge($CSRF_hash))
{
    $tags = htmlspecialchars($_POST['tag'], ENT_QUOTES);
    $feed_id = intval($_POST['feed_id']);
    $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
    
    foreach(explode(" ", $tags) as $tag)
    {
        fof_tag_feed(fof_current_user(), $feed_id, $tag);
        $message .= htmlspecialchars(" Tagged '$title' as $tag.");
    }
}

if(isset($_GET['untagfeed']) && fof_authenticate_CSRF_challenge($_GET['CSRF_hash']))
{
    $feed_id = intval($_GET['untagfeed']);
    $tags = htmlspecialchars($_GET['tag'], ENT_QUOTES);
    $title = htmlspecialchars($_GET['title'], ENT_QUOTES);
	
    foreach(explode(" ", $tags) as $tag)
    {
        fof_untag_feed(fof_current_user(), $feed_id, $tag);
        $message .= htmlspecialchars(" Dropped $tag from '$title'.");
    }
}

if(isset($_POST['prefs']) && fof_authenticate_CSRF_challenge($CSRF_hash)) {
	$allowedKeys = array(
		'favicons' => fof_bool_validator(),
		'keyboard' => fof_bool_validator(),
		'newtabs' => fof_bool_validator(),
		'tzoffset' => fof_int_validator(-12,12),
		'order' => fof_string_validator('/asc|desc/'),
		'sharing' => fof_string_validator('/no|all|shared/'),
		'sharedname' => function($x) {return array(True, htmlspecialchars($x));},
		'sharedurl' => function($x) {return array(True, htmlspecialchars($x));},
		'feed_order' => fof_string_validator('/max_age|feed_title|feed_age|feed_unread/'),
		'feed_direction' => fof_string_validator('/asc|desc/'),
		'howmany' => fof_int_validator(1,250)
	);
	//problem; for the checkboxes, if we untick them, nothing gets posted
	//fix that
	foreach (array('favicons','keyboard','newtabs') as $k){
		if (!isset($_POST[$k])) $_POST[$k] = 'off';
	}
	foreach (array_intersect_key($_POST, $allowedKeys) as $k => $v){
		$validator = $allowedKeys[$k];
		list($ok, $fixed) = $validator($v);
		if ($ok) {
			$prefs->set($k, $fixed);
		}
	}
	$prefs->save();
    
    if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
    {
    	if (fof_db_authenticate(fof_username(), $_POST['exist_pwd'])){
        	fof_db_change_password(fof_username(), $_POST['password']);
        	$message = 'Updated password.';
        } else {
        	$message = 'Current password was not entered correctly';
        }
    }
    else if($_POST['password'] || $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }
	
	$message .= ' Saved prefs.';
}

if(isset($_POST['plugins']) && fof_authenticate_CSRF_challenge($CSRF_hash))
{
    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
    	list($name, $key, $type, $sanitiser) = $plugin_pref;
		if ($sanitiser($_POST[$key])){
        	$prefs->set($key, $_POST[$key]);
        }
    }
    
    $plugins = array();
    $dirlist = opendir(FOF_DIR . DIRECTORY_SEPARATOR . 'plugins');
    while($file=readdir($dirlist))
    {
        if(preg_match('/\.php$/',$file))
        {
           $plugins[] = substr($file, 0, -4);
        }
    }

    closedir();
        
    foreach($plugins as $plugin)
    {
        $prefs->set("plugin_" . $plugin, $_POST[$plugin] == "on");
    }

	$prefs->save(fof_current_user());

	$message .= ' Saved plugin prefs.';
}

if(fof_is_admin() && isset($_POST['changepassword']) && fof_authenticate_CSRF_challenge($CSRF_hash)) 
{
	if (fof_db_authenticate(fof_username(), $_POST['admin_password'])){
    	if($_POST['password'] != $_POST['password2'])
    	{
        	$message = "Passwords do not match!";
    	}
    	else
    	{
        	$username = $_POST['username'];
        	$password = $_POST['password'];
        	fof_db_change_password($username, $password);
        	$message = htmlspecialchars("Changed password for $username.");
    	}
    } else {
    	$message = 'Please enter a valid admin password';
    }
}

if(fof_is_admin() && isset($_POST['adduser']) && $_POST['username'] && $_POST['password'] && fof_authenticate_CSRF_challenge($CSRF_hash)) 
{
	if (fof_db_authenticate(fof_username(), $_POST['admin_password'])){
    	$username = htmlspecialchars($_POST['username']);
    	if (preg_match('/^[a-zA-Z0-9]{1,32}$/',$username)){
    		$password = $_POST['password'];
    		$uniq = fof_db_add_user($username, $password);
			$message = $uniq ? "User '$username' added." : "A user named '$username' already exists.  Please try again";
			if ($uniq){
				//confirm this user (since added manually by admin)
				$uid = fof_db_get_user_id($username);
				$u_prefs = new FoF_Prefs($uid);
				$u_prefs->set('confirmed',true);
				$u_prefs->save();
			}
		} else {
			$message = 'Invalid username entered';
		}
	} else {
		$message = 'Please enter a valid admin password';
	}
	
}


if(fof_is_admin() && isset($_POST['deleteuser']) && $_POST['username'] && fof_authenticate_CSRF_challenge($CSRF_hash))
{
	if (fof_db_authenticate(fof_username(), $_POST['admin_password'])){
		$username = $_POST['username'];
		fof_db_delete_user($username);
		$message = htmlspecialchars("User '$username' deleted.");
	} else {
		$message = 'Please enter a valid admin password';
	}
}

if (isset($message)){
	$escaped_msg = htmlspecialchars($message,ENT_QUOTES,'UTF-8',False);
	echo "<br><font color=\"red\">$escaped_msg</font><br />";
}
?>
