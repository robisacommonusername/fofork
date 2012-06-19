<?php 

fof_add_item_widget('fof_wordpress');
fof_add_pref('WordPress URL', 'plugin_wordpressurl');

function fof_wordpress($item)
{
    $prefs = fof_prefs();
    $wordpress = $prefs['plugin_wordpressurl'];
    
    if(!$wordpress) return false;
    
   $url = urlencode(html_entity_decode($item['item_link']));
   $title = urlencode($item['item_title']);
   $text = urlencode('<blockquote>' . $item['item_content'] . '</blockquote>');

   $link = "$wordpress/wp-admin/post-new.php?text=$text&popupurl=$url&popuptitle=$title";
   #XSS here
   return "<a href='$link'><img src='plugins/wordpress.png' height=12 width=12 border=0 /></a> <a href='$link'>blog it</a>";
}
?>
