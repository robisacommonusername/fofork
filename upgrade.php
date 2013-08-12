<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * upgrade.php - upgrades old (1.0 or 1.x or 1.5) fofork to version 1.6
 * 
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2012-2013 Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */

require('fof-main.php'); //old (1.1.x) version

//perform setup - import required functions
function future_make_aes_key() {
	//makes a 128 bit key, returned as a string of 16 raw bytes
	$bytes = null;
	//try to get something cryptographically secure (*nix only)
	if (file_exists('/dev/urandom')){
		try {
			$f = fopen('/dev/urandom', 'r');
			$bytes = fread($f, 16);
			fclose($f);
		} catch (Exception $e) {
			$bytes = null;
		}
	}
	if ($bytes === null) {
		//fallback using mersenne twister.  Not great, but hopefully can extract
		//enough entropy from mt_rand without being able to reconstruct internal state.
		//the hashing is important! Must not give attacker access to the outputs!
		//further note - must NOT use mt_rand ANYWHERE in code where it can give
		//attacker access to output.  Should probably enforce this somehow.
		// Want 128 bits
		for ($i=0; $i<6; $i++){
			$bytes = hash('tiger160,4', $bytes . mt_rand(), True);
		}
	}
	return substr($bytes,0,16);
}

function future_make_salt() {
	//uses only printable characters, not raw binary.
	//22 characters, but only 16bytes entropy
	$k = fof_make_aes_key();
	$salt = substr(str_replace('+', '.', base64_encode($k)), 0, 22);
	return $salt;
}

function future_make_bcrypt_salt() {
	$salt = future_make_salt();
	$effort = fof_db_bcrypt_effort(); //REVIEW: this function may not exist!!
	$final = '$2a$' . $effort . '$' . $salt;
	return $final;
}
$new_version = '1.6.0';
define('FOF_NEW_VERSION','1.6.0');

//version specific setup.  Mostly involving database access
if (!function_exists('fof_db_get_version')){
	//version 1.0 or 1.1 setup
	$old_version = '1.1';
	
	//define some table names
	$FOF_CONFIG_TABLE = FOF_DB_PREFIX . 'config';
	define('BCRYPT_EFFORT', '9');
	
	//database access function
	$fof_upgrader_query = function($query, $args) {
		$args  = array_map('mysql_real_escape_string', $args);
		$query = vsprintf($query, $args);
    
		$result = fof_db_query($query, 1);
		if (mysql_errno()){
			throw new UpgraderDatabaseException();
		}
	};
	
	//database escape function
	$fof_upgrader_escape = function($arg) {
		return mysql_real_escape_string($arg);
	};
} else {
	//version 1.5 or 1.6, with PDO backend
	$old_version = fof_db_get_version();
	
	$fof_upgrader_query = function($query, $args){
		//need to do string interpolation for compatability with old style db
		global $fof_connection;
		$args = array_map(function ($arg) use ($fof_connection) {
			return $fof_connection->quote($arg);
		}, $args);
		$sql = vsprintf($query, $args);
		try {
			$result = fof_query($sql, null, False)
		} catch (PDOException $e) {
			throw new UpgraderDatabaseException();
		}
		return $result;
	};
	
	$fof_upgrader_escape = function($arg) {
		global $fof_connection;
		return $fof_connection->quote($arg);
	};
}

function backup_table($table, $oldSchema){
	global $fof_upgrader_query, $fof_upgrader_escape;
	//very basic dump routine.  Doesn't handle nulls or whatever,
	//but there shouldn't be any
	$backupFn = 'cache' . DIRECTORY_SEPARATOR . "$table.sql";
	echo "backing up $table to file $backupFn <br />";
	$f = fopen($backupFn,'w');
	fwrite($f,"DROP TABLE $table;\n");
	//remove any newlines from $oldSchema
	$oldSchema = str_replace(array("\n", "\r"), array(' ',' '), $oldSchema);
	fwrite($f, $oldSchema);
	fwrite($f, "\n");
	$result = $fof_upgrader_query("SELECT * from $table",null);
	while ($row = fof_db_get_row($result)){
		//$row has both numeric and string keys (ie all the values are in
		//the array twice, with two different keys.  We only want the 
		//string keys, and only want to include each value once.  Be careful!
		$fields = array_filter(array_keys($row), function ($x) {
			return !is_numeric($x);
		});
		$vals = array_map(function ($key) use($row, $fof_upgrader_escape){
			return $fof_upgrader_escape($row[$key]);
		}, $fields);
		$placeholders = implode(',', array_fill(0,count($fields),'`%s`'));
		$sql = vsprintf("INSERT into $table ($placeholders) VALUES ", $fields);
		$placeholders = implode(',', array_fill(0,count($vals),"'%s'"));
		$sql .= vsprintf("($placeholders);\n", $vals);
		fwrite($f, $sql);
	}
	fclose($f);
}
function restore_table($table){
	global $fof_upgrader_query;
	
	$backupFn = 'cache' . DIRECTORY_SEPARATOR . "$table.sql";
	$sql = file_get_contents($backupFn);
	//mysql extension really does not like multiple sql statements
	//separated by a ; - need to split them up, and query one at a time
	//we have ensured in backup_table that each statement starts on a 
	//new line, which makes splitting much easier
	$lines = array_filter(
		array_map('trim', explode("\n", $sql)),
		function ($x){
			return ($x != '');
		});
	foreach ($lines as $line) {
		try {
			$fof_upgrader_query($line,null);
		} catch (UpgraderDatabaseException $e)
			echo 'restore from backup failed! ' . mysql_error() . '<br />';
			echo "backup file is still available at $backupFn - you can try and restore it manually";
			exit;
		}
	}
	echo "Table $table was sucessfully restored from the backup file"; 
	unlink($backupFn);
}
function makeRestorer($table){
	return function() use($table){
		echo "ERROR: could not upgrade table $table <br />";
		restore_table($table);
		exit;
	};
}
function fof_safe_query_nodie(/* $query, [$args...]*/){
	$args  = func_get_args();
    $query = array_shift($args);
    if(is_array($args[0])) $args = $args[0];
    $args  = array_map('mysql_real_escape_string', $args);
    $query = vsprintf($query, $args);
    
    return fof_db_query($query, 1);
}

