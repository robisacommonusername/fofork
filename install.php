<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * install.php - creates tables and cache directory, if they don't exist
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

$fof_no_login = true;
$fof_installer = true;

include_once('fof-main.php');

//test if there's already an installation or a conflicting table name
$tablesInDB = fof_db_table_list();
$tableNames = array_values($FOF_TABLES_ARRAY);
$isect = array_intersect($tablesInDB, $tableNames);
if (count($isect) > 0){
	echo 'Cannot install.  The following tables already exist in the database: <br />';
	foreach ($isect as $conflictTable) {
		echo "$conflictTable <br />";
	}
	die();
}

fof_set_content_type();

// compatibility testing code lifted from SimplePie

function get_curl_version() {
	if (is_array($curl = curl_version())){
		$curl = $curl['version'];
	} else if (preg_match('/curl\/(\S+)(\s|$)/', $curl, $match)) {
		$curl = $match[1];
	} else {
		$curl = 0;
	}
	return $curl;
}

function createTables() {
	global $FOF_TABLES_ARRAY;
	
	$schema = '';
	switch (FOF_DB_TYPE) {
		case 'pgsql':
		$schema = 'schema/fof_tables_pgsql.sql';
		break;
		
		default:
		$schema = 'schema/fof_tables.sql';
	}
	
	$sql = file_get_contents($schema);
	$sql = str_replace(array_keys($FOF_TABLES_ARRAY), array_values($FOF_TABLES_ARRAY), $sql);
	$stmnts = explode(';', $sql);
	$stmnts = array_filter($stmnts, function ($x) {return !preg_match('/^\s*$/', $x);});
	foreach ($stmnts as $stmnt) {
		if (fof_query($stmnt, null, False) === False) {
			die("Database error: Can't create tables!. <br />" );
		}
	}
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

<head><title>fofork <?php echo FOF_VERSION;?> - installation</title>
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

	<body><div>		<center style="font-size: 20px;"><a href="<?php echo FOFORK_WEBSITE; ?>">fofork</a> - Installation</center><br>


<?php
if ($_POST['install_confirmed'] == 'yes' && isset($_POST['password']) && isset($_POST['password2'])) {
	if ($_POST['password'] === $_POST['password2']) {
		//begin installation
		echo 'Creating tables... <br />';
		createTables();
		echo 'Inserting initial data... <br />';
		
		fof_query("insert into $FOF_TAG_TABLE (tag_id, tag_name) values (1, 'unread'), (2, 'star')", null, False);
		fof_query("insert into $FOF_CONFIG_TABLE (param, val) values ('version', ?), ('bcrypt_effort', ?), ('log_password', ?), ('logging', '0'), ('autotimeout', '10'), ('manualtimeout', '5'), ('purge', '7'), ('max_items_per_request', '100'), ('open_registration', '0')", array(FOF_VERSION, BCRYPT_EFFORT, base64_encode(fof_make_aes_key())), False);

		echo 'Done.<hr>';
		
		//set admin password
		fof_query_log("insert into $FOF_USER_TABLE (user_id, user_name, user_password_hash, user_level) values (1, 'admin', 'ABCDEF', 'admin')", null);
		fof_db_change_password('admin',$_POST['password']);
		
		echo '<center><b>OK!  Setup complete! <a href=".">Login as admin</a>, and start subscribing!</center></b></div></body></html>';
	} else {
        echo '<center><font color="red">Passwords do not match!</font></center><br><br>';
    }
} else {
?>
	Please enter an admin password below, and click the button to install fofork! <br /><br />
	
	Checking compatibility...
	<?php
	if ($php_ok) {
		echo "<span class='pass'>PHP ok...</span> ";
	} else {
		echo "<br><span class='fail'>Your PHP version is too old!</span>  Feed on Feeds requires at least PHP 5.3.  Sorry!";
		echo "</div></body></html>";
		exit;
	}

	if ($xml_ok) {
		echo "<span class='pass'>XML ok...</span> ";
	} else {
    	echo "<br><span class='fail'>Your PHP installation is missing the XML extension!</span>  This is required by Feed on Feeds.  Sorry!";
    	echo "</div></body></html>";
    	exit;
	}

	if ($pcre_ok) {
		echo "<span class='pass'>PCRE ok...</span> ";
	} else {
		echo "<br><span class='fail'>Your PHP installation is missing the PCRE extension!</span>  This is required by Feed on Feeds.  Sorry!";
		echo "</div></body></html>";
		exit;
	}

	if ($curl_ok) {
		echo "<span class='pass'>cURL ok...</span> ";
	} else {
		echo "<br><span class='warn'>Your PHP installation is either missing the cURL extension, or it is too old!</span>  cURL version 7.10.5 or later is required to be able to subscribe to https or digest authenticated feeds.<br>";
	}

	if ($zlib_ok) {
		echo "<span class='pass'>Zlib ok...</span> ";
	} else {
		echo "<br><span class='warn'>Your PHP installation is missing the Zlib extension!</span>  Feed on Feeds will not be able to save bandwidth by requesting compressed feeds.<br>";
	}

	if ($iconv_ok) {
		echo "<span class='pass'>iconv ok...</span> ";
	} else {
		echo "<br><span class='warn'>Your PHP installation is missing the iconv extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
	}

	if ($mbstring_ok) {
		echo "<span class='pass'>mbstring ok...</span> ";
	} else {
    	echo "<br><span class='warn'>Your PHP installation is missing the mbstring extension!</span>  The number of international languages that Feed on Feeds can handle will be reduced.<br>";
	}

	?>
	<br />Minimum requirements met!
	<hr>

<?php
//check the cache directory
if (!file_exists('cache')) {
	$status = @mkdir('cache', 0755);
	if (!$status) {
		echo "<font color='red'>Can't create directory <code>" . getcwd() . "/cache/</code>.<br>You will need to create it yourself, and make it writeable by your PHP process.<br>Then, reload this page.</font>";
		echo "</div></body></html>";
        exit;
	}
}

if(!is_writable('cache')) {
	echo "<font color='red'>The directory <code>" . getcwd() . "/cache/</code> exists, but is not writable.<br>You will need to make it writeable by your PHP process.<br>Then, reload this page.</font>";
	echo "</div></body></html>";
	exit;
}

?>

Cache directory exists and is writable.<hr>

You will need to choose an initial password for the 'admin' account: <br />

<form method="post" action="install.php">
<input type="hidden" name="install_confirmed" value="yes">
<table>
<tr><td>Password:</td><td><input type=password name=password></td></tr>
<tr><td>Password again:</td><td><input type=password name=password2></td></tr>
</table>
<input type=submit value="Install!">
</form>

<?php } ?>

</div></body></html>
