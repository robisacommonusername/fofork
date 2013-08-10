<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * prefs.php - display and change preferences
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
$prefs =& FoF_Prefs::instance();
$CSRF_hash = $_POST['CSRF_hash'];
if(fof_is_admin() && isset($_POST['adminprefs']) && fof_authenticate_CSRF_challenge($CSRF_hash))
{
	$prefs->setAdmin('purge', intval($_POST['purge']));
	$prefs->setAdmin('manualtimeout', intval($_POST['manualtimeout']));
	$prefs->setAdmin('autotimeout', intval($_POST['autotimeout']));
	$prefs->setAdmin('logging', $_POST['logging'] ? True : False);
	$prefs->setAdmin('max_items_per_request', intval($_POST['max_items_per_request']));
	$prefs->save();
    	
	$message .= ' Saved admin prefs.';
    
    if($prefs->get('logging') && !@fopen("fof.log", 'a'))
    {
        $message .= ' Warning: could not write to log file!';
    }
}

if(isset($_POST['tagfeed']) && fof_authenticate_CSRF_challenge($CSRF_hash))
{
    $tags = htmlspecialchars($_POST['tag'], ENT_QUOTES);
    $feed_id = intval($_POST['feed_id']);
    $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
    
    foreach(explode(" ", $tags) as $tag)
    {
        fof_tag_feed(fof_current_user(), $feed_id, $tag);
        $message .= htmlspecialchars(" Tagged '$title' as $tag.");
    }
}

if(isset($_GET['untagfeed']) && fof_authenticate_CSRF_challenge($_GET['CSRF_hash']))
{
    $feed_id = intval($_GET['untagfeed']);
    $tags = htmlspecialchars($_GET['tag'], ENT_QUOTES);
    $title = htmlspecialchars($_GET['title'], ENT_QUOTES);
	
    foreach(explode(" ", $tags) as $tag)
    {
        fof_untag_feed(fof_current_user(), $feed_id, $tag);
        $message .= htmlspecialchars(" Dropped $tag from '$title'.");
    }
}

if(isset($_POST['prefs']) && fof_authenticate_CSRF_challenge($CSRF_hash))
{
	$prefs->set('favicons', $_POST['favicons'] ? True : False);
	$prefs->set('keyboard', $_POST['keyboard'] ? True : False);
	$prefs->set('newtabs', $_POST['newtabs'] ? True : False);
	$prefs->set('tzoffset', intval($_POST['tzoffset']));
	$prefs->set('howmany', intval($_POST['howmany']));
	$prefs->set('order', $_POST['order'] == 'asc' ? 'asc' : 'desc');
	$prefs->set('sharing', $_POST['sharing'] == 'no' ? 'no' : ($_POST['sharing'] == 'all' ? 'all' : 'tagged'));
	$prefs->set('sharedname', htmlspecialchars($_POST['sharedname']));
	$prefs->set('sharedurl', htmlspecialchars($_POST['sharedurl']));
	$prefs->save(fof_current_user());
    
    if($_POST['password'] && ($_POST['password'] == $_POST['password2']))
    {
    	if (fof_db_authenticate(fof_username(), $_POST['exist_pwd'])){
        	fof_db_change_password(fof_username(), $_POST['password']);
        	$message = 'Updated password.';
        } else {
        	$message = 'Current password was not entered correctly';
        }
    }
    else if($_POST['password'] || $_POST['password2'])
    {
        $message = "Passwords do not match!";
    }
	
	$message .= ' Saved prefs.';
}

