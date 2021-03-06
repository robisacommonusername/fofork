<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * login.php - username / password entry
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

ob_start();

$fof_no_login = true;

include_once('fof-main.php');

fof_set_content_type();

if(isset($_POST['user_name']) && isset($_POST['user_password']))
{
    if(fof_db_authenticate($_POST['user_name'], $_POST['user_password']))
    {
    	session_regenerate_id(True);
    	if ($_POST['persistent'] == 'True'){
    		fof_place_cookie($_SESSION['user_id']);
    	}
        Header('Location: .');
        exit();
    }
    else
    {
    	session_unset();
    	$failed = true;
    }
}

?>
<!DOCTYPE html>
<html>

   <head>
      <title>Feed on Feeds - Log on</title>
      
      <style>
      body
      {
          font-family: georgia;
          font-size: 16px;
      }
      
      div
      {
          background: #eee;
          border: 1px solid black;
          width: 20em;
          margin: 5em auto;
          padding: 1.5em;
      }
      </style>
   </head>
      
  <body>
<div>
	<form action="login.php" method="POST" style="display: inline">
		<center><a href="http://feedonfeeds.com/" style="font-size: 20px; font-family: georgia;">Feed on Feeds</a></center><br>
		<?php
		if (fof_db_registration_allowed()) { ?>
			<center><a href="register.php">Register Account</a></center><br />
		<?php } ?>
		User name:<br /><input type=string name=user_name style='font-size: 16px'><br /><br />
		Password:<br /><input type=password name=user_password style='font-size: 16px'><br /><br />
		Remember me:  <input type=checkbox name="persistent" value="True"><br />
		<input type=submit value="Log on!" style='font-size: 16px; float: right;'><br />
		<?php if($failed) echo "<br><center><font color=red><b>Incorrect user name or password</b></font></center>"; ?>
	</form>
</div>
  </body>
  
</html>
