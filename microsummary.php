<?php
$fof_no_login = 1;
require("fof-main.php");

if(isset($_SESSION['authenticated'])){
    $unread = fof_db_get_unread_count(fof_current_user());
}

echo "Feed on Feeds";
if($unread) echo " ($unread)";
?>