if (!fof_is_admin()) {
	die('You must be logged in as admin to upgrade fofork!');
}

function upgradePoint1Point6($adminPassword){
	global $FOF_USER_TABLE, $FOF_SESSION_TABLE, $FOF_CONFIG_TABLE;
	global $fof_upgrader_query;
	//performs an upgrade of the database from the 1.1 series to 1.5
	try {
		//create the config table
		$fof_upgrader_query("CREATE TABLE IF NOT EXISTS $FOF_CONFIG_TABLE (
		param VARCHAR( 128 ) NOT NULL ,
		val TEXT NOT NULL ,
		PRIMARY KEY (param))", null);
	
		//add some new parameters
		$fof_upgrader_query("INSERT into $FOF_CONFIG_TABLE (param, val) values ('version', '%s'), ('bcrypt_effort', '%d'), ('max_items_per_request', '%d')", array(FOF_NEW_VERSION, BCRYPT_EFFORT, 100));
		//move admin prefs into config table
		$p =& FoF_Prefs::instance();
		$admin_prefs = $p->admin_prefs;
    
		$adminKeys = array('purge' => null, 'autotimeout' => null, 'manualtimeout' => null, 'logging' => null);
		$actualAdminPrefs = array_intersect_key($admin_prefs, $adminKeys);
		$params = array();
		$args = array();
		foreach ($actualAdminPrefs as $key => $val){
			$params[] = "('%s', '%s')";
			$args[] = $key;
			$args[] = $val;
		}
		$paramString = implode(', ', $params);
		$fof_upgrader_query("INSERT into $FOF_CONFIG_TABLE (param, val) values $paramString", $args);
    
		//generate new log password (old logs become unreadable)
		$logPassword = base64_encode(future_make_aes_key());
		$fof_upgrader_query("INSERT into $FOF_CONFIG_TABLE (param,val) values ('log_password', '%s')", array($logPassword));
    
		//update users table - drop salt, change hashing to bcrypt
		//will need to drop all users except admin user
		//no transactions!!
	
		$result = $fof_upgrader_query("SELECT user_name, user_level, user_prefs from $FOF_USER_TABLE where user_id = 1",null);
	} catch (UpgraderDatabaseException $e) {
		die('Upgrade failed!!');
	}
	if ($row = fof_db_get_row($result)){
		backup_table($FOF_USER_TABLE,
			"CREATE TABLE IF NOT EXISTS `$FOF_USER_TABLE` (
				`user_id` int(11) NOT NULL auto_increment,
				`user_name` varchar(100) NOT NULL default '',
				`user_password_hash` varchar(32) NOT NULL default '',
				`user_level` enum('user','admin') NOT NULL default 'user',
				`user_prefs` text,
				`salt` varchar(40) NOT NULL default '',
				PRIMARY KEY (`user_id`)
			);\n");
		$restorer = makeRestorer($FOF_USER_TABLE);
		try {
			$fof_upgrader_query("DROP TABLE $FOF_USER_TABLE",null);
			$fof_upgrader_query("CREATE TABLE $FOF_USER_TABLE (
  				user_id int(11) NOT NULL auto_increment,
  				user_name varchar(100) NOT NULL default '',
  				user_password_hash varchar(60) NOT NULL default '',
  				user_level enum('user','admin') NOT NULL default 'user',
  				user_prefs text,
  				PRIMARY KEY  (user_id), UNIQUE KEY (user_name)
				)", null);
		} catch (UpgraderDatabaseException $e) {$restorer();}
		
		$salt = future_make_bcrypt_salt();
		$newHash = crypt($adminPassword, $salt);
		try {
			$fof_upgrader_query("INSERT into $FOF_USER_TABLE (user_name, user_password_hash, user_level, user_prefs)
				VALUES ('admin','%s','%s','%s')", array($newHash, $row['user_level'], $row['user_prefs']));	
		} catch (UpgraderDatabaseException $e) {$restorer();}
	}
	
	//rename columns in session table.  Will not be able to do a
	//session_write at the end of this script, but thats fine
	backup_table($FOF_SESSION_TABLE,
		"CREATE TABLE IF NOT EXISTS `$FOF_SESSION_TABLE` (
			`id` varchar(32) NOT NULL,
			`access` int(11) unsigned,
			`data` text,
			PRIMARY KEY (`id`)
		);\n");
	$restorer = makeRestorer($FOF_SESSION_TABLE);
	try {
		$fof_upgrader_query("ALTER table $FOF_SESSION_TABLE change `id` `session_id` varchar(32) NOT NULL", null);
		$fof_upgrader_query("ALTER TABLE $FOF_SESSION_TABLE CHANGE `access` `session_access` INT( 10 ) unsigned NOT NULL",null);
		$fof_upgrader_query("ALTER table $FOF_SESSION_TABLE change `data` `session_data` text");
	} catch (UpgraderDatabaseException $e) {$restorer();}
	
	//delete the backup files
	$backupFn = 'cache'.DIRECTORY_SEPARATOR."$FOF_USER_TABLE.sql";
	@unlink($backupFn);
	$backupFn = 'cache'.DIRECTORY_SEPARATOR."$FOF_SESSION_TABLE.sql";
	@unlink($backupFn);
    
    //clear the log file
    try {
		if (file_exists('fof.log') && is_writable('fof_log')) {
			@$f = fopen('fof.log','w');
			@fwrite($f,'');
			@fclose($f);
		}
	} catch (Exception $e) {}
}

function upgradePoint5Point6() {
	global $FOF_CONFIG_TABLE;
	//update the database log key to new format, clear old logs
	//clear the log file
	$k = future_make_aes_key();
	$encoded = base64_encode($k);
	try {
		fof_query("UPDATE $FOF_CONFIG_TABLE SET val = ? where param = 'log_password'", array($encoded), False);
	} catch (PDOException $e) {
		die('Upgrade process failed, could not update database');
	}
    try {
		if (file_exists('fof.log') && is_writable('fof_log')) {
			@$f = fopen('fof.log','w');
			@fwrite($f,'');
			@fclose($f);
		}
	} catch (Exception $e) {}
}

if (isset($_POST['confirm']) && fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])) {
	//check that correct admin password was entered
	if (fof_db_authenticate('admin', $_POST['admin_password'])) {
		$oldVersion = future_db_get_version();
		$newVersion = FOF_VERSION;
		//can only upgrade from 1.x.y, x<2 to 1.5.z (for now)
		if (version_compare($oldVersion,'1.2','<') && version_compare($newVersion, '1.5', '>=') && version_compare($newVersion,'1.6','<')){
			upgradePoint1Point5($_POST['admin_password']);
			die('Upgrade was successful.  Now replace the fofork code on your webserver with the newer version, and delete this upgrade script');
		}
	} else {
		die('Please enter the correct admin password to upgrade fofork!');
	}
}

