<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-render.php - contains function used to render a single item
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

// From Brian Suda @ http://suda.co.uk/projects/SEHL/

function do_highlight($full_body, $q, $class){
	/* seperate tags and data from the HTML file INCLUDING comments, avoiding HTML in the comments */
	$pat = '/((<[^!][\/]*?[^<>]*?>)([^<]*))|((<!--[ \r\n\t]*)(.*)[ \r\n\t]*-->([^<]*))/si';
	preg_match_all($pat,$full_body,$tag_matches);

	/* loop through and highlight $q value in data and recombine with tags */
	for ($i=0; $i< count($tag_matches[0]); $i++) {
		/* ignore all text within these tags */
		if (
			(preg_match('/<!/i', $tag_matches[0][$i])) or 
			(preg_match('/<textarea/i', $tag_matches[2][$i])) or 
			(preg_match('/<script/i', $tag_matches[2][$i]))
		){
			/* array[0] is everything the REGEX found */
			$full_body_hl .= $tag_matches[0][$i];
		} else {
			$full_body_hl .= $tag_matches[2][$i];

			/* the slash-i is for case-insensitive and the slash-b's are for word boundries */

			/* this one ALMOST works, except if the string is at the start or end of a string*/
			$holder = preg_replace('/(.*?)(\W)('.preg_quote($q).')(\W)(.*?)/iu',"\$1\$2<span class=\"$class\">\$3</span>\$4\$5",' '.$tag_matches[3][$i].' ');
			$full_body_hl .= substr($holder,1,(strlen($holder)-2));
		}
	}
	/* return tagged text */
	return $full_body_hl;
}


function fof_render_item($item){
    $items = true;

	list($feed_link,
	 $feed_title,
	  $feed_image,
	   $feed_description,
	    $item_link,
	     $item_id,
	      $item_title,
	      $item_content,
	       $item_published) = fof_escape_item_info($item);

	$prefs = fof_prefs();
	$offset = $prefs['tzoffset'];

	if(!$item_title) $item_title = "[no title]";
	
	if($_GET['search'])
	{
		$item_content = do_highlight("<span>$item_content</span>", $_GET['search'], "highlight");
		$item_title = do_highlight("<span>$item_title</span>", $_GET['search'], "highlight");
	}
	    
    $tags = array_map(function($x){return htmlspecialchars($x, ENT_QUOTES);}, $item['tags']);
	$star = in_array('star', $tags);
	$star_image = $star ? 'image/star-on.gif' : 'image/star-off.gif';
		
	$unread = in_array('unread', $tags);
?>

<div class="header">

	<span class="controls">
		<a class="uparrow" href="javascript:hide_body('<?php echo $item_id ?>')">&uarr;</a>
		<a class='downarrow' href="javascript:show_body('<?php echo $item_id ?>')">&darr;</a>
		<input
			type="checkbox"
			name="c<?php echo $item_id ?>"
			id="c<?php echo $item_id ?>"
			value="checked"
			ondblclick="flag_upto('c<?php echo $item_id?>');"
            onclick="return checkbox(event);"
			title="shift-click or double-click to flag all items up to this one"
		/>
	</span>
	
	<h1 <?php if($unread) echo "class='unread-item'" ?> >
		<img
			height="16"
			width="16"
			src="<?php echo $star_image ?>"
			id="fav<?php echo $item_id ?>"
			onclick="return toggle_favorite('<?php echo $item_id ?>')"
		/>
		<script>
			document.getElementById("fav<?php echo $item_id ?>").star = <?php echo $star ? 'true' : 'false'; ?>;
		</script>
		<a href="<?php echo $item_link ?>" <?php if ($prefs['newtabs']) echo 'target="_blank"'?> >
			<?php echo $item_title ?>
		</a>
	</h1>
	
	<span class="tags">

<?php
	if($tags)
	{
		foreach($tags as $tag)
		{
			if($tag == "unread" || $tag == "star") continue;
?>
		<a href="?what=<?php echo $tag ?>"><?php echo $tag ?></a>
		<a href="<?php echo $tag?>" onclick="return remove_tag('<?php echo $item_id ?>', '<?php echo $tag?>', '<?php echo fof_compute_CSRF_challenge();?>');">[x]</a>
<?php
		}
    }
?>

		<a
			href=""
			onclick="
					var textbox = document.getElementById('addtag<?php echo $item_id ?>');
					if (this.innerHTML == 'Cancel'){
						this.innerHTML = 'add tag';
						this.style.display = '';
						textbox.style.display = 'none';
					} else {
						textbox.style.display = '';
					 	this.innerHTML = 'Cancel';
					 }
					 return false;">
			add tag
		</a>

		<div id="addtag<?php echo $item_id ?>" style="display: none !important">
			<input
				onfocus="this.value=''"
				onkeypress="if(event.keyCode == 13) add_tag('<?php echo $item_id ?>', document.getElementById('tag<?php echo $item_id ?>').value, '<?php echo fof_compute_CSRF_challenge();?>');"
				type="text"
				id="tag<?php echo $item_id ?>"
				size="12"
				value="enter tag here"
			>
			<input
				type="button"
				name="add tag"
				value="tag"
				onclick="add_tag('<?php echo $item_id ?>', document.getElementById('tag<?php echo $item_id ?>').value, '<?php echo fof_compute_CSRF_challenge();?>');"
			>
		</div>

    </span>
    
    <span class="dash"> - </span>
    
    

    <?php $prefs = fof_prefs(); if($feed_image && $prefs['favicons']) { ?>
    <a href="<?php echo $feed_link ?>" title="<?php echo $feed_description ?>"><img src="<?php echo $feed_image ?>" height="16" width="16" border="0" /></a>
    <?php } ?>
    <a href="<?php echo $feed_link ?>" title="<?php echo $feed_description ?>"><?php echo $feed_title ?></a>
 

	<span class="meta">on <?php echo $item_published ?></span>

	</div>


	<div class="body"><?php echo $item_content ?></div>

	<?php
	//for long items, display control links at bottom
	//TODO: get a better way of working out the item height
	if (strlen($item_content) > 3000) { ?>
		<div class="header">
		<a onclick="document.getElementById('c<?php echo $item_id;?>').checked = true; return false;" href="">flag this item</a> | 
		<a onclick="flag_upto('c<?php echo $item_id;?>'); return false;" href="">flag all items above</a>
		</div>
	<?php } ?>

	<?php
	$widgets = fof_get_widgets($item);
	if($widgets) { ?>
		<div class="clearer"></div>

		<div class="widgets">

		<?php foreach($widgets as $widget) {
        	echo "<span class='widget'>$widget</span> ";
    	} ?>

		</div>

	<?php } ?>

<?php } ?>
