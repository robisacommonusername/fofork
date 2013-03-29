<?php 

fof_add_item_widget('fof_wordpress');
fof_add_pref('WordPress URL', 'plugin_wordpressurl', 'string', function($x) {return (is_string($x) && strlen($x) < 200);});

function fof_wordpress($item)
{
    $prefs = fof_prefs();
    $wordpress = htmlspecialchars($prefs['plugin_wordpressurl']);
    
    if(!$wordpress) return false;
    
   $url = htmlspecialchars($item['item_link']);
   $title = htmlspecialchars($item['item_title']);
   $text = '<blockquote>' . $item['item_content'] . '</blockquote>';

   $link = urlencode("$wordpress/wp-admin/post-new.php?text=$text&popupurl=$url&popuptitle=$title");
   return "<a href=\"$link\"><img src=\"plugins/wordpress.png\" height=12 width=12 border=0 /></a> <a href=\"$link\">blog it</a>";
}
?>
