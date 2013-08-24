<?php
/*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * fof-main.php - initializes FoF, and contains functions used from 
 * other scripts
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
 
/***********************************************************************
//Setup code
* *********************************************************************/
fof_repair_drain_bamage();

if ( !file_exists( dirname(__FILE__) . '/fof-config.php') )
{
    echo "You will first need to create a fof-config.php file.  Please copy fof-config-sample.php to fof-config.php and then update the values to match your database settings.";
    die();
}

require_once('fof-config.php');
//set the base url
$FOF_BASE_URL = FOF_BASE_URL;
//remove trailing / if there is one
//base url should have form like https?://subdomain.domain.com
if (substr($FOF_BASE_URL,-1,1) == '/'){
	$FOF_BASE_URL = substr($FOF_BASE_URL,0,-1);
}

//set the website url here so we don't have to reset it all over the code
define('FOFORK_WEBSITE','http://robisacommonusername.github.io/fofork');
//version
define('FOF_VERSION', '1.6.0');

require_once('fof-db.php');
require_once('classes/fof-prefs.php');
require_once('classes/AES.php');
require_once('strippers.php');

fof_db_connect();

session_set_save_handler('fof_db_open_session',
                         'fof_db_close_session',
                         'fof_db_read_session',
                         'fof_db_write_session',
                         'fof_db_destroy_session',
                         'fof_db_clean_session');

if(!$fof_installer)
{
	session_start();
	//have to call session_write_close on shutdown, otherwise
	//the database connection gets garbage collected away
	register_shutdown_function('session_write_close');
    if(!$fof_no_login)
    {
        require_user();
    }
    else
    {
        //$_SESSION['user_id'] = 1;
    }
	$fof_prefs_obj =& FoF_Prefs::instance();
    ob_start();
    fof_init_plugins();
    ob_end_clean();
}

require_once('simplepie/simplepie_1.3.1.mini.php');


/***********************************************************************
//Library functions
***********************************************************************/
function fof_set_content_type()
{
    static $set;
    if(!$set)
    {
        header("Content-Type: text/html; charset=utf-8");
        $set = true;
    }
}

function fof_log($message, $topic="debug") {
    global $fof_prefs_obj;
    
    if(!$fof_prefs_obj) return;
    
    $p = $fof_prefs_obj->adminPrefs();
    if(!$p['logging']) return;

    static $log;
    $log = @fopen("fof.log", 'a');
    if(!$log) return;
    
    $message = str_replace ("\n", "\\n", $message); 
    $message = str_replace ("\r", "\\r", $message);
    //truncate long messages
    if (strlen($message) > 500) {
    	$message = substr($message, 0, 500) . " ... ";
    }
    $totalMessage = date('r') . " [$topic] $message";
    
    $aes = new Crypt_AES();
    $aes->setKey(fof_db_log_password());
    $IV = fof_make_aes_key();
    $aes->setIV($IV);
    $cipherText = base64_encode($IV . $aes->encrypt($totalMessage)) . "\n";
    
    fwrite($log, $cipherText);
}

function require_user()
{
    if(!isset($_SESSION['authenticated']))
    {
    	if (fof_validate_cookie()){
    		//prevent session fixation
    		session_regenerate_id(True);
    	} else {
        	Header("Location: login.php");
        	exit();
        }
    }
    //check user agent hasn't changed
	if (isset($_SESSION['user_agent_hash']) && isset($_SESSION['hash_salt'])){
		$computed = hash('tiger160,4', $_SERVER['HTTP_USER_AGENT'] . $_SESSION['hash_salt']);
		if ($computed != $_SESSION['user_agent_hash']){
			session_unset();
			session_destroy();
			setcookie('PHPSESSID', '');
			header('Location: login.php');
			exit();
		}
	} else {
		$_SESSION['hash_salt'] = fof_make_salt();
		$_SESSION['user_agent_hash'] = hash('tiger160,4', $_SERVER['HTTP_USER_AGENT'] . $_SESSION['hash_salt']);
	}
	//check for timeout
	if (isset($_SESSION['last_access'])){
		if ((time() - $_SESSION['last_access']) > 30*60){
			//check if the user has a persistent login.  If they
			//do, generate a new session id.  Otherwise, destroy session
			//and redirect to login
			if (fof_validate_cookie()){
				$old_id = session_id();
				session_regenerate_id(True);
				//regenerating session id will upset all the csrf checks
				//ie if user has left a form for more than half an hour, then
				//submits it, application will falsely accuse her of trying to launch a CSRF.
				
				//to prevent this, we will transparently modify the CSRF_hash value in $_POST
				//iff the CSRF check would have passed with the old session id.  This prevents the case
				//where an attacker tricks the user into submitting a request, and she just happens
				//to have an expired session (ie still won't be able to do CSRF)
				//this is potentially dangerous, as it allows an attacker who has acquired the old session id
				//to do a CSRF
				if (hash('tiger160,4', $_SESSION['user_name'] . $old_id) === $_POST['CSRF_hash']){
					$_POST['CSRF_hash'] = hash('tiger160,4', $_SESSION['user_name'] . session_id());
				}
			} else {
				session_unset();
				session_destroy();
				setcookie('PHPSESSID','');
				header('Location: login.php');
				exit();
			}
		}
	}
	$_SESSION['last_access'] = time();
}

