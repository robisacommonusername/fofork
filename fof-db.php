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
$FOF_CONFIG_TABLE = FOF_CONFIG_TABLE;

$FOF_TABLES_ARRAY = array(
	'$FOF_FEED_TABLE' => FOF_FEED_TABLE,
	'$FOF_ITEM_TABLE' => FOF_ITEM_TABLE,
	'$FOF_ITEM_TAG_TABLE' => FOF_ITEM_TAG_TABLE,
	'$FOF_SUBSCRIPTION_TABLE' => FOF_SUBSCRIPTION_TABLE,
	'$FOF_TAG_TABLE' => FOF_TAG_TABLE,
	'$FOF_USER_TABLE' => FOF_USER_TABLE,
	'$FOF_COOKIE_TABLE' => FOF_COOKIE_TABLE,
	'$FOF_SESSION_TABLE' => FOF_SESSION_TABLE,
	'$FOF_CONFIG_TABLE' => FOF_CONFIG_TABLE
);

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

function fof_query($sql, $params, $dieOnErrors=True){
	global $fof_connection;
	try {
		$result = $fof_connection->prepare($sql);
		$result->execute($params);
	} catch (PDOException $e) {
		if ($dieOnErrors){
			die('Cannot query database.  Have you run <a href=\"install.php\"><code>install.php</code></a> to create or upgrade your installation? Database says: <b>'. $e->getMessage() . '</b>');
		} else {
			throw $e;
		}
	}
	return $result;
}

function fof_query_log($sql, $params, $dieOnErrors=True){
	$t1 = microtime(true);
	$result = fof_query($sql, $params, $dieOnErrors);
	$t2 = microtime(true);
	$elapsed = $t2 - $t1;
	$query = fof_fix_query_string($sql, $params);
	$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $query, $result->rowCount());
	fof_log($logmessage, 'pdo query');
	return $result;
}

function fof_query_log_private($sql, $params, $censors, $dieOnErrors=True){
	$t1 = microtime(true);
	$result = fof_query($sql, $params, $dieOnErrors);
	$t2 = microtime(true);
	$elapsed = $t2 - $t1;
	foreach ($censors as $field => $censorText){
		$params[$field] = $censorText;
	}
	$query = fof_fix_query_string($sql, $params);
	$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $query, $result->rowCount());
	fof_log($logmessage, 'private pdo query');
	return $result;
	
}

//returns a closure that can re-execute a prepared statement
function fof_prepare_query_log($sql){
	global $fof_connection;
	$stmnt = $fof_connection->prepare($sql);
	return function($params) use ($stmnt){
		$t1 = microtime(true);
		$result = $stmnt->execute($params);
		$t2 = microtime(true);
		$logmessage = sprintf('%.3f: [%s] (%d affected)', $t2 - $t1, $stmnt->queryString, $stmnt->rowCount());
		fof_log($log_message, 'prepared pdo query');
		return $result;
	};
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
			$temp_res = $fof_connection->prepare("SELECT :id_param FROM :table ORDER BY :id_param DESC limit 1 OFFSET 0");
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
	$queryString = fof_fix_query_string($sql, $params);
	$logmessage = sprintf('%.3f: [%s] (%d affected)', $elapsed, $queryString, $result->rowCount());
	fof_log($logmessage, 'pdo query');
	return array($result, $id);
}

function fof_db_table_list() {
	$ret = array();
	switch (FOF_DB_TYPE){
		case 'pgsql':
		$result = fof_query_log("select table_name from information_schema.tables where table_schema='public'", null);
		while ($row = fof_db_get_row($result)){
			$ret[] = $row['table_name'];
		}
		break;
		
		//mysql atm
		default:
		$result = fof_query_log("SHOW TABLES", null);
		while ($row = fof_db_get_row($result)){
			$names = array_values($row);
			$ret[] = $names[0];
		}
	}
	return $ret;
}

function fof_fix_query_string($query, $subs){
	//turn pdo placeholders into the full query string with params
	//substituted (used for logging)
	return preg_replace_callback('/(:\\w*)|[?]/',function($matches) use($subs){
		static $i = 0;
		if ($matches[0] === '?'){
			return $subs[$i++];
		} else {
			$paramName = substr($matches[0], 1);
			if (array_key_exists($paramName, $subs)){
				return $subs[$paramName];
			} else {
				return '?';
			}
		}
	}, $query);
}

