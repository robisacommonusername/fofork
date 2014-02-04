<?php 

fof_add_item_filter('fof_plain');
fof_add_pref('Strip (most) markup from items', 'plugin_plain_enable', 'boolean', 'is_bool');

function fof_plain($item) {
	$text = $item['item_content'];
    $prefs = fof_prefs();
    $enable = $prefs['plugin_plain_enable'];
    
    if($enable) {
	    $text = strip_tags($text, "<a><b><i><blockquote>");
	    $item['item_content'] = $text;
	}
    return $item;
}
?>