function fof_make_aes_key() {
	//makes a 128 bit key, returned as a string of 16 raw bytes
	$bytes = null;
	//try to get something cryptographically secure (*nix only)
	if (file_exists('/dev/urandom')){
		try {
			$f = fopen('/dev/urandom', 'r');
			$bytes = fread($f, 16);
			fclose($f);
		} catch (Exception $e) {
			$bytes = null;
		}
	}
	if ($bytes === null) {
		//fallback using mersenne twister.  Not great, but hopefully can extract
		//enough entropy from mt_rand without being able to reconstruct internal state.
		//the hashing is important! Must not give attacker access to the outputs!
		//further note - must NOT use mt_rand ANYWHERE in code where it can give
		//attacker access to output.  Should probably enforce this somehow.
		// Want 128 bits
		for ($i=0; $i<6; $i++){
			$bytes = hash('tiger160,4', $bytes . mt_rand(), True);
		}
	}
	return substr($bytes,0,16);
}

function fof_make_salt() {
	//uses only printable characters, not raw binary.
	//22 characters, but only 16bytes entropy
	$k = fof_make_aes_key();
	$salt = substr(str_replace('+', '.', base64_encode($k)), 0, 22);
	return $salt;
}

function fof_make_bcrypt_salt() {
	$salt = fof_make_salt();
	$effort = fof_db_bcrypt_effort();
	$final = '$2a$' . $effort . '$' . $salt;
	return $final;
}

function fof_slow_compare($a,$b){
	//compare strings $a and $b by comparing every character, even if a
	//mismatch is found (prevent timing attacks)
	//returns true if strings are equal
	$lena = strlen($a); $lenb = strlen($b);
	$min = $lena < $lenb ? $lena : $lenb;
	$res = $lena == $lenb ? 0x00 : 0xff;
	for ($i=0; $i<$min; $i++){
		$res |= (ord($a{$i}) ^ ord($b{$i}));
	}
	return $res === 0;
}

function fof_place_cookie($user_id){
	global $FOF_BASE_URL;
	$new_id = fof_make_salt();
	$oldToken = isset($_COOKIE['token']) ? $_COOKIE['token'] : False;
	#store to db and set cookie
	fof_db_place_cookie($oldToken, $new_id, $user_id, $_SERVER['HTTP_USER_AGENT']);
	//bool setcookie ( string $name [, string $value [, int $expire = 0 [, string $path [, string $domain [, bool $secure = false [, bool $httponly = false ]]]]]] )
	//set httponly true, but https only to false (allow non-https logins, etc)
	
	//possible dns rebinding problem here, so ensure cookie can only be accessed from our domain
	$domain = preg_replace('|https?://|', '', $FOF_BASE_URL);
	setcookie('token',$new_id, time()+60*60*24*30, '',$domain,False,True); //30 day expiry
}