function fof_db_get_row($result) {
	$ret = array();
    if ($result instanceof PDOStatement){
    	$ret = $result->fetch(PDO::FETCH_ASSOC);
    }
    return $ret;
}
//Deprecate??
function fof_db_get_all_rows($result) {
	$ret = array();
	if ($result instanceof PDOStatement){
		$ret = $result->fetchAll(PDO::FETCH_ASSOC);
	}
	return $ret;
}

function fof_db_optimize() {
	global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE, $FOF_COOKIE_TABLE, $FOF_SESSION_TABLE, $FOF_CONFIG_TABLE;
    
	fof_query_log("optimize table $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE, $FOF_TAG_TABLE, $FOF_USER_TABLE, $FOF_COOKIE_TABLE, $FOF_SESSION_TABLE, $FOF_CONFIG_TABLE", null);
}

function fof_db_get_version() {
	global $FOF_CONFIG_TABLE;
	
	$result = fof_query_log("SELECT val from $FOF_CONFIG_TABLE where param = 'version'", null);
	if ($row = fof_db_get_row($result)) {
		return $row['val'];
	}
	return '1.1';
}

function fof_db_log_password() {
	global $FOF_CONFIG_TABLE;
	static $password;
	
	if (isset($password)){
		return $password;
	}
	$result = fof_query("SELECT val from $FOF_CONFIG_TABLE where param = 'log_password'", null);
	if ($row = fof_db_get_row($result)) {
		$password = $row['val'];
	} else {
		$password = FOF_DB_PASS;
	}
	return $password;
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
    
    $placeholders = implode(', ', array_fill(0,count($items),'?'));
    array_unshift($items, $user_id);
    fof_query_log("delete from $FOF_SUBSCRIPTION_TABLE where feed_id = ? and user_id = ?", array($feed_id, $user_id));
    fof_query_log("delete from $FOF_ITEM_TAG_TABLE where user_id = ? and item_id in ($placeholders)", $items);
}

function fof_db_delete_feed($feed_id) {
    global $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_SUBSCRIPTION_TABLE;
    
    $fid_array = array($feed_id);
    fof_query_log("DELETE from $FOF_FEED_TABLE where feed_id = ?", $fid_array);
    fof_query_log("DELETE from $FOF_ITEM_TABLE where feed_id = ?", $fid_array);
    fof_query_log("DELETE from $FOF_SUBSCRIPTION_TABLE where feed_id = ?", $fid_array);
}

function fof_db_purge_feed($ignoreable_items, $feed_id, $purge_days){
	global $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE;
	
	$count = count($ignoreable_items);
	if ($count) {
		$in_placeholders = implode(', ', array_fill(0,$count,'?'));
    	array_unshift($ignoreable_items, $feed_id);
    	$sql = "select item_id, item_cached from $FOF_ITEM_TABLE where feed_id = ? and item_id not in ($in_placeholders) order by item_cached desc limit 1000000000 OFFSET $count";
    } else {
    	$ignoreable_items = array($feed_id);
    	$sql = "select item_id, item_cached from $FOF_ITEM_TABLE where feed_id = ? order by item_cached desc limit 1000000000 OFFSET $count";
    }
    
    $result = fof_query_log($sql, $ignoreable_items);
            
    //items older than purge age which HAVE NOT been tagged will be deleted
    $now = time();
    $item_num_tags = fof_prepare_query_log("select count(*) as \"count\" from $FOF_ITEM_TAG_TABLE where item_id=? and tag_id <= 2");
    while($row = fof_db_get_row($result)) {
		if($row['item_cached'] < ($now - ($purge_days * 24 * 60 * 60))) {
			$num = fof_db_get_row($item_num_tags(array($row['item_id'])));
			if(!$num['count']) {
				$delete[] = $row['item_id'];
			}
		}
	}
            
	$ndelete = count($delete);
	if($ndelete) {
		$in_placeholders = implode(', ', array_fill(0,$ndelete,'?'));
		fof_query_log("DELETE from $FOF_ITEM_TABLE where item_id in ($in_placeholders)", $delete);
		fof_query_log("DELETE from $FOF_ITEM_TAG_TABLE where item_id in ($in_placeholders)", $delete);
	}
	
	return $ndelete;
}


////////////////////////////////////////////////////////////////////////////////
// Item level stuff
////////////////////////////////////////////////////////////////////////////////

