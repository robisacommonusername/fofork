<?php

require('fof-main.php');

if (!fof_is_admin()) {
	die('You must be logged in as admin to upgrade fofork!');
}

function upgradePoint1Point5($adminPassword){
	global $FOF_CONFIG_TABLE, $FOF_USER_TABLE;
	//performs an upgrade of the database from the 1.1 series to 1.5
	
	//create the config table
	fof_query("CREATE TABLE IF NOT EXISTS `$FOF_CONFIG_TABLE` (
	`param` VARCHAR( 128 ) NOT NULL ,
	`val` TEXT NOT NULL ,
	UNIQUE KEY (`param`))", null);
	
	//add some new parameters
	fof_query("INSERT into $FOF_CONFIG_TABLE (param, val) values ('version', ?), ('bcrypt_effort', ?)", array(FOF_VERSION, bcrypt_EFFORT));
	
	//move admin prefs into config table
	$p =& FoF_Prefs::instance();
    $admin_prefs = $p->admin_prefs;
    
    $adminKeys = array('purge' => null, 'autotimeout' => null, 'manualtimeout' => null, 'logging' => null);
    $actualAdminPrefs = array_intersect_key($admin_prefs, $adminKeys);
    $params = array();
    $args = array();
    foreach ($actualAdminPrefs as $key => $val){
    	$params[] = '(?, ?)';
    	$args[] = $key;
    	$args[] = $val;
    }
    $paramString = implode(', ', $params);
    fof_query("INSERT into $FOF_CONFIG_TABLE (param, val) values $paramString", $args);
    
	//update users table - drop salt, change hashing to bcrypt
	//will need to drop all users except admin user
	$result = fof_query("SELECT user_name, user_level, user_prefs from $FOF_USER_TABLE where user_id = 1", null);
	if ($row = fof_db_get_row($result)){
		global $fof_connection;
		$fof_connection->beginTransaction();
		try {
			$fof_connection->query("DROP TABLE `$FOF_USER_TABLE`");
			$fof_connection->query("CREATE TABLE `$FOF_USER_TABLE` (
  				`user_id` int(11) NOT NULL auto_increment,
  				`user_name` varchar(100) NOT NULL default '',
  				`user_password_hash` varchar(60) NOT NULL default '',
  				`user_level` enum('user','admin') NOT NULL default 'user',
  				`user_prefs` text,
  				PRIMARY KEY  (`user_id`), UNIQUE KEY (`user_name`)
				)");
			$salt = fof_make_bcrypt_salt();
			$row['user_password_hash'] = crypt($adminPassword, $salt);
			$stmnt = $fof_connection->prepare("INSERT into $FOF_USER_TABLE (user_name, user_password_hash, user_level, user_prefs)
				values (:user_name, :user_password_hash, :user_level, :user_prefs)");
			$stmnt->execute($row);
			$fof_connection->commit();	
		} catch (Exception $e) {
			$fof_connection->rollBack();
		}
	}
}

if (isset($_POST['confirm']) && fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])) {
	//check that correct admin password was entered
	if (fof_db_authenticate('admin', $_POST['admin_password'])) {
		$oldVersion = fof_db_get_version();
		$newVersion = FOF_VERSION;
		//can only upgrade from 1.x.y, x<2 to 1.5.z (for now)
		if (version_compare($oldVersion,'1.2','<') && version_compare($newVersion, '1.5', '>=') && version_compare($newVersion,'1.6','<')){
			upgradePoint1Point5($_POST['admin_password']);
			die("Upgrade was successful.  Now replace the fofork code on your webserver with the newer version, and delete this upgrade script");
		}
	} else {
		die("Please enter the correct admin password to upgrade fofork!");
	}
}
$oldVersion = fof_db_get_version();
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

