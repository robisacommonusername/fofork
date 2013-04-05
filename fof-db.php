<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-db.php - (nearly) all of the DB specific code
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

$FOF_FEED_TABLE = FOF_FEED_TABLE;
$FOF_ITEM_TABLE = FOF_ITEM_TABLE;
$FOF_ITEM_TAG_TABLE = FOF_ITEM_TAG_TABLE;
$FOF_SUBSCRIPTION_TABLE = FOF_SUBSCRIPTION_TABLE;
$FOF_TAG_TABLE = FOF_TAG_TABLE;
$FOF_USER_TABLE = FOF_USER_TABLE;
$FOF_COOKIE_TABLE = FOF_COOKIE_TABLE;
$FOF_SESSION_TABLE = FOF_SESSION_TABLE;

$fof_connection = 3;

////////////////////////////////////////////////////////////////////////////////
// Utilities
////////////////////////////////////////////////////////////////////////////////

function fof_db_connect(){
    global $fof_connection;

	try {
		$connString = FOF_DB_TYPE.':host='.FOF_DB_HOST.';dbname='.FOF_DB_DBNAME;
		$fof_connection = new PDO($connString, FOF_DB_USER, FOF_DB_PASS);
		//check if database exists, try to create it if not
	} catch (PDOException $e) {
		die('<br><br>Cannot connect to database.  Please update configuration in <b>fof-config.php</b>.  PDO says: <i>' . $e->getMessage() . '</i>');
	}
	return $fof_connection;
}

function fof_db_optimize()
{
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE, $FOF_COOKIE_TABLE, $FOF_SESSION_TABLE;
    
	fof_db_query("optimize table $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE, $FOF_COOKIE_TABLE, $FOF_SESSION_TABLE");
}

//DEPRECTATED - provided for compatability - aim to eliminate these.
function fof_safe_query(/* $query, [$args...]*/){
	global $fof_connection;
    $args  = func_get_args();
    $query = array_shift($args);
    if(is_array($args[0])) $args = $args[0];
	//transform the sprintf % syntax into PDO placeholders.
	$query = preg_replace("/'?%[sdfg]'?/", '?', $query);
	
	$stmnt = null;
	$t1 = microtime(true);
	try {
		$stmnt = $fof_connection->prepare($query);
		$stmnt->execute($args);
		$t2 = microtime(true);
		$elapsed = $t2 - $t1;
		$num = $stmnt->rowCount();
		$log_msg = sprintf('%.3f: [%s] (%d affected)', $elapsed, $query, $num);
		fof_log($log_msg, 'safe query (deprecated)');
	} catch (PDOException $e) {}
    return $stmnt;
}

//DEPRECATED
function fof_private_safe_query(/*$query, $substitutions,[$args]*/){
	//essentially does the same thing as fof_safe_query, except that it replaces
	//the substitutions with XXXX in the log (ie so we don't expose session ids, etc)
	global $fof_connection;
	$args = func_get_args();
	$query = array_shift($args);
	$subs = array_shift($args);
	if (is_array($args[0])) $args = $args[0];
	//convert placeholder syntax
	$pdo_query = preg_replace("/'?%[sdfg]'?/", '?', $query);
	$censored_args = array_replace($args, $subs);
	$censored_query = vsprintf($query, $censored_args);
	
	$stmnt = null;
	$t1 = microtime(true);
	try {
		$stmnt = $fof_connection->prepare($pdo_query);
		$stmnt->execute($args);
		$t2 = microtime(true);
		$num = $stmnt->rowCount();
		$elapsed = $t2 - $t1;
		$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $censored_query, $num);
    	fof_log($logmessage, 'private query (deprecated)');
	} catch (PDOException $e) {}
	
	return $stmnt;
	
}
//DEPRECATED
function fof_db_query($sql, $live=0){   
    global $fof_connection;
    
    $t1 = microtime(true);
	$result = null;
	try {
		$result = $fof_connection->query($sql);   

    	if ($result instanceof PDOStatement) {
			$num = $result->rowCount();
   		}
    
    	$t2 = microtime(true);
    	$elapsed = $t2 - $t1;
    	$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $sql, $num);
    	fof_log($logmessage, 'query (deprecated)');
	} catch (PDOException $e) {
		if (!$live) {
			die('Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? Database says: <b>'. $e->getMessage() . '</b>');
		}
	}
	return $result;
}