function fof_db_add_items($feed_id, $items) {
	//items is an array of simplepie item objects
	
	if (count($items) == 0){
		return array();
	}
	
	//the combination of feed_id + item_guid is unique, but neither the item_guid or the feed_id are unique
	//this makes it hard to do on duplicate key update.  grrr...
	
	global $FOF_ITEM_TABLE, $fof_connection;
	//technically need to do all queries in a transaction to prevent race conditions
	//ie don't want to add same item twice due to race conditions
	$fof_connection->beginTransaction();
	
	$fid = intval($feed_id); //prevent sql inj here
	$guids = array_map(function($item){return $item->get_id() ? $item->get_id() : $item->get_permalink();}, $items);
	$placeholders = implode(', ', array_fill(0, count($guids), '?'));
	$result = fof_query_log("SELECT item_guid from $FOF_ITEM_TABLE where feed_id = $fid and item_guid in ($placeholders)", $guids);
	$existingGuids = array();
	while ($row = fof_db_get_row($result)) {
		$existingGuids[$row['item_guid']] = True;
	}
	
	$newItems = array_values(array_filter($items, function($item) use($existingGuids){
		$guid = $item->get_id() ? $item->get_id() : $item->get_permalink();
		return !array_key_exists($guid, $existingGuids);
	}));
	
	if (count($newItems) == 0){
		$fof_connection->commit();
		return array();
	}
	
	//now insert the new items
	$now = time();
	$placeholder = "($fid, ?, ?, ?, ?, $now, ?, ?)";
	$placeholders = implode(', ', array_fill(0, count($newItems), $placeholder));
	$params = array_reduce($newItems, function($body, $curr){
		$link = $curr->get_permalink();
		$guid = $curr->get_id();
		if (!$guid){
			$guid = $link;
		}
		$date = $curr->get_date('U');
		if (!$date){
			$date = time();
		}
		return array_merge($body, array($link, $guid, $curr->get_title(), $curr->get_content(), $date, $date));
	}, array());
	fof_query_log("INSERT into $FOF_ITEM_TABLE (feed_id, item_link, item_guid, item_title, item_content, item_cached, item_published, item_updated) values $placeholders", $params);
	
	//commit transaction
	$fof_connection->commit();
	
	//need to get the ids of the added items.  on pgsql, could have just used "returning item_id"
	$guids = array_map(function($item) {return $item->get_id() ? $item->get_id() : $item->get_permalink();}, $newItems);
	$guid_placeholders = implode(', ', array_fill(0,count($guids),'?'));
	$result = fof_query_log("SELECT item_id from $FOF_ITEM_TABLE where feed_id = $fid and item_guid in ($guid_placeholders)", $guids);
	
	$ids = array();
	while ($row = fof_db_get_row($result)){
		$ids[] = $row['item_id'];
	}
	
	//mark the new items as unread
	fof_db_mark_items_unread($fid, $ids);
	
	return $ids;

}

