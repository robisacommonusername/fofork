<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * register.php - allow new users to sign up
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2012-2013 Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */
 
 ob_start();

$fof_no_login = true;

include_once('fof-main.php');
require_once('classes/fof-prefs.php');

if (!fof_db_registration_allowed()){
	die();
}
fof_set_content_type();
$fields = array('username','email','password','password2');

//confirm registration
if (isset($_GET['uid']) && isset($_GET['token'])){
	//confirm registration
	$uid = intval($_GET['uid']);
	$prefs = new FoF_Prefs($uid);
	if ($_GET['token'] === $prefs->get('token')){
		$prefs->set('confirmed', true);
		$prefs->clear('token');
		$prefs->save();
		header('Location: login.php');
		exit();
	} else {
		die();
	}
}

//register a new user
//confirm that all required fields have been set validly,
//and that all honeypot fields are ''
if ($_POST['registered'] == 'true'){
	$fieldCodes = $_SESSION['field_codes'];
	$honeyPots = $_SESSION['honey_pots'];
	foreach ($honeyPots as $h){
		if ($_POST[$h] != ''){
			die();
		}
	}
	//check user data
	$msg = '';
	$allOk = True;
	$password = $_POST[$fieldCodes['password']];
	$password2 = $_POST[$fieldCodes['password2']];
	$username = $_POST[$fieldCodes['username']];
	$email = $_POST[$fieldCodes['email']];
	if (!preg_match('/^[a-zA-Z0-9]{1,32}$/', $username)){
		$msg .= 'Bad username entered <br />';
		$allOk = false;
	}
	if (fof_db_get_user_id($username) !== null){
		$msg .= 'That username is already taken! <br />';
		$allOk = false;
	}
	if ($password != $password2){
		$msg .= 'Passwords do not match! <br />';
		$allOk = False;
	}
	if (!preg_match('/^[^@]+@[^@.]+[.]?[^@]*$/', $email)){
		$msg .= 'Bad email entered <br />';
		$allOk = False;
	}
	//should also check that the email hasn't been used to register
	//another account
	if (fof_db_get_user_id_by_email($email) !== False){
		$msg .= 'An account has already been registered with that email address <br />';
		$allOk = False;
	}
	if ($allOk){
		fof_db_add_user($username, $password);
		$uid = fof_db_get_user_id($username);
		$token = fof_make_salt();
		$enc_token = urlencode($token);
			
		//email out token
		$subject = 'fofork registration - confirmation';
		$body = <<<END
		<html>
		<head>
		<title>fofork registration - confirmation</title>
		</head>
		<body>
		Thank you for registering a fofork account at $FOF_BASE_URL <br /><br />
		To confirm you registration, please click the following link, and login
		with the details you provided at registration:
		<a href=\"$FOF_BASE_URL/register.php?uid=$uid&token=$enc_token\">
		$FOF_BASE_URL/register.php?uid=$uid&token=$enc_token</a>
		</body>
		</html>
END;
		mail($email, $subject, $body);
		$prefs = new FoF_Prefs($uid);
		fof_db_set_email($uid, $email);
		//$prefs->set('email', $email);
		$prefs->set('confirmed', False);
		$prefs->set('token',$token);
		$prefs->save();
		session_unset();
		session_destroy();
		$msg = 'Thank you for registering.  Please check your nominated email account to confirm your registration'; 
	}
}

//otherwise, display registration form
//to minimise spam signups, we'll randomly generate the field names, 
//and present them in a random order
//The bot has a 1/24 chance of guessing correctly
//we will also add some "honeypot" fields with normal looking names
//and clear them.  If the fields are filled, reject registration.

//obviously this won't stop targeted attacks, but should stop generic bots

function make_name(){
	//don't disclose mt_rand outputs!
	$name = str_replace(
		array('+','/'),
		array('-','_'),
		substr(
			base64_encode(
				hash('tiger192,4', mt_rand(), True)), 0, 8));
	return $name;
	
}
$fieldCodes = array();
$messages = array_combine($fields,
	array('User name:', 'Email address:', 'Password:', 'Confirm Password:'));
$i = 0;
$numFields = count($fields);
while ($i < $numFields){
	$newname = make_name();
	if (array_search($newname, $fieldCodes) === False){
		$fieldCodes[$fields[$i]] = $newname;
		$i++;
	}
}
$honeyPots = $fields;
//add some extra honeypots
for ($i=0; $i<$numFields; $i++){
	$honeyPots[] = make_name();
}

//now save the fieldCodes and honeyPot fields
$_SESSION['field_codes'] = $fieldCodes;
$_SESSION['honey_pots'] = $honeyPots;

?>
<!DOCTYPE html>
<html>

   <head>
	   
	   <script>
		   window.onload = function(){
				var ins = document.getElementsByClassName('user_in');
				var len = ins.length;
				for (var i=0; i<len; i++){
					ins[i].value = '';
				}
				var hides = document.getElementsByClassName('hide');
				len = hides.length;
				for (var i=0; i<len; i++){
					hides[i].style.display = "none";
				}
		   };
	   </script>
      <title>fofork - Register New User</title>
      
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
          margin: 1em auto;
          padding: 1.5em;
      }
      </style>
   </head>
      
  <body>
<div>
	<center><h2>fofork - new user registration</h2></center>
	<div class="hide">
		<center><font color="red">You must enable javascript to register on this site!</font></center>
	</div>
	<center><font color="red"><?php echo $msg;?></font></center>
	<form method="post" action="register.php">
		<input type="hidden" name="registered" value="true">
		<?php
		//output the fields and the honeypots
		foreach ($fields as $i => $field){
			$r = ord(substr(hash('tiger192,4',mt_rand(),True),0,1)) % 2;
			$message = $messages[$field];
			$type = preg_match('/password/', $field) ? 'password' : 'string';
			if ($r){
				$first = $honeyPots[$i+$numFields];
				$second = $fieldCodes[$field];
				$c1='hide';
				$c2='show';
			} else {
				$first = $fieldCodes[$field];
				$second = $honeyPots[$i+$numFields];
				$c1 = 'show';
				$c2 = 'hide';
			}
			?>
		
			<div class="<?php echo $c1;?>">
				<?php echo $message;?> <input type="<?php echo $type;?>" class="user_in" name="<?php echo $first;?>"><br />
			</div>
			<div class="<?php echo $c2;?>">
				<?php echo $message;?> <input type="<?php echo $type;?>" class="user_in" name="<?php echo $second;?>"><br />
			</div>
			<div class="hide">
				<?php echo $message;?> <input type="<?php echo $type;?>" class="user_in" name="<?php echo $honeyPots[$i];?>"><br />
			</div>
		<?php } ?>
		<input type="submit" name="register" value="Register">
	</form>
</div>
</body>
  
</html>