function fof_validate_cookie(){
	if (isset($_COOKIE['token'])){
		//check that the cookie is the correct length, correct alphabet.  This is to reduce chance
		//of attacker finding a working preimage from the hash (ie minimise the size of the working preimage space)
		if (!preg_match('|^[./0-9a-zA-z]{22}$|', $_COOKIE['token'])) {
			return False;
		}
		$result = fof_db_validate_cookie($_COOKIE['token'], $_SERVER['HTTP_USER_AGENT']);
		if (is_array($result)){
			$_SESSION['authenticated'] = True;
			$_SESSION['user_name'] = $result['user_name'];
			$_SESSION['user_id'] = $result['user_id'];
			$_SESSION['user_level'] = $result['user_level'];
			return True;
		}
	}
	return False;
}

function fof_logout()
{
    session_unset();
    session_destroy();
    setcookie('PHPSESSID','');
    if (isset($_COOKIE['token'])){
    	fof_db_delete_cookie($_COOKIE['token']);
    	setcookie('token','');
    }
    header('Location: ./login.php');
    exit();
}

function fof_current_user()
{
    return $_SESSION['user_id'];
}

function fof_username()
{  
    return $_SESSION['user_name'];
}

function fof_compute_CSRF_challenge(){
	$user_name = $_SESSION['user_name'];
    $challenge = hash('tiger160,4', $user_name . session_id());
    return $challenge;
}

function fof_authenticate_CSRF_challenge($response){
	$user_name = $_SESSION['user_name'];
    $challenge = hash('tiger160,4', $user_name . session_id());
    return ($challenge === $response);
}

function fof_prefs()
{        
    $p =& FoF_Prefs::instance();
    return $p->prefs;
}

function fof_is_admin()
{   
    return ($_SESSION['user_level'] == "admin");
}

function fof_get_tags($user_id)
{
    $tags = array();
    
    $result = fof_db_get_tags($user_id);
    
    $counts = fof_db_get_tag_unread($user_id);
    
    while($row = fof_db_get_row($result))
    {
		//postgresql (maybe others) adds extra whitespace to end of tag
		//that needs to be trimmed off
		$row['tag_name'] = trim($row['tag_name']);
        if(isset($counts[$row['tag_id']]))
            $row['unread'] = $counts[$row['tag_id']];
        else
            $row['unread'] = 0;
            
        $tags[] = $row;
    }
    
    return $tags;
}

function fof_tag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }
    
    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_tag_items($user_id, $tag_id, $items);
    
    fof_db_tag_feed($user_id, $feed_id, $tag_id);
}

function fof_untag_feed($user_id, $feed_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);
    if($tag_id == NULL)
    {
        $tag_id = fof_db_create_tag($user_id, $tag);
    }
    
    $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_untag_items($user_id, $tag_id, $items);
    
    fof_db_untag_feed($user_id, $feed_id, $tag_id);
}

function fof_tag_item($user_id, $item_id, $tag)
{
	if(is_array($tag)) $tags = $tag; else $tags[] = $tag;
	
	foreach($tags as $tag)
	{
		$tag_id = fof_db_get_tag_by_name($user_id, $tag);
		if($tag_id == NULL)
		{
			$tag_id = fof_db_create_tag($user_id, $tag);
		}
		
		fof_db_tag_items($user_id, $tag_id, $item_id);   
	}
}

function fof_untag_item($user_id, $item_id, $tag)
{
   $tag_id = fof_db_get_tag_by_name($user_id, $tag);
   fof_db_untag_items($user_id, $tag_id, $item_id);   
}

function fof_untag($user_id, $tag)
{
    $tag_id = fof_db_get_tag_by_name($user_id, $tag);

    $result = fof_db_get_items($user_id, $feed_id, $tag, NULL, NULL);
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_untag_items($user_id, $tag_id, $items);
}

