<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * add-tag.php - adds (or removes) a tag to an item
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

include_once("fof-main.php");

$tags = htmlspecialchars($_POST['tag'], ENT_QUOTES);
$item = intval($_POST['item']);
$remove = $_POST['remove'];
$CSRF_hash = $_POST['CSRF_hash'];
if (!fof_authenticate_CSRF_challenge($CSRF_hash)){
	die("CSRF detected");
}

foreach(explode(" ", $tags) as $tag)
{
    if($remove == 'true')
    {
        fof_untag_item(fof_current_user(), $item, $tag);
    }
    else
    {
        fof_tag_item(fof_current_user(), $item, $tag);
    }
}
?>