function fof_query_log($sql, $params, $dieOnErrors=True){
	global $fof_connection;
	$t1 = microtime(true);
	try {
		$result = $fof_connection->prepare($sql);
		$result->execute($params);
	} catch (PDOException $e) {
		if ($dieOnErrors){
			die('Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? Database says: <b>'. $e->getMessage() . '</b>');
		}
	}
	$t2 = microtime(true);
	$elapsed = $t2 - $t1;
	$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $result->queryString, $result->rowCount());
	fof_log($logmessage, 'pdo query');
	return $result;
}

function fof_prepare_query_log($sql){
	global $fof_connection;
	$stmnt = $fof_connection->prepare($sql);
	$retf = function($params) use($stmnt){
		$t1 = microtime(true);
		$stmnt->execute($params);
		$t2 = microtime(true);
		$elapsed = $t2 - $t1;
		$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $stmnt->queryString, $stmnt->rowCount());
		fof_log($logmessage, 'pdo query');
		return $stmnt;
	};
	return $retf;
}

function fof_query_log_get_id($sql, $params, $table, $id_param){
	//performs a query, returning the result and the last insert id
	//while trying to prevent race conditions
	global $fof_connection;
	$t1 = microtime(true);
	try {
		$fof_connection->beginTransaction();
		$result = $fof_connection->prepare($sql);
		$result->execute($params);
		//get the id of the last insert
		$id = null;
		switch (FOF_DB_TYPE){
			case 'mysql':
			$id = $fof_connection->lastInsertId();
			break;
			
			case 'pgsql':
			$id = $fof_connection->lastInsertId("{$table}_{$id_param}_seq");
			break;
			
			default:
			$temp_res = $fof_connection->prepare("SELECT :id_param FROM :table ORDER BY :id_param DESC limit 0,1");
			$temp_res->execute(array('table' => $table,
									'id_param' => $id_param));
			$arr = $temp_res->fetch(PDO::FETCH_ASSOC);
			$id = $arr[$id_param];
		}
		$fof_connection->commit();
		
	} catch (PDOException $e) {
		$fof_connection->rollBack();
		die('Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? Database says: <b>'. $e->getMessage() . '</b>');
	}
	$t2 = microtime(true);
	$elapsed = $t2 - $t1;
	$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $result->queryString, $result->rowCount());
	fof_log($logmessage, 'pdo query');
	return array($result, $id);
}

function fof_db_get_row($result) {
	$ret = array();
    if ($result instanceof PDOStatement){
    	$ret = $result->fetch(PDO::FETCH_ASSOC);
    }
    return $ret;
}

////////////////////////////////////////////////////////////////////////////////
// Feed level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_feed_mark_cached($feed_id) {
    global $FOF_FEED_TABLE;
	return fof_query_log("update $FOF_FEED_TABLE set feed_cache_date = ? where feed_id = ?", array(time(), $feed_id));
}

function fof_db_feed_mark_attempted_cache($feed_id) {
    global $FOF_FEED_TABLE;
	return fof_query_log("update $FOF_FEED_TABLE set feed_cache_attempt_date = ? where feed_id = ?", array(time(), $feed_id));
}

function fof_db_feed_update_metadata($feed_id, $url, $title, $link, $description, $image, $image_cache_date){
    global $FOF_FEED_TABLE;
    
    $sql = "update $FOF_FEED_TABLE set feed_url = ?, feed_title = ?, feed_link = ?, feed_description = ?";
    $args = array($url, $title, $link, $description);
    
	if($image) {
		$sql .= ", feed_image = ? ";
        $args[] = $image;
	} else {
		$sql .= ", feed_image = NULL ";
	}
	
    $sql .= ", feed_image_cache_date = ? ";
    $args[] = $image_cache_date;
    
	$sql .= "where feed_id = ?";
    $args[] = $feed_id;
    
	$result = fof_query_log($sql, $args);
	return $result;
}

