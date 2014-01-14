<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * upgrade.php - upgrades old (1.5) fofork to version 1.6
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

require('fof-main.php'); //old (1.5) version

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
$new_version = '1.6.0';
if (function_exists('fof_db_get_version')){
	$old_version = fof_db_get_version();
} else {
	$old_version = '1.1';
}

if (!fof_is_admin()) {
	die('You must be logged in as admin to upgrade fofork!');
}

function upgradePoint5Point6() {
	global $FOF_CONFIG_TABLE, $FOF_USER_TABLE, $FOF_FEED_TABLE;
	//add the open_registration parameter and
	//update the database log key to new format, clear old logs
	//add email parameter to user table
	//add token_expiry parameter to cookie table
	//update favicon cache format
	//clear the log file
	$k = future_make_aes_key();
	$encoded = base64_encode($k);
	
	global $fof_connection;
	$fof_connection->beginTransaction();
	try {
		fof_query("UPDATE $FOF_CONFIG_TABLE SET val = ? where param = 'log_password'", array($encoded), False);
		fof_query("INSERT into $FOF_CONFIG_TABLE (param, val) VALUES ('open_regsitration','0')", array(), False);
		//create index on user_email
		switch (FOF_DB_TYPE){
			case 'pgsql':
				fof_query("ALTER TABLE $FOF_USER_TABLE ADD user_email varchar(511) NOT NULL after user_level", array(), False);
				fof_query("CREATE UNIQUE INDEX user_email_user_idx on $FOF_USER_TABLE (user_email)");
				break;
			case 'mysql':
				fof_query("ALTER TABLE `$FOF_USER_TABLE` ADD `user_email` VARCHAR( 511 ) NOT NULL AFTER `user_level`, ADD UNIQUE (`user_email`)", array(), False);
			default:
		}
		//add token expiry
		fof_query("ALTER TABLE $FOF_COOKIE_TABLE ADD token_expiry INT NOT NULL AFTER token_hash", array(), False); 
		//update favicon format
		$res = fof_query("SELECT feed_id,feed_image from $FOF_FEED_TABLE", array(), False);
		while ($row = fof_db_get_row($res)){
			$img = $row['feed_image'];
			$id = $row['feed_id'];
			if (preg_match('/^favicon.php?i=/', $img)){
				$newi = md5(urldecode(substr($img, 14)));
				$new_favi = "favicon.php?i=$newi";
				fof_query("UPDATE $FOF_FEED_TABLE set feed_image = ? where feed_id = ?", array($new_favi, $id), False);
			}
		}
		$fof_connection->commit();
	} catch (PDOException $e) {
		die('Upgrade process failed, could not update database');
		$fof_connection->rollBack();
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
		//can only upgrade from 1.5.y, to 1.6.z 
		if (version_compare($old_version,'1.6','<') && version_compare($new_version, '1.5', '>=')){
			upgradePoint5Point6($_POST['admin_password']);
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
<?php if (version_compare($old_version,'1.5', '>=')) { ?>
This script will upgrade the database schema from fofork <?php echo $old_version;?> to fofork <?php echo $new_version;?>
<br />
<br />
You need to be logged in as admin to run this script.  After you have clicked confirm, you should replace all the php files
for fofork on your web server with the newer (version <?php echo $new_version;?>) versions.
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
</div>
<?php } else { ?>
	Cannot upgrade your current version of fofork; it is too old. <br />
	This script can only upgrade from the 1.5 series to the 1.6 series. <br />
	Either first upgrade your installation to the 1.5 series, then run this script,
	or remove your current installation and run a fresh install. <br />
	Sorry!
<?php } ?>
</body>
</html>

