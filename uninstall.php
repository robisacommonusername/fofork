<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * uninstall.php - if confirmed, drops FoF's tables
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");
if (!fof_is_admin()){
	die('Fuck off, you non-administrator scum.  You are not supposed to be here!');
}

fof_set_content_type();

?>
<!DOCTYPE html>
<html>

	<head>
		<title>feed on feeds - uninstallation</title>
		<link rel="stylesheet" href="fof.css" media="screen" />
		<script src="fof.js" type="text/javascript"></script>
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
	</head>

	<body id="panel-page">


<?php
if($_POST['confirmed'] == 'delete' && fof_authenticate_CSRF_challenge($_POST['CSRF_hash']) && fof_is_admin()){
	if (!fof_db_authenticate(fof_username(), $_POST['admin_password'])){
		die('Incorrect password.  Please enter an admin password to proceed');
	}

	$tables = array($FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE,
				$FOF_SUBSCRIPTION_TABLE, $FOF_USER_TABLE, $FOF_COOKIE_TABLE, $FOF_SESSION_TABLE);
	$allTables = implode(', ', $tables);
	fof_query_log("DROP TABLE $allTables", null);

	echo 'Done.  Now just delete this entire directory and we\'ll forget this ever happened.';
} elseif (!isset($_POST['confirmed'])) {
?>
Please be aware the uninstalling will delete all of Feed on Feeds' database tables. <br />
This is your absolute last chance.  Do you really want to uninstall Feed on Feeds? <br /><br />
<form name="confirmation_form" method="post" action="uninstall.php">
Administrator Password: <input type="password" name="admin_password" value=""><br />
<input type="hidden" name="CSRF_hash" value="<?php echo fof_compute_CSRF_challenge();?>">
<input type="radio" name="confirmed" value="delete"/> Uninstall <br />
<input type="radio" name="confirmed" value="no_delete" CHECKED/> Don't uninstall <br />
<input type="submit" value="Continue">
</form>
</body></html>
<?php } else {
   header( 'Location: ./prefs.php' ) ;
} ?>