function fof_db_get_latest_item_age(){
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TABLE;
    
	$result = fof_query_log("SELECT max( item_cached ) AS \"max_date\", $FOF_ITEM_TABLE.feed_id as \"id\" FROM $FOF_ITEM_TABLE GROUP BY $FOF_ITEM_TABLE.feed_id", null);
	return $result;	
}

function fof_db_get_subscriptions($user_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    return fof_query_log("select * from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = ? and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id order by feed_title", array($user_id));
}

function fof_db_get_feeds() {
    global $FOF_FEED_TABLE;
    return fof_query_log("select * from $FOF_FEED_TABLE order by feed_title", null);
}

function fof_db_get_item_count($user_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return fof_query_log("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.user_id = ? and $FOF_ITEM_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id group by id", array($user_id));
}

function fof_db_get_unread_item_count($user_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return fof_query_log("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE where $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and  $FOF_ITEM_TAG_TABLE.tag_id = 1 and $FOF_ITEM_TAG_TABLE.user_id = ? and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id and $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id group by id", array($user_id));
}

function fof_db_get_starred_item_count($user_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    return fof_query_log("select count(*) as count, $FOF_ITEM_TABLE.feed_id as id from $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_FEED_TABLE where $FOF_ITEM_TABLE.item_id = $FOF_ITEM_TAG_TABLE.item_id and $FOF_SUBSCRIPTION_TABLE.user_id = $user_id and  $FOF_ITEM_TAG_TABLE.tag_id = 2 and $FOF_ITEM_TAG_TABLE.user_id = ? and $FOF_FEED_TABLE.feed_id = $FOF_SUBSCRIPTION_TABLE.feed_id and $FOF_ITEM_TABLE.feed_id = $FOF_FEED_TABLE.feed_id group by id", array($user_id));
}

function fof_db_get_subscribed_users($feed_id) {
    global $FOF_SUBSCRIPTION_TABLE;
    
    return fof_query_log("select user_id from $FOF_SUBSCRIPTION_TABLE where $FOF_SUBSCRIPTION_TABLE.feed_id = ?", array($feed_id));
}

function fof_db_is_subscribed($user_id, $feed_url) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select $FOF_SUBSCRIPTION_TABLE.feed_id from $FOF_FEED_TABLE, $FOF_SUBSCRIPTION_TABLE where feed_url= ? and $FOF_SUBSCRIPTION_TABLE.feed_id = $FOF_FEED_TABLE.feed_id and $FOF_SUBSCRIPTION_TABLE.user_id = ?", array($feed_url, $user_id));
    
    if($result->rowCount() == 0) {
        return false;
    }
    
    return true;
}

function fof_db_get_feed_by_url($feed_url) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select * from $FOF_FEED_TABLE where feed_url=?", array($feed_url));
    
    if($result->rowCount() == 0)
    {
        return NULL;
    }
    
    $row = fof_db_get_row($result);
    
    return $row;
}

function fof_db_get_feed_by_id($feed_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select * from $FOF_FEED_TABLE where feed_id=?", array($feed_id));
    
    $row = fof_db_get_row($result);
    
    return $row;
}

function fof_db_add_feed($url, $title, $link, $description) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;
    
    list($res,$id) =fof_query_log_get_id("INSERT into $FOF_FEED_TABLE (feed_url,feed_title,feed_link,feed_description) values (?, ?, ?, ?)", 
    									array($url, $title, $link, $description),
    									$FOF_FEED_TABLE,
    									'feed_id');
    
    return $id;
}

function fof_db_add_subscription($user_id, $feed_id) {
    global $FOF_SUBSCRIPTION_TABLE;
    
    fof_query_log("insert into $FOF_SUBSCRIPTION_TABLE (feed_id, user_id) values (?, ?)", array($feed_id, $user_id));
}

function fof_db_delete_subscription($user_id, $feed_id) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_ITEM_TAG_TABLE;
        
	$result = fof_db_get_items($user_id, $feed_id, $what="all", NULL, NULL);
    
    foreach($result as $r) {
        $items[] = $r['item_id'];
    }
    
    $itemclause = join(", ", $items); //no sql inj, these are trusted
    fof_query_log("delete from $FOF_SUBSCRIPTION_TABLE where feed_id = ? and user_id = ?", array($feed_id, $user_id));
    fof_query_log("delete from $FOF_ITEM_TAG_TABLE where user_id = ? and item_id in ($itemclause)", array($user_id));
}