function fof_db_get_items($user_id=1, $feed=null, $what='unread', $when=null, $start=null, $limit=null, $order='desc', $search=null) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    
    $prefs = fof_prefs();
    $offset = $prefs['tzoffset'];
    
    if($when != null) {
        if($when == 'today') {
            $whendate = fof_todays_date();
        } else {
            $whendate = $when;
        }
        
        $whendate = explode('/', $whendate);
        $begin = gmmktime(0, 0, 0, $whendate[1], $whendate[2], $whendate[0]) - ($offset * 60 * 60);
        $end = $begin + (24 * 60 * 60);
    }
    
    if(is_numeric($start)) {
        if(!is_numeric($limit)) {
            $limit = $prefs['howmany'];
        }
        $limit_clause = sprintf(' limit %d offset %d ', $limit, $start);;
    }
    
    $args = array();
    $select = 'SELECT i.* , f.* ';
    $from = "FROM $FOF_FEED_TABLE f, $FOF_ITEM_TABLE i, $FOF_SUBSCRIPTION_TABLE s ";
    $where = 'WHERE s.user_id = ? AND s.feed_id = f.feed_id AND f.feed_id = i.feed_id ';
    $args[] = $user_id;
 
    if($feed != null) {
        $where .= 'AND f.feed_id = ? ';
        $args[] = $feed;
    }
    
    if($when != null) {
        $where .= 'AND i.item_published > ? and i.item_published < ? ';
        $args[] = $begin;
        $args[] = $end;
    }
    
    if($what != 'all') {
        $tags = split(' ', $what);
        //there appears to be an escaping bug, at least with pgsql backend
        //instead of ending up with IN ('star', 'unread'), we just get
        //IN (star, unread).  This works on mysql, but fails on postgres
        //therefore, add the quotes manually 
        $in = implode(', ', array_fill(0, count($tags), "'?'"));
        $from .= ", $FOF_TAG_TABLE t, $FOF_ITEM_TAG_TABLE it ";
        $where .= "AND it.user_id = ? AND it.tag_id = t.tag_id AND ( t.tag_name IN ($in) ) AND i.item_id = it.item_id "; 
        $args[] = $user_id;
        $args = array_merge($args, $tags);
        $group = sprintf("GROUP BY i.item_id,f.feed_id HAVING COUNT( i.item_id ) = %d ", count($tags));
    }
    
    if($search != null) {
        $where .= 'AND (i.item_title like ? or i.item_content like ? )';
        $args[] = $search;
        $args[] = $search;
    }
    
    if ($order != 'desc') {
    	$order = 'asc';
    }
    $order_by = "order by i.item_published $order $limit_clause ";
    
    $query = $select . $from . $where . $group . $order_by;
    
    $result = fof_query_log($query, $args);
    
    if ($result->rowCount() == 0) {
        return array();
    }
    
    $i = 0;
    $items = array();
    while ($item = fof_db_get_row($result)) {
    	$items[] = $item;
        $ids[] = $item['item_id'];
        $lookup[$item['item_id']] = $i; //item_id => array index
        $items[$i]['tags'] = array();
        $i++;
    }

    $placeholders = implode(', ', array_fill(0,$i,'?'));
    $ids[] = $user_id;  //just tack on the end
    
    //get the tags.
    $result = fof_query_log("select $FOF_TAG_TABLE.tag_name, $FOF_ITEM_TAG_TABLE.item_id from $FOF_TAG_TABLE, $FOF_ITEM_TAG_TABLE where $FOF_TAG_TABLE.tag_id = $FOF_ITEM_TAG_TABLE.tag_id and $FOF_ITEM_TAG_TABLE.item_id in ($placeholders) and $FOF_ITEM_TAG_TABLE.user_id = ?", $ids);
    
    while ($row = fof_db_get_row($result)){
    	$item_id = $row['item_id'];
    	$tag = $row['tag_name'];
    	$items[$lookup[$item_id]]['tags'][] = $tag;
    }
    $x = count($items);
    fof_log("returned $x items", 'star debug');
    return $items;
}