function fof_nice_time_stamp($age)
{
      $age = time() - $age;

      if($age == 0)
      {
         $agestr = "never";
         $agestrabbr = "&infin;";
      }
      else
      {
         $seconds = $age % 60;
         $minutes = $age / 60 % 60;
         $hours = $age / 60 / 60 % 24;
         $days = floor($age / 60 / 60 / 24);

         if($seconds)
         {
            $agestr = "$seconds second";
            if($seconds != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $seconds . "s";
         }

         if($minutes)
         {
            $agestr = "$minutes minute";
            if($minutes != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $minutes . "m";
         }

         if($hours)
         {
            $agestr = "$hours hour";
            if($hours != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $hours . "h";
         }

         if($days)
         {
            $agestr = "$days day";
            if($days != 1) $agestr .= "s";
            $agestr .= " ago";

            $agestrabbr = $days . "d";
         }
      }
      
      return array($agestr, $agestrabbr);
}

function fof_get_feeds($user_id, $order = 'feed_title', $direction = 'asc')
{
   $feeds = array();
   
   $result = fof_db_get_subscriptions($user_id);
   
   $i = 0;

   while($row = fof_db_get_row($result))
   {
      $id = $row['feed_id'];
      $age = $row['feed_cache_date'];

      $feeds[$i]['feed_id'] = $id;
      $feeds[$i]['feed_url'] = $row['feed_url'];
      $feeds[$i]['feed_title'] = $row['feed_title'];
      $feeds[$i]['feed_link'] = $row['feed_link'];
      $feeds[$i]['feed_description'] = $row['feed_description'];
      $feeds[$i]['feed_image'] = $row['feed_image'];
      $feeds[$i]['prefs'] = unserialize($row['subscription_prefs']);
      $feeds[$i]['feed_age'] = $age;

	  list($agestr, $agestrabbr) = fof_nice_time_stamp($age);
	  
      $feeds[$i]['agestr'] = $agestr;
      $feeds[$i]['agestrabbr'] = $agestrabbr;

      $i++;
   }
   
   $tags = fof_db_get_tag_id_map();
   
   for($i=0; $i<count($feeds); $i++)
   {
       $feeds[$i]['tags'] = array();
       if(is_array($feeds[$i]['prefs']['tags']))
       {
           foreach($feeds[$i]['prefs']['tags'] as $tag)
           {
               $feeds[$i]['tags'][] = $tags[$tag];
           }
       }
   }
     
   $result = fof_db_get_item_count($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['feed_items'] = $row['count'];
         $feeds[$i]['feed_read'] = $row['count'];
         $feeds[$i]['feed_unread'] = 0;
      }
     }
   }

   $result = fof_db_get_unread_item_count($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['feed_unread'] = $row['count'];
      }
     }
   }

   foreach($feeds as $feed)
   {
      $feed['feed_starred'] = 0;
   }
   
   $result = fof_db_get_starred_item_count($user_id);

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['feed_starred'] = $row['count'];
      }
     }
   }
   
   $result = fof_db_get_latest_item_age();

   while($row = fof_db_get_row($result))
   {
     for($i=0; $i<count($feeds); $i++)
     {
      if($feeds[$i]['feed_id'] == $row['id'])
      {
         $feeds[$i]['max_date'] = $row['max_date'];
		  list($agestr, $agestrabbr) = fof_nice_time_stamp($row['max_date']);
	  
    	  $feeds[$i]['lateststr'] = $agestr;
      	$feeds[$i]['lateststrabbr'] = $agestrabbr;

      }
     }
   }


   $feeds = fof_multi_sort($feeds, $order, $direction != "asc");

   return $feeds;
}

function fof_get_items($user_id, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL)
{
   global $fof_item_filters;
   
   $items = fof_db_get_items($user_id, $feed, $what, $when, $start, $limit, $order, $search);
   
   for($i=0; $i<count($items); $i++)
   {
   	  foreach($fof_item_filters as $filter)
   	  {
		  $items[$i]['item_content'] = $filter($items[$i]['item_content']);
      }
   }
   
   return $items;
}

function fof_get_item($user_id, $item_id)
{   
   global $fof_item_filters;

   $item = fof_db_get_item($user_id, $item_id);
   
   foreach($fof_item_filters as $filter)
   {
      $item['item_content'] = $filter($item['item_content']);
   }
   
   return $item;
}

function fof_escape_feed_info($feed){
	$stripper = new FofFeedSanitiser();
	$id = intval($feed['feed_id']);
   	$url = $stripper->sanitiseLink($feed['feed_url']);
   	$title = fof_htmlspecialchars(strip_tags($feed['feed_title']));
   	$link = $stripper->sanitiseLink($feed['feed_link']);  
   	$tags = array_map('fof_htmlspecialchars', $feed['tags']);
   	$feed_image = $stripper->sanitiseLink($feed['feed_image']);
   	
   	$description = fof_htmlspecialchars($feed['feed_description']);
   	$age = intval($feed['feed_age']);
   	$unread = intval($feed['feed_unread']);
   	$starred = intval($feed['feed_starred']);
   	$items = intval($feed['feed_items']);

   	return array($id,$url,$title,$link,$tags,$feed_image,$description,$age,$unread,$starred,$items);
	
}