if(isset($_POST['plugins']) && fof_authenticate_CSRF_challenge($CSRF_hash))
{
    foreach(fof_get_plugin_prefs() as $plugin_pref)
    {
    	list($name, $key, $type, $sanitiser) = $plugin_pref;
		if ($sanitiser($_POST[$key])){
        	$prefs->set($key, $_POST[$key]);
        }
    }
    
    $plugins = array();
    $dirlist = opendir(FOF_DIR . DIRECTORY_SEPARATOR . 'plugins');
    while($file=readdir($dirlist))
    {
        if(preg_match('/\.php$/',$file))
        {
           $plugins[] = substr($file, 0, -4);
        }
    }

    closedir();
        
    foreach($plugins as $plugin)
    {
        $prefs->set("plugin_" . $plugin, $_POST[$plugin] == "on");
    }

	$prefs->save(fof_current_user());

	$message .= ' Saved plugin prefs.';
}

if(fof_is_admin() && isset($_POST['changepassword']) && fof_authenticate_CSRF_challenge($CSRF_hash)) 
{
	if (fof_db_authenticate(fof_username(), $_POST['admin_password'])){
    	if($_POST['password'] != $_POST['password2'])
    	{
        	$message = "Passwords do not match!";
    	}
    	else
    	{
        	$username = $_POST['username'];
        	$password = $_POST['password'];
        	fof_db_change_password($username, $password);
        	$message = htmlspecialchars("Changed password for $username.");
    	}
    } else {
    	$message = 'Please enter a valid admin password';
    }
}

if(fof_is_admin() && isset($_POST['adduser']) && $_POST['username'] && $_POST['password'] && fof_authenticate_CSRF_challenge($CSRF_hash)) 
{
	if (fof_db_authenticate(fof_username(), $_POST['admin_password'])){
    	$username = htmlspecialchars($_POST['username']);
    	if (preg_match('/^[a-zA-Z0-9]{1,32}$/',$username)){
    		$password = $_POST['password'];
			$message = fof_db_add_user($username, $password) ? "User '$username' added." : "A user named '$username' already exists.  Please try again";
		} else {
			$message = 'Invalid username entered';
		}
	} else {
		$message = 'Please enter a valid admin password';
	}
	
}


if(fof_is_admin() && isset($_POST['deleteuser']) && $_POST['username'] && fof_authenticate_CSRF_challenge($CSRF_hash))
{
	if (fof_db_authenticate(fof_username(), $_POST['admin_password'])){
		$username = $_POST['username'];
		fof_db_delete_user($username);
		$message = htmlspecialchars("User '$username' deleted.");
	} else {
		$message = 'Please enter a valid admin password';
	}
}

include("header.php");
$challenge = fof_compute_CSRF_challenge();
?>

<?php if(isset($message)) {?>

<br><font color="red"><?php echo $message ?></font><br>

<?php } ?>
<br><h1>Feed on Feeds - Preferences</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
<input type="hidden" name="CSRF_hash" value="<?php echo $challenge;?>">
Default display order: <select name="order"><option value=desc>new to old</option><option value=asc <?php if($prefs->get('order') == "asc") echo "selected";?>>old to new</option></select><br><br>
Number of items in paged displays: <input type="string" name="howmany" value="<?php echo intval($prefs->get('howmany')) ?>"><br /><br />
Display custom feed favicons? <input type="checkbox" name="favicons" <?php if($prefs->get('favicons')) echo 'CHECKED';?> ><br /><br />
Use keyboard shortcuts? <input type="checkbox" name="keyboard" <?php if($prefs->get('keyboard')) echo 'CHECKED';?> ><br /><br />
Open stories in new tab? <input type="checkbox" name="newtabs" <?php if($prefs->get('newtabs')) echo 'CHECKED';?> ><br /><br />
Time offset in hours: <input size=3 type=string name=tzoffset value="<?php echo intval($prefs->get('tzoffset'))?>"> (UTC time: <?php echo gmdate("Y-n-d g:ia") ?>, local time: <?php echo gmdate("Y-n-d g:ia", time() + intval($prefs->get("tzoffset"))*60*60) ?>)<br><br>
<table border=0 cellspacing=0 cellpadding=2><tr><td>Current password:</td><td><input type=password name=exist_pwd><tr><td>New password:</td><td><input type=password name=password> (leave blank to not change)</td></tr>
<tr><td>Repeat new password:</td><td><input type=password name=password2></td></tr></table>
<br>