function fof_db_delete_feed($feed_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE;
    
    fof_query_log("delete from $FOF_FEED_TABLE where feed_id = ?", array($feed_id));
    fof_query_log("delete from $FOF_ITEM_TABLE where feed_id = ?", array($feed_id));
}


////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_find_item($feed_id, $item_guid) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE, $fof_connection;
    
    $result = fof_query_log("select item_id from $FOF_ITEM_TABLE where feed_id=? and item_guid=?", array($feed_id, $item_guid));
    if($result->rowCount() == 0) {
        return null;
    }
    else {
    	$row = fof_db_get_row($result);
        return($row['item_id']);
    }
}

//this function gets called a lot.  Would be nice to optimise it
function fof_db_add_item($feed_id, $guid, $link, $title, $content, $cached, $published, $updated) {
    global $FOF_ITEM_TABLE, $fof_connection;
    
    list($res,$id) = fof_query_log_get_id("insert into $FOF_ITEM_TABLE (feed_id, item_link, item_guid, item_title, item_content, item_cached, 	item_published, item_updated) values (?, ?, ? ,?, ?, ?, ?, ?)",
    										array($feed_id, $link, $guid, $title, $content, $cached, $published, $updated),
    										$FOF_ITEM_TABLE,
    										'item_id');
    
    return $id;
}

function fof_db_get_items($user_id=1, $feed=NULL, $what="unread", $when=NULL, $start=NULL, $limit=NULL, $order="desc", $search=NULL) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    
    if(!is_null($when) && $when != "")
    {
        if($when == "today")
        {
            $whendate = fof_todays_date();
        }
        else
        {
            $whendate = $when;
        }
        
        $whendate = explode("/", $whendate);
        $begin = gmmktime(0, 0, 0, $whendate[1], $whendate[2], $whendate[0]) - ($offset * 60 * 60);
        $end = $begin + (24 * 60 * 60);
    }
    
    if(is_numeric($start))
    {
        if(!is_numeric($limit))
        {
            $limit = intval($prefs["howmany"]);
        }
        
        $limit_clause = " limit $start, $limit ";
    }
    
    $args = array();
    $select = "SELECT i.* , f.* ";
    $from = "FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s ";
    $where = sprintf("WHERE s.user_id = %d AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id ", $user_id);
 
    if(!is_null($feed) && $feed != "")
    {
        $where .= sprintf("AND f.feed_id = %d ", $feed);
    }
    
    if(!is_null($when) && $when != "")
    {
        $where .= sprintf("AND i.item_published > %d and i.item_published < %d ", $begin, $end);
    }
    
    if($what != "all")
    {
        $tags = split(" ", $what);
        $in = implode(", ", array_fill(0, count($tags), '?'));
        $from .= ", $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it ";
        $where .= sprintf("AND it.user_id = %d ", $user_id);
        $where .= "AND it.tag_id = t.tag_id AND ( t.tag_name IN ( $in ) ) AND i.item_id = it.item_id ";
        $group = sprintf("GROUP BY i.item_id HAVING COUNT( i.item_id ) = %d ", count($tags));
        $args = array_merge($args, $tags);
    }
    
    if(!is_null($search) && $search != "")
    {
        $where .= "AND (i.item_title like ? or i.item_content like ? )";
        $args[] = $search;
        $args[] = $search;
    }
    
    $order_by = "order by i.item_published desc $limit_clause ";
    
    $query = $select . $from . $where . $group . $order_by;
    
    $result = fof_query_log($query, $args);
    
    if($result->rowCount() == 0) {
        return array();
    }
    	
    while($row = fof_db_get_row($result)) {
        $array[] = $row;
    }
    
    $array = fof_multi_sort($array, 'item_published', $order != 'asc');
    
    $i = 0;
    foreach($array as $item)
    {
        $ids[] = $item['item_id'];
        $lookup[$item['item_id']] = $i;
        $array[$i]['tags'] = array();
        
        $i++;
    }
    
    //can't do implode($ids, ', '), because PDO will escape most of the arguments (except the first)
    $retf = fof_prepare_query_log("select $FOF_TAG_TABLE.tag_name, $FOF_ITEM_TAG_TABLE.item_id from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = :id and $FOF_ITEM_TAG_TABLE.user_id = :userid");
    
    $params = array('userid' => $user_id);
    foreach ($ids as $id){
    	$params['id'] = $id;
    	$result = $retf($params);
    	while ($row = fof_db_get_row($result)){
    		$item_id = $row['item_id'];
        	$tag = $row['tag_name'];
        	$array[$lookup[$item_id]]['tags'][] = $tag;
    	}
    }

    return $array;
}

