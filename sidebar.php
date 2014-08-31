<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * sidebar.php - sidebar for all pages
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

fof_set_content_type();
$CSRF_hash = fof_compute_CSRF_challenge();

?>
<img id="throbber" src="image/throbber.gif" align="left" style="position: fixed; left: 0; top: 0; display: none;">

<center id="welcome">Welcome <b><?php echo $_SESSION['user_name']; ?></b>!<br /> <a href="prefs.php">prefs</a> | <a href="logout.php">log out</a> | <a href="logout.php?everywhere=yes">log out everywhere</a> | <a href="<?php echo FOFORK_WEBSITE; ?>">about</a></center>
<br>
<center><a href="add.php"><b>Add Feeds</b></a> / <a href="update.php"><b>Update Feeds</b></a></center>

<ul id="nav">

<?php

//Required for ordering the feeds in the sidebar
$feed_order = $fof_prefs_obj->get('feed_order');
$allowedOrders = array('feed_age', 'max_date', 'feed_unread', 'feed_url', 'feed_title');
$feed_order = in_array($feed_order, $allowedOrders) ? $feed_order : 'feed_age' ;
$feed_direction = $fof_prefs_obj->get('feed_direction') == 'asc' ? 'asc' : 'desc';

//these parameters are used in the case of a search, so that searches can
//preserve the what, when, which, etc. i.e. this allows searching within
//the currently displayed feed, etc
if(!isset($_GET['what'])) {
	$what = 'all';
} else {
	$what = htmlspecialchars($_GET['what'], ENT_QUOTES);
}
$when = htmlspecialchars($_GET['when'], ENT_QUOTES);
$direction = $_GET['order'] == 'asc' ? 'asc' : 'desc';
$search = htmlspecialchars($_GET['search'], ENT_QUOTES);

//get feeds for display in sidebar
$feeds = fof_get_feeds(fof_current_user(), $feed_order, $feed_direction);

foreach($feeds as $row)
{
    $n++;
    $unread += $row['feed_unread'];
    $starred += $row['feed_starred'];
    $total += $row['feed_items'];
}

if($unread)
{
    echo "<script>document.title = 'Feed on Feeds ($unread)';</script>";
}
else
{
    echo "<script>document.title = 'Feed on Feeds';</script>";
}

echo "<script>starred = $starred;</script>";

?>
        
