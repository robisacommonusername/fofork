<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * delete.php - deletes a feed and all items
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

include("header.php");

if (isset($_POST['confirmed'])){
	$feed_id = intval($_POST['feed_id']);
	$feed = fof_db_get_feed_by_id($feed_id);
	$title = $feed['feed_title'];
	if ($_POST['confirmed'] == 'delete'){
		fof_delete_subscription(fof_current_user(), $feed_id);
		printf('Deleted feed %s', htmlspecialchars($title));
	} else {
		exit;
	}
	
} else {
	$feed_id = intval($_GET['feed']);
	$feed = fof_db_get_feed_by_id($feed_id);
	$title = htmlspecialchars($feed['feed_title']);
	?>
	Are you sure you wish to delete the feed <?php echo $title; ?>? <br />
	<form name="confirmation_form" action="delete.php" method="post" />
	<input type="hidden" name="feed_id" value="<?php echo $feed_id; ?>" />
	<input type="radio" name="confirmed" value="delete" CHECKED /> Delete <br />
	<input type="radio" name="confirmed" value="no_delete" /> Don't delete <br />
	<input type="submit" value="Continue">
	</form>
	<?php
}
include("footer.php"); ?>