function fof_db_get_item($user_id, $item_id) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    
    $query = "select $FOF_FEED_TABLE.feed_image as feed_image, $FOF_FEED_TABLE.feed_title as feed_title, $FOF_FEED_TABLE.feed_link as feed_link, $FOF_FEED_TABLE.feed_description as feed_description, $FOF_ITEM_TABLE.item_id as item_id, $FOF_ITEM_TABLE.item_link as item_link, $FOF_ITEM_TABLE.item_title as item_title, $FOF_ITEM_TABLE.item_cached, $FOF_ITEM_TABLE.item_published, $FOF_ITEM_TABLE.item_updated, $FOF_ITEM_TABLE.item_content as item_content from $FOF_FEED_TABLE, $FOF_ITEM_TABLE where $FOF_ITEM_TABLE.feed_id=$FOF_FEED_TABLE.feed_id and $FOF_ITEM_TABLE.item_id = ?";
    
    $result = fof_query_log($query, array($item_id));
    
    $item = fof_db_get_row($result);
    
    $item['tags'] = array();
    
	if($user_id) {
		$result = fof_query_log("select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = ? and $FOF_ITEM_TAG_TABLE.user_id = ?", array($item_id, $user_id));

		while($row = fof_db_get_row($result)) {
			$item['tags'][] = $row['tag_name'];
		}
	}
    
    return $item;
}

////////////////////////////////////////////////////////////////////////////////
// Tag stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_subscription_to_tags() {
    $r = array();
    global $FOF_SUBSCRIPTION_TABLE;
    $result = fof_query_log("select * from $FOF_SUBSCRIPTION_TABLE", null);
    while($row = fof_db_get_row($result)) {
        $prefs = unserialize($row['subscription_prefs']);
        $tags = $prefs['tags'];
        if(!is_array($r[$row['feed_id']])) $r[$row['feed_id']] = array();
        $r[$row['feed_id']][$row['user_id']] = $tags;
    }
    
    return $r;    
}

function fof_db_tag_feed($user_id, $feed_id, $tag_id) {
    global $FOF_SUBSCRIPTION_TABLE;
    
    $result = fof_query_log("select subscription_prefs from $FOF_SUBSCRIPTION_TABLE where feed_id = ? and user_id = ?", array($feed_id, $user_id));
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    
    if(!is_array($prefs['tags']) || !in_array($tag_id, $prefs['tags'])) $prefs['tags'][] = $tag_id;
    
    fof_query_log("update $FOF_SUBSCRIPTION_TABLE set subscription_prefs = ? where feed_id = ? and user_id = ?", array(serialize($prefs), $feed_id, $user_id));
}

function fof_db_untag_feed($user_id, $feed_id, $tag_id) {
    global $FOF_SUBSCRIPTION_TABLE;
    
    $result = fof_query_log("select subscription_prefs from $FOF_SUBSCRIPTION_TABLE where feed_id = ? and user_id = ?", array($feed_id, $user_id));
    $row = fof_db_get_row($result);
    $prefs = unserialize($row['subscription_prefs']);
    
    if(is_array($prefs['tags'])) {
        $prefs['tags'] = array_diff($prefs['tags'], array($tag_id));
    }
    
    fof_query_log("update $FOF_SUBSCRIPTION_TABLE set subscription_prefs = ? where feed_id = ? and user_id = ?", array(serialize($prefs), $feed_id, $user_id));
}