function fof_escape_item_info($item){
	$stripper = new FofItemSanitiser();
	$feed_link = $stripper->sanitiseLink($item['feed_link']);
	$feed_title = fof_htmlspecialchars(strip_tags($item['feed_title']));
	$feed_image = $stripper->sanitiseLink($item['feed_image']);
	$feed_description = fof_htmlspecialchars(strip_tags($item['feed_description']));

	$item_link = $stripper->sanitiseLink($item['item_link']);
	$item_id = intval($item['item_id']);
	$item_title = fof_htmlspecialchars(strip_tags($item['item_title']));
	$item_content = $stripper->sanitise($item['item_content']);
	
	$item_published = gmdate("Y-n-d g:ia", $item['item_published'] + $offset*60*60);
	return array($feed_link, $feed_title, $feed_image, $feed_description, $item_link, $item_id, $item_title, $item_content, $item_published);
}

function fof_delete_subscription($user_id, $feed_id)
{
    fof_db_delete_subscription($user_id, $feed_id);
    
    if(fof_get_subscribed_users($feed_id)->rowCount() == 0)
    {
    	fof_db_delete_feed($feed_id);
    }
}

function fof_opml_to_array($opml)
{
   $rx = '/xmlurl\s*=\s*"([^"]*)"/mi'; //get whatever's between the quotes

   if (preg_match_all($rx, $opml, $m))
   {
      for($i = 0; $i < count($m[0]) ; $i++)
      {
         $r[] = $m[1][$i];
      }
  }

  return $r;
}

function fof_prepare_url($url)
{
   $url = trim($url);

   if(substr($url, 0, 7) == "feed://") $url = substr($url, 7);

   if(substr($url, 0, 7) != 'http://' && substr($url, 0, 8) != 'https://')
   {
     $url = 'http://' . $url;
   }

    return $url;
}

function fof_add_feed($url, $title, $link, $description)
{
   if($title == "") $title = "[no title]";

   $id = fof_db_add_feed($url, $title, $link, $description);
   
   return $id;
}

function fof_get_subscribed_users($feed_id)
{
   return(fof_db_get_subscribed_users($feed_id));
}

function fof_parse($url)
{
    $p =& FoF_Prefs::instance();
    $admin_prefs = $p->adminPrefs();
    
    $pie = new SimplePie();
    $pie->set_cache_duration($admin_prefs["manualtimeout"] * 60);
    $pie->set_favicon_handler("favicon.php");
	$pie->set_feed_url($url);
	//$pie->set_javascript(false);
	$pie->remove_div(false);
	$pie->init();
	
	return $pie;
}

