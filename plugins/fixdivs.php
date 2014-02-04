<?php 

fof_add_item_filter('fof_fixdivs');

function fof_fixdivs($item) {
	$text = $item['item_content'];
	$text = str_ireplace('<div"', '<div "', $text);
	$text = str_ireplace('<div ...', '', $text);
	$item['item_content'] = $text;
	return $item;
}
?>
