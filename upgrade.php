<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * upgrade.php - upgrades old (1.0 or 1.x) fofork to version 1.5 or 
 * greater
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

//reimplement some standard functions from 1.5.x series
//but using 1.1.x style db calls

function future_make_salt(){
	$bytes = null;
	//try to get something cryptographically secure (*nix only)
	if (file_exists('/dev/urandom')){
		try {
			$f = fopen('/dev/urandom', 'r');
			$bytes = fread($f, 32);
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
	$salt = substr(str_replace('+', '.', base64_encode($bytes)), 0, 22);
	return $salt;
}
function future_make_bcrypt_salt(){
	$salt = future_make_salt();
	$effort = BCRYPT_EFFORT;
	$final = '$2a$' . $effort . '$' . $salt;
	return $final;
}
function future_db_get_version(){
	return '1.1';
}
function backup_table($table, $oldSchema){
	//very basic dump routine.  Doesn't handle nulls or whatever,
	//but there shouldn't be any
	$backupFn = 'cache' . DIRETCORY_SEPARATOR . "$table.sql";
	echo "backing up $table to file $backupFn <br />";
	$f = fopen($backupFn,'w');
	fwrite($f,"DROP TABLE $table;\n");
	fwrite($f, $oldSchema);
	$result = fof_db_query("SELECT * from $table",1);
	while ($row = fof_db_get_row($result)){
		$fields = array_keys($row);
		$vals = array_values($row);
		$vals = array_map('mysql_real_escape_string',$vals);
		$placeholders = implode(',', array_fill(0,count($fields),'`%s`'));
		$sql = vsprintf("INSERT into $table ($placeholders) VALUES "
		$placeholders = implode(',', array_fill(0,count($vals),"'%s'"));
		$sql .= vsprintf("($placeholders);\n", $vals);
		fwrite($f, $sql);
	}
	fclose($f);
}
function restore_table($table){
	$backupFn = 'cache' . DIRECTORY_SEPARATOR . "$table.sql";
	$sql = file_get_contents($backupFn);
	fof_db_query($sql,1);
	if (mysql_errno()){
		echo "restore from backup failed! <br />"
		echo "backup file is still available at $backupFn - you can try and restore it manually";
	} else {
		unlink($backupFn);
	}
}
function makeRestorer($table){
	return function() use($table){
		echo "ERROR: could not upgrade table $table <br />";
		restore_table($table);
		exit;
	}
}


if (!fof_is_admin()) {
	die('You must be logged in as admin to upgrade fofork!');
}

function upgradePoint1Point5($adminPassword){
	global $FOF_USER_TABLE;
	$FOF_CONFIG_TABLE = FOF_CONFIG_TABLE;
	//performs an upgrade of the database from the 1.1 series to 1.5
	
	//create the config table
	fof_safe_query("CREATE TABLE IF NOT EXISTS $FOF_CONFIG_TABLE (
	param VARCHAR( 128 ) NOT NULL ,
	val TEXT NOT NULL ,
	PRIMARY KEY (param))");
	
	//add some new parameters
	fof_safe_query("INSERT into $FOF_CONFIG_TABLE (param, val) values ('version', '%s'), ('bcrypt_effort', '%d'), ('max_items_per_request', '%d')", FOF_VERSION, BCRYPT_EFFORT, 100);
	
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
    fof_safe_query("INSERT into $FOF_CONFIG_TABLE (param, val) values $paramString", $args);
    
    //check - is there a log password in the admin prefs?
    $logPassword = array_key_exists('log_password',$admin_prefs) ? $admin_prefs['log_password'] : fof_make_salt();
    fof_safe_query("INSERT into $FOF_CONFIG_TABLE (param,val) values ('log_password', '%s')", $logPassword);
    
	//update users table - drop salt, change hashing to bcrypt
	//will need to drop all users except admin user
	//no transactions!!
	
	$result = fof_safe_query("SELECT user_name, user_level, user_prefs from $FOF_USER_TABLE where user_id = 1");
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
		fof_safe_query("DROP TABLE $FOF_USER_TABLE");
		if (mysql_errno()) {$restorer();}
		
		fof_safe_query("CREATE TABLE $FOF_USER_TABLE (
  				user_id int(11) NOT NULL auto_increment,
  				user_name varchar(100) NOT NULL default '',
  				user_password_hash varchar(60) NOT NULL default '',
  				user_level enum('user','admin') NOT NULL default 'user',
  				user_prefs text,
  				PRIMARY KEY  (user_id), UNIQUE KEY (user_name)
				)");
		if (mysql_errno()) {$restorer();}
		
		$salt = future_make_bcrypt_salt();
		$newHash = crypt($adminPassword, $salt);
		fof_safe_query("INSERT into $FOF_USER_TABLE (user_name, user_password_hash, user_level, user_prefs)
				VALUES (admin,'%s','%s','%s')", $newHash, $row['user_level'], $row['user_prefs']);	
		if (mysql_errno()) {$restorer();}
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
	fof_safe_query("ALTER table $FOF_SESSION_TABLE change `id` `session_id` varchar(32) NOT NULL");
	if (mysql_errno()) {$restorer();}
	fof_safe_query("ALTER TABLE $FOF_SESSION_TABLE CHANGE `access` `session_access` INT( 10 ) unsigned NOT NULL");
	if (mysql_errno()) {$restorer();}
	fof_safe_query("ALTER table $FOF_SESSION_TABLE change `data` `session_data` text");
	if (mysql_errno()) {$restorer();}
    
    //clear the log file
    $f = fopen('fof.log','w');
    fwrite($f,'');
    fclose($f);
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
$oldVersion = future_db_get_version();
$newVersion = FOF_VERSION;

?>
<!DOCTYPE html>
<html>

<head><title>fofork database upgrader</title>
<link rel="stylesheet" href="fof.css" media="screen" />
<body>
<center><h1> Upgrade Feed on Feeds (fofork)</h1></center><br />
<div style="display: inline" width=50%>
This script will upgrade the database schema from fofork <?php echo $oldVersion;?> to fofork <?php echo $newVersion;?>
<br />
<br />
You need to be logged in as admin to run this script.  After you have clicked confirm, you should replace all the php files
for fofork on your web server with the newer (version <?php echo $newVersion;?>) versions.
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

