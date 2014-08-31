<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * items.php - displays right hand side "frame"
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
include_once('fof-render.php');

list($feed,$what,$when,$which,$how,$howmany,$order,$search) = fof_safe_parse_item_constraints($_GET);

//prepare the page title
$title = 'feed on feeds';
if ($when != ''){
	$title .= " - $when" ;
}
if ($feed > 0) {
	$r = fof_db_get_feed_by_id($feed);
	$title .= " - {$r['feed_title']}";
}
$title .= " - $what items";

if ($search != '') {
	$title .= " - <a href=\"javascript:toggle_highlight()\">matching <i class=\"highlight\">$search</i></a>";
}

//fetch the items from the db
$items = fof_get_items(fof_current_user(), $feed, $what, $when, $which, $howmany, $order, $search);

//prepare the navigation links
$next = $which + $howmany;
$prev = $which - $howmany;

$navParts = array();
if ($prev >= 0) {
	$navParts[] = "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$prev&amp;howmany=$howmany\">[&laquo; previous $howmany]</a> ";
}
if (count($items) == $howmany){
	$navParts[] = "<a href=\".?feed=$feed&amp;what=$what&amp;when=$when&amp;how=paged&amp;which=$next&amp;howmany=$howmany\">[next $howmany &raquo; ]</a> ";
}

$navLink = implode(' | ', $navParts);
?>
<br style="clear: both"><br />

<center><p><?php echo $title; ?></p> 
<?php echo $navLink; ?></center>

<ul id="item-display-controls" class="inline-list">
	<li class="orderby"><?php
	$search_clause = ($search == '' ? '' : "search=$search&amp;");
	echo ($order == 'desc') ? '[new to old]' : '<a href=".?'.$search_clause."feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=desc\">[new to old]</a>" ;
	
	?></li>
	<li class="orderby"><?php

	echo ($order == 'asc') ? '[old to new]' : '<a href=".?'.$search_clause."feed=$feed&amp;what=$what&amp;when=$when&amp;how=$how&amp;howmany=$howmany&amp;order=asc\">[old to new]</a>" ;
	
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
$first = true;

foreach($items as $row)
{
	$item_id = $row['item_id'];
	if($first) print "<script>firstItem = 'i$item_id'; </script>";
	$first = false;
	print '<div class="item shown" id="i' . $item_id . '"  onclick="return itemClicked(event)">';
	fof_render_item($row);
	print '</div>';
}

if(count($items) == 0)
{
	echo "<p><i>No items found.</i></p>";
}

?>
		</form>
        
        <div id="end-of-items"><center><?php echo $navLink; ?></center></div>

<script>itemElements = $$('.item');</script>