function fof_db_get_item_tags($user_id, $item_id) {
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select $FOF_TAG_TABLE.tag_name from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id = ? and $FOF_ITEM_TAG_TABLE.user_id = ?", array($item_id, $user_id));
    
    return $result;   
}

function fof_db_item_has_tags($item_id) {
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where item_id=? and tag_id <= 2", array($item_id));
    $row = fof_db_get_row($result);
    
    return $row['count'];
}

function fof_db_get_unread_count($user_id) {
    global $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where tag_id = 1 and user_id = ?", array($user_id)); 
    $row = fof_db_get_row($result);
    
    return $row['count'];
}

function fof_db_get_tag_unread($user_id) {
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;

    $result = fof_query_log("SELECT count(*) as count, it2.tag_id FROM $FOF_ITEM_TABLE i, $FOF_ITEM_TAG_TABLE it , $FOF_ITEM_TAG_TABLE it2 where it.item_id = it2.item_id and it.tag_id = 1 and i.item_id = it.item_id and i.item_id = it2.item_id and it.user_id = ? and it2.user_id = ? group by it2.tag_id", array($user_id, $user_id));
    
    $counts = array();
    while($row = fof_db_get_row($result)) {
        $counts[$row['tag_id']] = $row['count'];
    }
    
    return $counts;
}

function fof_db_get_tags($user_id) {
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $sql = "SELECT $FOF_TAG_TABLE.tag_id, $FOF_TAG_TABLE.tag_name, count( $FOF_ITEM_TAG_TABLE.item_id ) as count
        FROM $FOF_TAG_TABLE
        LEFT JOIN $FOF_ITEM_TAG_TABLE ON $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id
        WHERE $FOF_ITEM_TAG_TABLE.user_id = ?
        GROUP BY $FOF_TAG_TABLE.tag_id order by $FOF_TAG_TABLE.tag_name";
    
    $result = fof_query_log($sql, array($user_id));
    
    return $result;   
}

function fof_db_get_tag_id_map() {
    global $FOF_TAG_TABLE;
    
    $sql = "select * from $FOF_TAG_TABLE";
    $result = fof_query_log($sql,null);
    $tags = array();
    while($row = fof_db_get_row($result)) {
        $tags[$row['tag_id']] = $row['tag_name'];
    }
    
    return $tags;   
}

function fof_db_create_tag($user_id, $tag) {
    global $FOF_TAG_TABLE, $fof_connection;
    
    list($res,$id) =fof_query_log_get_id("insert into $FOF_TAG_TABLE (tag_name) values (?)", 
    									array($tag),
    									$FOF_TAG_TABLE,
    									'tag_id');
    
    return $id;
}

function fof_db_get_tag_by_name($user_id, $tag) {
    global $FOF_TAG_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
    
    $result = fof_query_log("select $FOF_TAG_TABLE.tag_id from $FOF_TAG_TABLE where $FOF_TAG_TABLE.tag_name = ?", array($tag));
    if($result->rowCount() == 0) {
        return null;
    }
    $row = fof_db_get_row($result);
    
    return $row['tag_id'];
}