Share 
<select name="sharing">
<option value=no>no</option>
<option value=all <?php if($prefs->get('sharing') == "all") echo "selected";?>>all</option>
<option value=tagged <?php if($prefs->get('sharing') == "tagged") echo "selected";?>>tagged as "shared"</option>
</select>
items.
<?php if($prefs->get('sharing') != "no") echo " <small><i>(your shared page is <a href=\"./shared.php?user=$fof_user_id\">here</a>)</i></small>";?><br><br>
Name to be shown on shared page: <input type=string name=sharedname value="<?php echo htmlspecialchars($prefs->get('sharedname'))?>"><br><br>
URL to be linked on shared page: <input type=string name=sharedurl value="<?php echo htmlspecialchars($prefs->get('sharedurl'))?>">
<br><br>

<input type=submit name=prefs value="Save Preferences">
</form>

<br><h1>Feed on Feeds - Plugin Preferences</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
<input type="hidden" name="CSRF_hash" value="<?php echo $challenge;?>">
<?php
    $plugins = array();
    $dirlist = opendir(FOF_DIR . DIRECTORY_SEPARATOR . 'plugins');
    while($file=readdir($dirlist))
    {
    	fof_log("considering " . $file);
        if(preg_match('/\.php$/',$file))
        {
           $plugins[] = htmlspecialchars(substr($file, 0, -4));
        }
    }

    closedir();

?>

<?php foreach($plugins as $plugin) { ?>
<input type="checkbox" name="<?php echo $plugin ?>" <?php if($prefs->get("plugin_" . $plugin)) echo 'checked'; ?>> Enable plugin <tt><?php echo $plugin?></tt>?<br>
<?php } ?>

<br>
<?php foreach(fof_get_plugin_prefs() as $plugin_pref) { $name = $plugin_pref[0]; $key = $plugin_pref[1]; $type = $plugin_pref[2]; ?>
<?php echo $name ?>: 

<?php if($type == "boolean") { ?>
<input name="<?php echo $key ?>" type="checkbox" <?php if($prefs->get($key)) echo "checked" ?>><br>
<?php } else { ?>
<input name="<?php echo $key ?>" value="<?php echo $prefs->get($key)?>"><br>
<?php } } ?>
<br>
<input type=submit name=plugins value="Save Plugin Preferences">
</form>

    
    
<br><h1>Feed on Feeds - Feeds and Tags</h1>
<div style="border: 1px solid black; margin: 10px; padding: 10px; font-size: 12px; font-family: verdana, arial;">
<table cellpadding=3 cellspacing=0>
<?php
foreach($feeds as $feed)
{
	list($id,
		$url,
		$title,
		$link,
		$tags,
		$feed_image) = fof_escape_feed_info($feed);
   
   if(++$t % 2)
   {
      print "<tr class=\"odd-row\">";
   }
   else
   {
      print "<tr>";
   }

   if($feed_image && $prefs->get('favicons'))
   {
	   print "<td><a href=\"$url\" title=\"feed\"><img src=\"$feed_image\" width=\"16\" height=\"16\" border=\"0\" /></a></td>";
   }
   else
   {
	   print "<td><a href=\"$url\" title=\"feed\"><img src='image/feed-icon.png' width='16' height='16' border='0' /></a></td>";
   }
    
   print "<td><a href=\"$link\" title=\"home page\">$title</a></td>";
   
   print "<td align=right>";
   
   if($tags){
   		$challenge = fof_compute_CSRF_challenge();
   		foreach($tags as $tag)
       {
           $utag = urlencode($tag);
           $utitle = urlencode($title);
           print "$tag <a href=\"prefs.php?untagfeed=$id&tag=$utag&title=$utitle&CSRF_hash=$challenge\">[x]</a> ";
       }
   }
   
   print "</td>";
   print "<td><form method=post action=prefs.php><input type=\"hidden\" name=\"CSRF_hash\" value=\"$challenge\"><input type=\"hidden\" name=\"title\" value=\"$title\"><input type=hidden name=feed_id value=$id><input type=string name=tag> <input type=submit name=tagfeed value='Tag Feed'> <small><i>(separate tags with spaces)</i></small></form></td></tr>";
}
?>
</table>
</div>