function fof_db_get_item($user_id, $item_id) {
    global $FOF_SUBSCRIPTION_TABLE, $FOF_FEED_TABLE, $FOF_ITEM_TABLE, $FOF_ITEM_TAG_TABLE, $FOF_TAG_TABLE;
    
    $query = "select $FOF_FEED_TABLE.feed_image as feed_image, $FOF_FEED_TABLE.feed_title as feed_title, $FOF_FEED_TABLE.feed_link as feed_link, $FOF_FEED_TABLE.feed_description as feed_description, $FOF_ITEM_TABLE.item_id as item_id, $FOF_ITEM_TABLE.item_link as item_link, $FOF_ITEM_TABLE.item_title as item_title, $FOF_ITEM_TABLE.item_cached, $FOF_ITEM_TABLE.item_published, $FOF_ITEM_TABLE.item_updated, $FOF_ITEM_TABLE.item_content as item_content from $FOF_FEED_TABLE, $FOF_ITEM_TABLE where $FOF_ITEM_TABLE.feed_id=$FOF_FEED_TABLE.feed_id and $FOF_ITEM_TABLE.item_id = ?";
    
    $result = fof_query_log($query, array($item_id));
    
    $item = fof_db_get_row($result);
    
    $item['tags'] = array();
    
	if ($user_id) {
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

function fof_db_apply_subscription_tags($feed_id, $ids) {
	global $FOF_ITEM_TAG_TABLE, $FOF_SUBSCRIPTION_TABLE;
    
    $result = fof_query_log("select * from $FOF_SUBSCRIPTION_TABLE where feed_id = ?", array($feed_id));
    $values = array();
    while ($row = fof_db_get_row($result)) {
    	$prefs = unserialize($row['subscription_prefs']);
        $tags = $prefs['tags'];
        if (!is_array($tags)) continue;
        foreach ($ids as $item_id) {
        	foreach ($tags as $tag_id){
        		$values[] = sprintf('(%d, %d, %d)', $row['user_id'], $item_id, $tag_id);
        	}
        }
    }
    if (count($values) == 0) return;
    
    $allValues = implode(', ', $values);
    fof_query_log("INSERT into $FOF_ITEM_TAG_TABLE (user_id, item_id, tag_id) values $allValues", null);
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

function fof_db_mark_items_unread($feed_id, $item_ids) {
	global $FOF_ITEM_TAG_TABLE;
	
	$result = fof_db_get_subscribed_users($feed_id);
	$vals = array();
	while ($row = fof_db_get_row($result)){
		foreach ($item_ids as $id){
			$vals[] = sprintf('(%d, %d, 1)', $row['user_id'], $id);
		}
	}
	$allValues = implode(', ', $vals);
	fof_query_log("INSERT into $FOF_ITEM_TAG_TABLE (user_id, item_id, tag_id) values $allValues", null);
}

function fof_db_tag_items($user_id, $tag_id, $items) {
    global $FOF_ITEM_TAG_TABLE;

    if(!$items) return;
    
    if(!is_array($items)) $items = array($items);

    foreach($items as $item)
    {
        $vals[] = sprintf('(%d, %d, %d)', $user_id, $tag_id, $item);
    }
    
    $values = implode ( ',', $vals );
    
	$sql = "insert into $FOF_ITEM_TAG_TABLE (user_id, tag_id, item_id) values $values";
	
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
        $users[$row['user_id']]['user_name'] = $row['user_name'];
        $users[$row['user_id']]['user_prefs'] = unserialize($row['user_prefs']);
    }
}

function fof_db_add_user($username, $password) {
    global $FOF_USER_TABLE;
	
	//check if username already exists
	$result = fof_query_log("SELECT user_id from $FOF_USER_TABLE where user_name= ?", array($username));
	if ($result->rowCount() > 0){
		return False;
	} else {
		$salt = fof_make_bcrypt_salt();
		$password_hash = crypt($password, $salt);
		fof_query_log_private("insert into $FOF_USER_TABLE (user_name, user_password_hash) values (?, ?)", array($username, $password_hash), array(1 => 'XXX password_hash XXX'));
		return True;
	}
    
	
}

function fof_db_change_password($username, $password) {
    global $FOF_USER_TABLE;
    $salt = fof_make_bcrypt_salt();
    
	$password_hash = crypt($password, $salt);
    
	fof_query_log_private("update $FOF_USER_TABLE set user_password_hash = ? where user_name = ?", array($password_hash, $username), array('XXX password_hash XXX'));
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

function fof_db_get_admin_prefs() {
	global $FOF_CONFIG_TABLE;
	
	$result = fof_query_log("SELECT * from $FOF_CONFIG_TABLE where param in ('logging','autotimeout','manualtimeout','purge', 'max_items_per_request')", null);
	$ret = array();
	while ($row = fof_db_get_row($result)){
		$ret[$row['param']] = $row['val'];
	}
	return $ret;
}

function fof_db_set_admin_prefs($prefs) {
	global $FOF_CONFIG_TABLE;
	
	if (count($prefs) == 0) return;
	$allowedKeys = array('logging' => null, 'autotimeout' => null, 'manualtimeout' => null, 'purge' => null, 'max_items_per_request' => null);
	//performance not important, this shouldn't change often
	$updater = fof_prepare_query_log("UPDATE $FOF_CONFIG_TABLE set val = ? where param = ?");
	foreach (array_intersect_key($prefs, $allowedKeys) as $key => $val) {
		$updater(array($val, $key));
	} 
}

function fof_db_authenticate($user_name, $password){
    global $FOF_USER_TABLE;
    $result = fof_query_log("select * from $FOF_USER_TABLE where user_name = ?", array($user_name));
    if ($result instanceof PDOStatement){
    	if($result->rowCount() == 0) {
        	return False;
    	}
    
    	$row = fof_db_get_row($result);
    	$computedHash = crypt($password, $row['user_password_hash']);
    	if ($computedHash === $row['user_password_hash']){
    		$_SESSION['user_name'] = $row['user_name'];
    		$_SESSION['user_id'] = $row['user_id'];
    		$_SESSION['user_level'] = $row['user_level'];
    		$_SESSION['authenticated'] = True;
    		
    		//check whether we need to change the bcrypt effort
    		$storedEffort = intval(substr($row['user_password_hash'], 4, 2));
    		$requiredEffort = intval(fof_db_bcrypt_effort());
    		if ($storedEffort != $requiredEffort) {
    			fof_db_change_password($user_name, $password); //same password, just rehashes
    		}
    		return True;
   		}
   	}
    return False;
}

function fof_db_bcrypt_effort() {
	//get effort from config table, and format as two digit string
	global $FOF_CONFIG_TABLE;
	
	$result = fof_query_log("SELECT val from $FOF_CONFIG_TABLE where param = 'bcrypt_effort'", null);
	if ($row = fof_db_get_row($result)) {
		return sprintf('%02d', $row['val']);
	}
	return "09";
}

function fof_db_place_cookie($oldToken, $newToken, $uid, $user_agent){
	global $FOF_COOKIE_TABLE;
	// clear previous cookie if there is one.  It is possible, though unlikely, that another user may have the same
	// token value.  Thus we must delete ALL the records with the old token value, then insert the new record
	// and NOT simply do an update.  This will slightly inconvenience the second user, who will have to (re) log in,
	// but will guarantee that 2nd user doesn't get access to first user's account.
	//also delete any tokens with the new value - see bug report 180
	$args[] = hash('tiger160,4', $newToken);
	$query = "DELETE from $FOF_COOKIE_TABLE where token_hash=?";
	if ($oldToken) {
		$args[] = hash('tiger160,4',$oldToken);
		$query .= " or token_hash=?";
	}
	$result = fof_query_log($query, $args);
	$result = fof_query_log_private("INSERT into $FOF_COOKIE_TABLE (token_hash, user_id, user_agent_hash) VALUES (:tokenhash, :userid, :useragenthash)",
									array('tokenhash' => hash('tiger160,4', $newToken), 'userid' => $uid, 'useragenthash' => hash('tiger160,4', $user_agent . $newToken)),
									array('tokenhash' => 'XXX token hash XXX'));
	return True;
}

function fof_db_validate_cookie($token, $userAgent){
	global $FOF_COOKIE_TABLE, $FOF_USER_TABLE;
	$result = fof_query_log_private("SELECT * from $FOF_COOKIE_TABLE where token_hash=?",array(hash('tiger160,4',$token)), array('XXX token_hash XXX'));
	if ($result instanceof PDOStatement){
		if ($result->rowCount() > 0){
			$row = fof_db_get_row($result);
			if (hash('tiger160,4',$userAgent . $token) === $row['user_agent_hash']){
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
	return fof_query_log_private("DELETE from $FOF_COOKIE_TABLE where token_hash=?", array(hash('tiger160,4',$token)), array('XXX token_hash XXX'));
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
	$hash = base64_encode(hash('tiger192,4',$id,True));
	global $FOF_SESSION_TABLE;
    $result = fof_query_log_private("SELECT session_data from $FOF_SESSION_TABLE where session_id = :sessid",
    								array('sessid' => $hash),
    								array('sessid' => 'XXX session id hash XXX'));
    if ($result->rowCount()){
    	$record = fof_db_get_row($result);
    	return $record['session_data'];
    }
    return '';
}

function fof_db_write_session($id, $data){
	global $FOF_SESSION_TABLE;
	global $fof_connection;
	
	$hash = base64_encode(hash('tiger192,4',$id,True));
    $access = time();
    
    //do a separate delete then insert, cos not all database backends support replace
    //don't really need the atomicity, because I assume php only ever issues unique sessin ids
    //but hashes could collide, I suppose, and it seems good practice
    $fof_connection->beginTransaction();
    try {
    	fof_query_log_private("DELETE from $FOF_SESSION_TABLE where session_id = ?", array($hash), array('XXX session id hash XXX'), False);
    	fof_query_log_private("INSERT into $FOF_SESSION_TABLE (session_id, session_access, session_data) 
    							VALUES (:sessid, :access, :data)",
    							array('sessid' => $hash, 'access' => $access, 'data' => $data),
    							array('sessid' => 'XXX session id hash XXX'),
    							False);
    	$fof_connection->commit();
    	return True;
    } catch (Exception $e) {
    	$fof_connection->rollBack();
    }
}

function fof_db_destroy_session($id){
	global $FOF_SESSION_TABLE;
	$hash = base64_encode(hash('tiger192,4',$id,True));
    fof_query_log_private("DELETE from $FOF_SESSION_TABLE where session_id=?", array($hash), array('XXX session id hash XXX'));
    return True;
}

function fof_db_clean_session($max){
	global $FOF_SESSION_TABLE;
    $old = time() - $max;
	return fof_query_log("DELETE from $FOF_SESSION_TABLE where session_access < ?", array($old));
}

?>