?>
<!DOCTYPE html>
<html>

<head><title>fofork database upgrader</title>
<link rel="stylesheet" href="fof.css" media="screen" />
<body>
<center><h1> Upgrade Feed on Feeds (fofork)</h1></center><br />
<div style="display: inline" width=50%>
This script will upgrade the database schema from fofork <?php echo $old_version;?> to fofork <?php echo $new_version;?>
<br />
<br />
You need to be logged in as admin to run this script.  After you have clicked confirm, you should replace all the php files
for fofork on your web server with the newer (version <?php echo $new_version;?>) versions.
<br />
<br />
Please note that if upgrading from versions 1.0.x or 1.1.x to 1.5 or greater, you will lose any user accounts you have created (with the exception of the admin account).  All users except admin will be deleted, and will need to be recreated.  This is due to the new password hashing method used in versions 1.5 and greater.</div>
<br />
<br />
Please back up your existing database before proceeding (or not, it's up to you.  It's just recommended :-) )
<br />
<br />
<center><form action="upgrade.php" method="post">
Admin password: <input type="password" name="admin_password" style="font-size: 16px"><br />
I know what I'm doing, and wish to upgrade the database: <input type="checkbox" name="confirm"><br />
<input type="submit" value="Perform Upgrade" style="font-size: 16px">
<input type="hidden" name="CSRF_hash" value="<?php echo fof_compute_CSRF_challenge();?>"></form></center>
</body>
</html>

