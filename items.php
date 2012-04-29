<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * items.php - displays right hand side "frame"
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");
include_once("fof-render.php");

if($_GET['how'] == 'paged' && !isset($_GET['which'])){
	$which = 0;
} else {
	$which = intval($_GET['which']);
}

if(!isset($_GET['what'])) {
    $what = "unread";
} else {
    $what = htmlspecialchars($_GET['what']);
}

if(!isset($_GET['order'])) {
	$order = $fof_prefs_obj->get("order") == 'asc' ? 'asc' : 'desc';
} else {
	$order = $_GET['order'] == 'asc' ? 'asc' : 'desc';
}

$how = htmlspecialchars($_GET['how']);
$feed = intval($_GET['feed']);
$when = htmlspecialchars($_GET['when']);
$howmany = intval($_GET['howmany']);
if (!$howmany){
	$howmany = intval($fof_prefs_obj->get("howmany"));
}
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : "";

$title = fof_view_title($feed, $what, $when, $which, $howmany, $search);
//$noedit = $_GET['noedit'];

?>

<ul id="item-display-controls-spacer" class="inline-list">
	<li class="orderby">[new to old]</li>
	<li class="orderby">[old to new]</li>
	<li><a href="javascript:flag_all();mark_read()"><strong>Mark all read</strong></a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_all()">Show all</a></li>
	<li><a href="javascript:hide_all()">Hide all</a></li>
</ul>

<br style="clear: both"><br />

<p><?php echo $title ?></p> 


<ul id="item-display-controls" class="inline-list">
	<li class="orderby"><?php
	
	echo ($order == "desc") ? '[new to old]' : "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=desc\">[new to old]</a>" ;
	
	?></li>
	<li class="orderby"><?php

	echo ($order == "asc") ? '[old to new]' : "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=asc\">[old to new]</a>" ;
	
	?></li>
	<li><a href="javascript:flag_all();mark_read()"><strong>Mark all read</strong></a></li>
	<li><a href="javascript:flag_all()">Flag all</a></li>
	<li><a href="javascript:unflag_all()">Unflag all</a></li>
	<li><a href="javascript:toggle_all()">Toggle all</a></li>
	<li><a href="javascript:mark_read()">Mark flagged read</a></li>
	<li><a href="javascript:mark_unread()">Mark flagged unread</a></li>
	<li><a href="javascript:show_all()">Show all</a></li>
	<li><a href="javascript:hide_all()">Hide all</a></li>
</ul>



<!-- close this form to fix first item! -->

		<form id="itemform" name="items" action="view-action.php" method="post" onSubmit="return false;">
		<input type="hidden" name="action" />
		<input type="hidden" name="return" />

<?php
	$links = fof_get_nav_links($feed, $what, $when, $which, $howmany); #XSS

	if($links)
	{
?>
		<center><?php echo $links?></center>

<?php
	}


$result = fof_get_items(fof_current_user(), $feed, $what, $when, $which, $howmany, $order, $search);

$first = true;

foreach($result as $row)
{
	$item_id = $row['item_id'];
	if($first) print "<script>firstItem = 'i$item_id'; </script>";
	$first = false;
	print '<div class="item shown" id="i' . $item_id . '"  onclick="return itemClicked(event)">';
	fof_render_item($row);
	print '</div>';
}

if(count($result) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

?>
		</form>
        
        <div id="end-of-items"></div>

<script>itemElements = $$('.item');</script>