<?php if(fof_is_admin()) { ?>

<br><h1>Feed on Feeds - Admin Options</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
<input type="hidden" name="CSRF_hash" value="<?php echo $challenge;?>">
Enable logging? <input type="checkbox" name="logging" <?php if($prefs->getAdmin('logging')) echo "checked"; ?>><br><br>
Purge read items after <input size="4" type="string" name="purge" value="<?php echo intval($prefs->getAdmin('purge'))?>"> days (leave blank to never purge)<br><br>
Allow automatic feed updates every <input size="4" type="string" name="autotimeout" value="<?php echo intval($prefs->getAdmin('autotimeout'))?>"> minutes<br><br>
Allow manual feed updates every <input size="4" type="string" name="manualtimeout" value="<?php echo intval($prefs->getAdmin('manualtimeout'))?>"> minutes<br><br>
Maximum number of items per page request <input size="4" type="string" name="max_items_per_request" value="<?php echo intval($prefs->getAdmin('max_items_per_request'))?>"><br><br>
<input type="submit" name="adminprefs" value="Save Options">
</form>

<br><h1>Add User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;">
<input type="hidden" name="CSRF_hash" value="<?php echo $challenge;?>">
<table cellspacing="0" cellpadding="2">
<tr><td>Admin password:</td><td><input type="password" name="admin_password"></td></tr>
<tr><td>Username: </td><td><input type="string" name="username"></td></tr>
<tr><td>Password: </td><td><input type="string" name="password"></td></tr>
</table>
<input type=submit name=adduser value="Add user">
</form>

<?php
	$result = fof_query_log("select user_name from $FOF_USER_TABLE where user_id > 1", null);
	
	while($row = fof_db_get_row($result))
	{
		$username = htmlspecialchars($row['user_name']);
		$delete_options .= "<option value=$username>$username</option>";
	}

    if(isset($delete_options))
    {
?>

<br><h1>Delete User</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Delete User - Are you sure?')">
<input type="hidden" name="CSRF_hash" value="<?php echo $challenge;?>">
<table border=0 cellspacing=0 cellpadding=10><tr><td>Enter Admin Password:</td><td>Select user to delete:</td></tr>
<tr><td><input type="password" name="admin_password"></td><td>
<select name="username"><?php echo $delete_options ?></select>
<input type="submit" name="deleteuser" value="Delete user"></td></tr></table>
</form>

<br><h1>Change User's Password</h1>
<form method="post" action="prefs.php" style="border: 1px solid black; margin: 10px; padding: 10px;" onsubmit="return confirm('Change Password - Are you sure?')">
<input type="hidden" name="CSRF_hash" value="<?php echo $challenge;?>">
<table border=0 cellspacing=0 cellpadding=2>
<tr><td>Select user:</td><td><select name="username"><?php echo $delete_options ?></select></td></tr>
<tr><td>Admin password:</td><td><input type="password" name="admin_password"></td></tr>
<tr><td>New password:</td><td><input type="password" name="password"></td></tr>
<tr><td>Repeat new password:</td><td><input type="password" name="password2"></td></tr></table>
<input type="submit" name="changepassword" value="Change"><br>
</form>

<?php } ?>

<br>
<center><b><a href="logs.php">View Feed on Feeds log file</a></b></center><br />
<center><b><a href="uninstall.php">Uninstall Feed on Feeds</a></b></center><br />

<?php } ?>

<?php include("footer.php") ?>