function fof_db_mark_unread($user_id, $items) {
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_read($user_id, $items) {
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_read($user_id, $feed_id) {
	//this is the sort of function that could benefit from a better way
	//of storing the tags
    global $FOF_ITEM_TAG_TABLE;
    
    $result = fof_db_get_items($user_id, $feed_id, $what="all");
    
    foreach($result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_untag_items($user_id, 1, $items);
}

function fof_db_mark_feed_unread($user_id, $feed, $what)
{
    global $FOF_ITEM_TAG_TABLE;
    
    fof_log("fof_db_mark_feed_unread($user_id, $feed, $what)");
    
    if($what == "all")
    {
        $result = fof_db_get_items($user_id, $feed, "all");
    }
    if($what == "today")
    {
        $result = fof_db_get_items($user_id, $feed, "all", "today");
    }
    
    foreach((array)$result as $r)
    {
        $items[] = $r['item_id'];
    }
    
    fof_db_tag_items($user_id, 1, $items);
}

function fof_db_mark_item_unread($users, $id) {
    global $FOF_ITEM_TAG_TABLE;
    
    if(count($users) == 0) return;
    
    foreach($users as $user) {
        $sql[] = sprintf("(%d, 1, %d)", $user, $id);
    }
    
    $values = implode ( ",", $sql );
    
	$sql = "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) values " . $values;
	
	$result = fof_query_log($sql, null);
}

function fof_db_tag_items($user_id, $tag_id, $items) {
    global $FOF_ITEM_TAG_TABLE;

    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);

    foreach($items as $item)
    {
        $sql[] = sprintf("(%d, %d, %d)", $user_id, $tag_id, $item);
    }
    
    $values = implode ( ",", $sql );
    
	$sql = "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) values " . $values;
	
	$result = fof_query_log($sql, null);
}

function fof_db_untag_items($user_id, $tag_id, $items) {
    global $FOF_ITEM_TAG_TABLE;
    
    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);
    
    foreach($items as $item)
    {
        $sql[] = " item_id = ? ";
        $args[] = $item;
    }
    
    $values = implode ( " or ", $sql );
    
    $sql = "delete from $FOF_ITEM_TAG_TABLE where user_id = ? and tag_id = ? and ( $values )";
    
    array_unshift($args, $tag_id);
    array_unshift($args, $user_id);

    fof_query_log($sql, $args);
}


////////////////////////////////////////////////////////////////////////////////
// User stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_get_users() {
    global $FOF_USER_TABLE;
    
    $result = fof_query_log("select user_name, user_id, user_prefs from $FOF_USER_TABLE",null);
    while($row = fof_db_get_row($result)) {
        $users[$row['user_id']['user_name']] = $row['user_name'];
        $users[$row['user_id']['user_prefs']] = unserialize($row['user_prefs']);
    }
}

function fof_db_add_user($username, $password) {
    global $FOF_USER_TABLE;
	
	//check if username already exists
	$result = fof_query_log("SELECT user_id from $FOF_USER_TABLE where user_name= ?", array($username));
	if ($result->rowCount() > 0){
		return False;
	} else {
		$salt = fof_make_salt();
		$password_hash = md5($password . $salt);  //update this to blowfish
		fof_query_log("insert into $FOF_USER_TABLE (user_name, user_password_hash, salt) values (?, ?, ?)", array($username, $password_hash, $salt));
		return True;
	}
    
	
}

function fof_db_change_password($username, $password) {
    global $FOF_USER_TABLE;
    $salt = fof_make_salt();
    
	$password_hash = md5($password . $salt); //update to blowfish
    
	fof_query_log("update $FOF_USER_TABLE set user_password_hash = ?, salt=? where user_name = ?", array($password_hash, $salt, $username));
}

function fof_db_get_user_id($username) {
    global $FOF_USER_TABLE;
    $result = fof_query_log("select user_id from $FOF_USER_TABLE where user_name = ?", array($username));
    $row = fof_db_get_row($result);
    
    return $row['user_id'];
}

function fof_db_delete_user($username) {
    global $FOF_USER_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
    $user_id = fof_db_get_user_id($username);
    
    $args = array($user_id);
    fof_query_log("delete from $FOF_SUBSCRIPTION_TABLE where user_id = ?", $args);
    fof_query_log("delete from $FOF_ITEM_TAG_TABLE where user_id = ?", $args);
    fof_query_log("delete from $FOF_USER_TABLE where user_id = ?", $args);
}

function fof_db_save_prefs($user_id, $prefs) {
    global $FOF_USER_TABLE;
    
    $prefs = serialize($prefs);
    
    fof_query_log("update $FOF_USER_TABLE set user_prefs = ? where user_id = ?", array($prefs, $user_id));
}

function fof_db_authenticate($user_name, $password){
    global $FOF_USER_TABLE;
    
    $result = fof_query_log("select * from $FOF_USER_TABLE where user_name = ?", array($user_name));
    if ($result instanceof PDOStatement){
    	if($result->rowCount() == 0)
    	{
        	return false;
    	}
    
    	$row = fof_db_get_row($result);
    	$computedHash = md5($password . $row['salt']); //update to blowfish
    	if ($computedHash === $row['user_password_hash']){
    		$_SESSION['user_name'] = $row['user_name'];
    		$_SESSION['user_id'] = $row['user_id'];
    		$_SESSION['user_level'] = $row['user_level'];
    		$_SESSION['authenticated'] = True;
    		return True;
   		}
   	}
    return False;
}