<li <?php if($what == "unread") echo "style='background: #ddd'" ?> ><a href=".?what=unread"><font color=red><b>Unread <?php if($unread) echo "($unread)" ?></b></font></a></li>
<li <?php if($what == "star") echo "style='background: #ddd'" ?> ><a href=".?what=star"><img src="image/star-on.gif" border="0" height="10" width="10"> Starred <span id="starredcount"><?php if($starred) echo "($starred)" ?></span></a></li>
<li <?php if($what == "all" && isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all&when=today">&lt; Today</a></li>
<li <?php if($what == "all" && !isset($when)) echo "style='background: #ddd'" ?> ><a href=".?what=all&how=paged">All Items <?php if($total) echo "($total)" ?></a></li>
<li <?php if(isset($search)) echo "style='background: #ddd'" ?> ><a href="javascript:Element.toggle('search'); Field.focus('searchfield');void(0);">Search</a>
<form action="." id="search" <?php if(!isset($search)) echo 'style="display: none"';?>>
<input id="searchfield" name="search" value="<?php echo $search ?>">
<?php if ($what != '') {
	echo '<input type="hidden" name="what" value="'.$what.'">';
}?>
<input type="hidden" name="order" value="<?php echo $direction ?>">
<?php if ($what == '' && $when != '') {
	echo '<input type="hidden" name="what" value="'.$when.'">';
} ?>
</form>
</li>
</ul>

<?php

$tags = fof_get_tags(fof_current_user());

$n = 0;

foreach($tags as $tag)
{
    $tag_id = $tag['tag_id'];
    if($tag_id == 1 || $tag_id == 2) continue;
    $n++;
}

if($n)
{
?>

<div id="tags">

<table cellspacing="0" cellpadding="1" border="0" id="taglist">

<tr class="heading">
<td><span class="unread">#</span></td><td>tag name</td><td>untag all items</td>
</tr>

<?php
foreach($tags as $tag)
{   
   $tag_name = htmlspecialchars($tag['tag_name'], ENT_QUOTES);
   $tag_id = $tag['tag_id'];
   $count = $tag['count'];
   $unread = $tag['unread'];
 
   if($tag_id == 1 || $tag_id == 2) continue;

   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   print "<td>";
   $tagNameEncoded = urlencode($tag_name);
   $tagNameEscaped = addslashes($tag_name);
   if($unread) print "<a class='unread' href=\".?what=$tagNameEncoded+unread\">$unread</a>/";
   print "<a href=\".?what=$tagNameEncoded\">$count</a></td>";
   print "<td><b><a href=\".?what=$tagNameEncoded\">$tag_name</a></b></td>";
   print "<td><a href=\"#\" title=\"untag all items\" onclick=\"if(confirm('Untag all [$tagNameEscaped] items --are you SURE?')) { delete_tag('$tagNameEscaped', '$CSRF_hash'); return false; }  else { return false; }\">[x]</a></td>";

   print "</tr>";
}


?>

</table>

</div>

<br>

<?php } ?>


<div id="feeds">

<div id="feedlist">

<table cellspacing="0" cellpadding="1" border="0">

<tr class="heading">

<?php
$title = array();
$title['feed_age'] = 'sort by last update time';
$title['max_date'] = 'sort by last new item';
$title['feed_unread'] = 'sort by number of unread items';
$title['feed_url'] = 'sort by feed URL';
$title['feed_title'] = 'sort by feed title';

$name = array();
$name["feed_age"] = "age";
$name["max_date"] = "latest";
$name["feed_unread"] = "#";
$name["feed_url"] = "feed";
$name["feed_title"] = "title";

foreach ($allowedOrders as $col)
{
	$challenge = fof_compute_CSRF_challenge();
    if($col == $feed_order)
    {
        $url = "return change_feed_order('$col', '" . ($feed_direction == "asc" ? "desc" : "asc") . "', '$challenge')";
    }
    else
    {
        $url = "return change_feed_order('$col', 'asc', '$challenge')";
    }
    
    echo "<td><nobr><a href='#' title='$title[$col]' onclick=\"$url\">";
    
    if($col == "feed_unread")
    {
        echo "<span class=\"unread\">#</span>";
    }
    else
    {
        echo $name[$col];
    }
    
    if($col == $feed_order)
    {
        echo ($feed_direction == "asc") ? "&darr;" : "&uarr;";
    }
    
    echo "</a></nobr></td>";
}

?>

<td></td>
</tr>

<?php

foreach($feeds as $feed)
{
	list($id,
		$url,
		$title,
		$link,
		$tags,
		$feed_image,
		$description,
		$age,
		$unread,
		$starred,
		$items) = fof_escape_feed_info($feed);
   $agestr = $feed['agestr'];
   $agestrabbr = $feed['agestrabbr'];
   $lateststr = $feed['lateststr'];
   $lateststrabbr = $feed['lateststrabbr'];


   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   $u = ".?feed=$id";
   $u2 = ".?feed=$id&amp;what=all&amp;how=paged";

   print "<td><span title=\"$agestr\" id=\"${id}-agestr\">$agestrabbr</span></td>";

   print "<td><span title=\"$lateststr\" id=\"${id}-lateststr\">$lateststrabbr</span></td>";

   print "<td class=\"nowrap\" id=\"${id}-items\">";

   if($unread)
   {
      print "<a class=\"unread\" title=\"new items\" href=\"$u\">$unread</a>/";
   }

   print "<a href=\"$u2\" title=\"all items\">$items</a>";

   print "</td>";

	print "<td align='center'>";
	if($feed_image && $fof_prefs_obj->get('favicons'))
	{
	   print "<a href=\"$url\" title=\"feed\"><img src='" . $feed_image . "' width='16' height='16' border='0' /></a>";
	}
	else
	{
	   print "<a href=\"$url\" title=\"feed\"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a>";
	}
	print "</td>";
	
	print "<td>";
   
	//stitle will be injected into page in the context of a javascript string.
	//it's thus necessary to remove any newline characters that the title
	//may contain, as these will result in the javascript parser producing
	//unterminated string errors.  ie
	//var a = 'This is
	// a string';
	// is not valid javascript
	$stitle = htmlspecialchars(
		str_replace(
			array("\r","\n"),
			array('',''),
			$title),
		ENT_QUOTES);
	
	print "<a href=\"$link\" title=\"home page\"><b>$stitle</b></a></td>";
	
	print "<td><nobr>";
	
	print "<a href=\"update.php?feed=$id\" title=\"update\">u</a>";
	print " <a href=\"#\" title=\"mark all read\" onclick=\"if(confirm('Mark all [$stitle] items as read --are you SURE?')) { mark_feed_read($id); return false; }  else { return false; }\">m</a>";
	print " <a  title=\"delete\" onclick=\"if(confirm('Delete feed [$stitle] - are you SURE?')) {delete_feed($id, '$CSRF_hash'); return false;} else {return false;}\" href=\"#\">d</a>";
	
	print "</nobr></td>";
	
	print "</tr>";
}

?>

</table>

</div>

</div>