function fof_update_feed($id) {
    if(!$id) return 0;
    
    $feed = fof_db_get_feed_by_id($id);
    $url = $feed['feed_url'];
    fof_log("Updating $url");
    
    fof_db_feed_mark_attempted_cache($id);
    
    $rss = fof_parse($feed['feed_url']);
    
    $escapedUrl = htmlspecialchars($url,ENT_QUOTES);
    if ($rss->error())
    {
        fof_log("feed update failed: " . $rss->error(), "update");
        return array(0, "Error: <b>" . htmlspecialchars($rss->error(),ENT_QUOTES) . "</b> <a href=\"http://feedvalidator.org/check?url=$escapedUrl\">try to validate it?</a>");
    }
        
    $sub = html_entity_decode($rss->subscribe_url(), ENT_QUOTES);
    $self_link = $rss->get_link(0, 'self');
    if($self_link) $sub = html_entity_decode($self_link, ENT_QUOTES);
    
    fof_log("subscription url is $sub");
    
    $image = $feed['feed_image'];
    $image_cache_date = $feed['feed_image_cache_date'];
    
    if($feed['feed_image_cache_date'] < (time() - (7*24*60*60)))
    {
        $image = $rss->get_favicon();
        $image_cache_date = time();
    }
	
	$title =  $rss->get_title();
	if($title == "") $title = "[no title]";
	
    fof_db_feed_update_metadata($id, $sub, $title, $rss->get_link(), $rss->get_description(), $image, $image_cache_date );
    
    $feed_id = $feed['feed_id'];
    
    $items = $rss->get_items();
    if($items) {
 		//add the items to the db and mark as unread
        $ids = fof_db_add_items($feed_id, $items);
        
        //apply any necessary subscription tags
        fof_db_apply_subscription_tags($feed_id, $ids);
        
    }
    $n = count($ids);

    // optionally purge old items -  if 'purge' is set we delete items that are not
    // unread or starred, not currently in the feed or within sizeof(feed) items
    // of being in the feed, and are over 'purge' many days old
    
    $p =& FoF_Prefs::instance();
    $admin_prefs = $p->adminPrefs();
    $ndelete = 0;
    if($admin_prefs['purge'] != "") {
        fof_log('purge is ' . $admin_prefs['purge']);
        fof_log("items in feed: $n");

        $ndelete = fof_db_purge_feed($ids, $feed_id, $admin_prefs['purge']);
    }
    
    unset($rss);
    
    fof_db_feed_mark_cached($feed_id);
    
    $log = "feed update complete, $n new items, $ndelete items purged";
    if($admin_prefs['purge'] == "")
    {
        $log .= ' (purging disabled)';
    }
    fof_log($log, 'update');

    return array($n, '');
}

function fof_apply_plugin_tags($feed_id, $item_id = null, $user_id = null)
{
    $users = array();

    if($user_id)
    {
        $users[] = $user_id;
    }
    else
    {
        $result = fof_get_subscribed_users($feed_id);
        
        while($row = fof_db_get_row($result))
        {
            $users[] = $row['user_id'];
        }
    }
    
    $items = array();
    if($item_id)
    {
        $items[] = fof_db_get_item($user_id, $item_id);
    }
    else
    {
        $result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
        
        foreach($result as $r)
        {
            $items[] = $r;
        }
    }
    
    $userdata = fof_db_get_users();
    
    foreach($users as $user)
    {
        fof_log("tagging for $user");
                
        global $fof_tag_prefilters;
        foreach($fof_tag_prefilters as $plugin => $filter)
        {
            fof_log("considering $plugin $filter");
    
            if(!$userdata[$user]['user_prefs']['plugin_' . $plugin])
            {
                foreach($items as $item)
                {
                    $tags = $filter($item['item_link'], $item['item_title'], $item['item_content']);
                    fof_tag_item($user, $item['item_id'], $tags);
                }
            }
        }
    }
}

function fof_init_plugins()
{
	global $fof_item_filters, $fof_item_prefilters, $fof_tag_prefilters, $fof_plugin_prefs;
    
    $fof_item_filters = array();
    $fof_item_prefilters = array();
    $fof_plugin_prefs = array();
	$fof_tag_prefilters = array();

    $p =& FoF_Prefs::instance();
    
    $dirlist = opendir(FOF_DIR . DIRECTORY_SEPARATOR . 'plugins');
    while($file=readdir($dirlist))
    {
    	fof_log("considering $file");
        if(preg_match('/\.php$/',$file) && $p->get('plugin_' . substr($file, 0, -4)))
        {
        	fof_log("including $file");

            include(FOF_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $file);
        }
    }

    closedir();
}

function fof_add_tag_prefilter($plugin, $function)
{
    global $fof_tag_prefilters;
    
    $fof_tag_prefilters[$plugin] = $function;
}

function fof_add_item_filter($function)
{
    global $fof_item_filters;
    
    $fof_item_filters[] = $function;
}

function fof_add_item_prefilter($function)
{
    global $fof_item_prefilters;
    
    $fof_item_prefilters[] = $function;
}

//do nothing function as default argument to fof_add_pref
//php doesn't just let us pass a lambda as a default arg, hence why this
//function is declared here
function default_sanitiser($x) {
	return False;
}
function fof_add_pref($name, $key, $type='string', $sanitiser='default_sanitiser')
{
    global $fof_plugin_prefs;
    
    $fof_plugin_prefs[] = array($name, $key, $type, $sanitiser);
}

