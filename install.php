<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * install.php - creates tables and cache directory, if they don't exist
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$fof_no_login = true;
$fof_installer = true;

include_once('fof-main.php');

//test if there's already an installation or a conflicting table name
$tables = fof_query_log('SHOW TABLES',null);
$tableNames = array($FOF_TAG_TABLE, $FOF_USER_TABLE, $FOF_FEED_TABLE, 
				$FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, 
				$FOF_SUBSCRIPTION_TABLE, $FOF_CONFIG_TABLE);
$conflict = False;
while ($line = fof_db_get_row($tables)){
	if (in_array($line[0], $tableNames)){
		$conflict = True;
		$conflictName = $line[0];
		break;
	}
}
if ($conflict){
	die("Cannot install, there already exists a table named $conflictName in the database");
}

fof_set_content_type();

// compatibility testing code lifted from SimplePie

function get_curl_version()
{
        if (is_array($curl = curl_version()))
        {
                $curl = $curl['version'];
        }
        else if (preg_match('/curl\/(\S+)(\s|$)/', $curl, $match))
        {
                $curl = $match[1];
        }
        else
        {
                $curl = 0;
        }
        return $curl;
}

$php_ok = (function_exists('version_compare') && version_compare(phpversion(), '5.3', '>='));
$xml_ok = extension_loaded('xml');
$pcre_ok = extension_loaded('pcre');

$curl_ok = (extension_loaded('curl') && version_compare(get_curl_version(), '7.10.5', '>='));
$zlib_ok = extension_loaded('zlib');
$mbstring_ok = extension_loaded('mbstring');
$iconv_ok = extension_loaded('iconv');

?>
<!DOCTYPE html>
<html>

<head><title>feed on feeds <?php echo FOF_VERSION;?> - installation</title>
		<link rel="stylesheet" href="fof.css" media="screen" />
		<script src="fof.js" type="text/javascript"></script>
		<meta name="ROBOTS" content="NOINDEX, NOFOLLOW" />
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
            width: 75%;
            margin: 5em auto;
            padding: 1.5em;
        }
        
        hr
        {
            height:0;
            border:0;
            border-top:1px solid #999;
        }
        
        .fail { color: red; }
        
        .pass { color: green; }

        .warn { color: #a60; }
        
        </style>

	</head>

	<body><div>		<center style="font-size: 20px;"><a href="http://feedonfeeds.com/">Feed on Feeds</a> - Installation</center><br>


<?php
if($_POST['password'] && $_POST['password'] == $_POST['password2'] )
{
	fof_query_log("insert into $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level, salt) values (1, 'admin', 'ABCDEF', 'admin', 'ABCDEF')", null);
	fof_db_change_password('admin',$_POST['password']);
		
	echo '<center><b>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b></div></body></html>';
}
else
{
    if($_POST['password'] != $_POST['password2'] )
    {
        echo '<center><font color="red">Passwords do not match!</font></center><br><br>';
    } else {
    	echo '<center><font color="red">You must enter a password!</font></center><br><br>';
    }

?>

Checking compatibility...
<?php
if($php_ok) echo "<span class='pass'>PHP ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP version is too old!</span>  Feed on Feeds requires at least PHP 5.3.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($xml_ok) echo "<span class='pass'>XML ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the XML extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($pcre_ok) echo "<span class='pass'>PCRE ok...</span> ";
else
{
    echo "<br><span class='fail'>Your PHP installation is missing the PCRE extension!</span>  This is required by Feed on Feeds.  Sorry!";
    echo "</div></body></html>";
    exit;
}

if($curl_ok) echo "<span class='pass'>cURL ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is either missing the cURL extension, or it is too old!</span>  cURL version 7.10.5 or later is required to be able to subscribe to https or digest authenticated feeds.<br>";
}

if($zlib_ok) echo "<span class='pass'>Zlib ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the Zlib extension!</span>  Feed on Feeds will not be able to save bandwidth by requesting compressed feeds.<br>";
}

if($iconv_ok) echo "<span class='pass'>iconv ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the iconv extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
}

if($mbstring_ok) echo "<span class='pass'>mbstring ok...</span> ";
else
{
    echo "<br><span class='warn'>Your PHP installation is missing the mbstring extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
}

?>
<br>Minimum requirements met!
<hr>

Creating tables...
<?php

