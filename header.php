<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * header.php - common header for all pages
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

include_once("fof-main.php");

fof_set_content_type();

if(isset($_COOKIE['fof_sidebar_width']))
{
	$width = intval($_COOKIE['fof_sidebar_width']);
}
else
{
	$width = 250;
}

$unread_count = fof_db_get_unread_count(fof_current_user());

?>
<!DOCTYPE html>
<html>

   <head>
      <title>Feed on Feeds<?php if($unread_count) echo " ($unread_count)";?></title>
      
      <link rel="stylesheet" href="fof.css" media="screen" />

      <link rel="microsummary" href="microsummary.php" />
      
      <script src="prototype/prototype.js" type="text/javascript"></script>
      
      <script src="fof.js" type="text/javascript"></script>
      
      
      <script>
      	document.onmousemove = dragResize;
      	document.onmouseup = completeDrag;
      	<?php if($fof_prefs_obj->get('keyboard')) { ?>
            document.onkeypress = keyboard;
      	<?php } ?>
        isIE = false;
      </script>
      
	  <style>
		#sidebar
		{
			width: <?php echo $width ?>px;
		}

		#handle
		{
			left:<?php echo $width ?>px;
		}
		
		#items
		{
			margin-left: <?php echo $width+20 ?>px;
		}
        
		#item-display-controls { left: <?php echo $width+10 ?>px; }
	  </style>

	<!--[if IE]>
	  <style>
		#sidebar table
		{
			width: <?php echo $width - 20?>px;
		}
	  </style>

	  <script>isIE = true;</script>
	<![endif]-->
		

   </head>
      
  <body class="highlight-on"> <!--onkeypress="keyboard(event)"-->

<div id="sidebar">
 <?php include("sidebar.php") ?>
</div>

<div id="handle" onmousedown="startResize(event)"></div>

<div id="items">