function fof_add_item_widget($function)
{
    global $fof_item_widgets;
    
    $fof_item_widgets[] = $function;
}

function fof_get_widgets($item)
{
    global $fof_item_widgets;

    if (!is_array($fof_item_widgets))
    {
		return false;
	}

    foreach($fof_item_widgets as $widget)
    {
        $w = $widget($item);
        if($w) $widgets[] = $w;
    }
     
    return $widgets;
}

function fof_get_plugin_prefs()
{
    global $fof_plugin_prefs;
    
    return $fof_plugin_prefs;
}

function fof_multi_sort($tab,$key,$rev)
{
    $allowedKeys = array('feed_id','feed_url','feed_link','feed_description','feed_title','item_published',
                        'feed_cache_attempt_date','item_updated','item_cached','item_id','item_title');
    if (in_array($key, $allowedKeys)) {
        if($rev)
            $compare = function ($a,$b) use($key){
            	if (strtolower($a[$key]) == strtolower($b[$key])) {
            		return 0;
            	} else {
            		return (strtolower($a[$key]) > strtolower($b[$key])) ? -1 : 1;
            	}
            };
        else
            $compare = function ($a,$b) use($key){
            	if (strtolower($a[$key]) == strtolower($b[$key])) {
            		return 0;
            	} else {
            		return (strtolower($a[$key]) < strtolower($b[$key])) ? -1 : 1;
            	}
            };
            
        usort($tab,$compare) ;
    }
    return $tab ;
}

function fof_todays_date()
{
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    
    return gmdate( "Y/m/d", time() + ($offset * 60 * 60) );
}

function fof_htmlspecialchars($str){
	//essentially does the same thing as htmlspecialchars($string, ENT_QUOTES), except
	//that if text has ALREADY been escaped, it won't stuff things up.
	//ie & becomes &amp;
	//but &quot; is NOT transformed to &amp;quot;
	$new = preg_replace('/&(?!(lt|gt|quot|amp|#039|pound|#163|mdash|#151);)/', '&amp;', $str);
	$new = str_replace(array('<','>','"', "'"), array('&lt;','&gt;','&quot;','&#039;'), $new);
	
	//allow some very basic tags, eg <em>
	$allowedTags = array('em');
	$toFind = array_map(function($tag){return "&lt;$tag&gt;";}, $allowedTags);
	$toFind2 = array_map(function($tag){return "&lt;/$tag&gt;";}, $allowedTags);
	$replace = array_map(function($tag){return "<$tag>";}, $allowedTags);
	$replace2 = array_map(function($tag){return "</$tag>";}, $allowedTags);
	$new = str_replace(array_merge($toFind,$toFind2), array_merge($replace,$replace2), $new);
	
	return $new;
}

function fof_int_validator($lower, $upper) {
	return function($x) use($lower, $upper){
		$val = intval($x);
		$ok = ($val >= $lower && $val <= $upper);
		return array($ok, $val);
	};
}

function fof_bool_validator() {
	return function($x) {
		$fixed = preg_match('/on|true|checked|1/i',$x) ? True : False;
		return array(True, $fixed);
	};
}

function fof_string_validator($regex) {
	return function($x) use($regex){
		$ok = preg_match($regex, $x) ? True : False;
		return array($ok, $x);
	};
}

function fof_repair_drain_bamage()
{
    if (ini_get('register_globals')) foreach($_REQUEST as $k=>$v) { unset($GLOBALS[$k]); }
    
    // thanks to submitter of http://bugs.php.net/bug.php?id=39859
    if (get_magic_quotes_gpc()) {
        function undoMagicQuotes($array, $topLevel=true) {
            $newArray = array();
            foreach($array as $key => $value) {
                if (!$topLevel) {
                    $key = stripslashes($key);
                }
                if (is_array($value)) {
                    $newArray[$key] = undoMagicQuotes($value, false);
                }
                else {
                    $newArray[$key] = stripslashes($value);
                }
            }
            return $newArray;
        }
        $_GET = undoMagicQuotes($_GET);
        $_POST = undoMagicQuotes($_POST);
        $_COOKIE = undoMagicQuotes($_COOKIE);
        $_REQUEST = undoMagicQuotes($_REQUEST);
    }
}
?>