$tables = array();
$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_FEED_TABLE` (
  `feed_id` int(11) NOT NULL auto_increment,
  `feed_url` text NOT NULL,
  `feed_title` text NOT NULL,
  `feed_link` text NOT NULL,
  `feed_description` text NOT NULL,
  `feed_image` text,
  `feed_image_cache_date` int(11) default '0',
  `feed_cache_date` int(11) default '0',
  `feed_cache_attempt_date` int(11) default '0',
  `feed_cache` text,
  PRIMARY KEY  (`feed_id`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_ITEM_TABLE` (
  `item_id` int(11) NOT NULL auto_increment,
  `feed_id` int(11) NOT NULL default '0',
  `item_guid` text NOT NULL,
  `item_link` text NOT NULL,
  `item_cached` int(11) NOT NULL default '0',
  `item_published` int(11) NOT NULL default '0',
  `item_updated` int(11) NOT NULL default '0',
  `item_title` text NOT NULL,
  `item_content` text NOT NULL,
  PRIMARY KEY  (`item_id`),
  KEY `feed_id` (`feed_id`),
  KEY `item_guid` (`item_guid`(255)),
  KEY `feed_id_item_cached` (`feed_id`,`item_cached`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_ITEM_TAG_TABLE` (
  `user_id` int(11) NOT NULL default '0',
  `item_id` int(11) NOT NULL default '0',
  `tag_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`item_id`,`tag_id`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_SUBSCRIPTION_TABLE` (
  `feed_id` int(11) NOT NULL default '0',
  `user_id` int(11) NOT NULL default '0',
  `subscription_prefs` text,
  PRIMARY KEY  (`feed_id`,`user_id`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_TAG_TABLE` (
  `tag_id` int(11) NOT NULL auto_increment,
  `tag_name` char(100) NOT NULL default '',
  PRIMARY KEY  (`tag_id`),
  UNIQUE KEY (`tag_name`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_USER_TABLE` (
  `user_id` int(11) NOT NULL auto_increment,
  `user_name` varchar(100) NOT NULL default '',
  `user_password_hash` varchar(60) NOT NULL default '',
  `user_level` enum('user','admin') NOT NULL default 'user',
  `user_prefs` text,
  PRIMARY KEY  (`user_id`), UNIQUE KEY (`user_name`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_COOKIE_TABLE` (
  `token_hash` varchar(40) NOT NULL default '',
  `user_id` int(11) NOT NULL default '0',
  `user_agent_hash` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`token_hash`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_SESSION_TABLE` (
  	`id` varchar(32) NOT NULL,
    `access` int(11) unsigned,
    `data` text,
    PRIMARY KEY (`id`)
);
EOQ;

$tables[] = <<<EOQ
CREATE TABLE IF NOT EXISTS `$FOF_CONFIG_TABLE` (
`param` VARCHAR( 128 ) NOT NULL ,
`val` TEXT NOT NULL ,
UNIQUE (
`param`
)
);
EOQ;

foreach($tables as $table) {
	if(fof_query($table, 1, False) === False) {
		die("Database error: Can't create table $table. <br />" );
	}
}

?>

Inserting initial data...

<?php
fof_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (1, 'unread'), (2, 'star')", null, False);
fof_query("insert into $FOF_CONFIG_TABLE (param, val) values ('version', ?), ('bcrypt_effort', ?), ('log_password', ?),
				('logging', '0'), ('autotimeout', '10'), ('manualtimeout', '5'), ('purge', '7'), ('max_items_per_request', '100')", array(FOF_VERSION, BCRYPT_EFFORT, fof_make_salt()), False);
?>

Done.<hr>

Checking cache directory...
<?php

if ( ! file_exists( "cache" ) )
{
	$status = @mkdir( "cache", 0755 );

	if ( ! $status )
	{
		echo "<font color='red'>Can't create directory <code>" . getcwd() . "/cache/</code>.<br>You will need to create it yourself, and make it writeable by your PHP process.<br>Then, reload this page.</font>";
		echo "</div></body></html>";
        exit;
	}
}

if(!is_writable( "cache" ))
{
		echo "<font color='red'>The directory <code>" . getcwd() . "/cache/</code> exists, but is not writable.<br>You will need to make it writeable by your PHP process.<br>Then, reload this page.</font>";
		echo "</div></body></html>";
		exit;
}

?>

Cache directory exists and is writable.<hr>

<?php
	$result = fof_query_log("select * from $FOF_USER_TABLE where user_name = 'admin'", null);
	if($result->rowCount() == 0) {
?>

You now need to choose an initial password for the 'admin' account:<br>

<form method="post" action="install.php">
<table>
<tr><td>Password:</td><td><input type=password name=password></td></tr>
<tr><td>Password again:</td><td><input type=password name=password2></td></tr>
</table>
<input type=submit value="Set Password">
</form>

<?php } else { ?>

'admin' account already exists.<br>
<br><b><center>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b>

<?php } } ?>

</div></body></html>