function fof_db_place_cookie($oldToken, $newToken, $uid, $user_agent){
	global $FOF_COOKIE_TABLE;
	// clear previous cookie if there is one.  It is possible, though unlikely, that another user may have the same
	// token value.  Thus we must delete ALL the records with the old token value, then insert the new record
	// and NOT simply do an update.  This will slightly inconvenience the second user, who will have to (re) log in,
	// but will guarantee that 2nd user doesn't get access to first user's account.
	//also delete any tokens with the new value - see bug report 180
	$args[] = sha1($newToken);
	$query = "DELETE from $FOF_COOKIE_TABLE where token_hash=?";
	if ($oldToken) {
		$args[] = sha1($oldToken);
		$query .= " or token_hash=?";
	}
	$result = fof_query_log($query, $args);
	$censors[] = 'XXX token_hash XXX';
	$result = fof_private_safe_query("INSERT into $FOF_COOKIE_TABLE (token_hash, user_id, user_agent_hash) VALUES ('%s', %d, '%s')", $censors, sha1($newToken), $uid, sha1($user_agent));
	return True;
}

function fof_db_validate_cookie($token, $userAgent){
	global $FOF_COOKIE_TABLE, $FOF_USER_TABLE;
	$result = fof_query_log("SELECT * from $FOF_COOKIE_TABLE where token_hash=?",array(sha1($token)));
	if ($result instanceof PDOStatement){
		if ($result->rowCount() > 0){
			$row = fof_db_get_row($result);
			if (sha1($userAgent) === $row['user_agent_hash']){
				$uid = $row['user_id'];
				$result = fof_query_log("SELECT * from $FOF_USER_TABLE where user_id=?", array($uid));
				if ($result->rowCount() > 0){
					return fof_db_get_row($result);
				}
			}
		}
	}
	return False;
}

function fof_db_logout_everywhere(){
	global $FOF_COOKIE_TABLE;
	fof_query_log("DELETE from $FOF_COOKIE_TABLE where user_id=?", array(fof_current_user()));
	//do we need an index on user_id? Should probably add one
}

function fof_db_delete_cookie($token){
	global $FOF_COOKIE_TABLE;
	return fof_query_log("DELETE from $FOF_COOKIE_TABLE where token_hash='%s'", array(sha1($token)));
}

function fof_db_open_session(){
	global $fof_connection;
	if (!$fof_connection){
		fof_db_connect();
		//$fof_connection = fof_db_connect();
	}
	return $fof_connection;
	
}

function fof_db_close_session(){
    return True;
}

function fof_db_read_session($id){
	global $FOF_SESSION_TABLE;
	$censors[] = 'XXX session_id XXX';
    $result = fof_private_safe_query("SELECT data from $FOF_SESSION_TABLE where id='%s'", $censors, $id);
    if ($result->rowCount()){
    	$record = fof_db_get_row($result);
    	return $record['data'];
    }
    return '';
}

function fof_db_write_session($id, $data){
	fof_db_connect(); //I DO NOT UNDERSTAND WHY THIS IS NECESSARY
	//SEEMS LIKE $fof_connection is getting garbage collected awyay
	global $FOF_SESSION_TABLE;  
    $access = time();
    $censors = array('XXX session_id XXX');
	return fof_private_safe_query("REPLACE into $FOF_SESSION_TABLE VALUES ('%s', '%d', '%s')", $censors, $id, $access, $data);
}

function fof_db_destroy_session($id){
	global $FOF_SESSION_TABLE;
    return fof_query_log("DELETE from $FOF_SESSION_TABLE where id=?", array($id));
}

function fof_db_clean_session($max){
	global $FOF_SESSION_TABLE;
    $old = time() - $max;
	return fof_query_log("DELETE from $FOF_SESSION_TABLE where access < ?", array($old));
}

?>
