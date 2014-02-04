<?php
fof_add_item_filter('fof_fix_double_escape_idiocy');
function fof_fix_double_escape_idiocy($item){
	$title = $item['item_title'];
	$fixed_title = preg_replace('/&amp;(lt|gt|quot|amp|#039|pound|#163|mdash|#151|euro);/','&$1;', $title);
	$item['item_title'] = $fixed_title;
	return $item;
}
?>
