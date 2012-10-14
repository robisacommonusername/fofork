<?php 

fof_add_tag_prefilter('fof_autotag', 'fof_autotag');
fof_add_pref('Automatically tag these keywords', 'plugin_autotag_tags', 'string', function ($x){return (is_string($x) && (strlen($x) < 200) && (htmlspecialchars($x, ENT_QUOTES) == $x));});

function fof_autotag($link, $title, $content)
{
	$tags = array();
	
    $prefs = fof_prefs();
    $autotag = $prefs['plugin_autotag_tags'];

	if($autotag)
	{
		$shebang = strip_tags($title . " " . $content);
	
		foreach(explode(" ", $autotag) as $tag)
			if(preg_match("/\b" . preg_quote($tag) . "\b/i", $shebang))
				$tags[] = $tag;
	}

	return $tags;
}
?>
