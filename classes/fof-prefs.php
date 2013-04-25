<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-prefs.php - Preferences class
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

class FoF_Prefs {
	var $user_id;
    var $prefs;
    var $admin_prefs;
    
	function FoF_Prefs($user_id) {
        global $FOF_USER_TABLE;
        
		$this->user_id = $user_id;

        $result = fof_query_log("select user_prefs from $FOF_USER_TABLE where user_id = ?", array($user_id));
        $row = fof_db_get_row($result);
        $prefs = unserialize($row['user_prefs']);
        if(!is_array($prefs)) $prefs = array();
        $this->prefs = $prefs;
        
        //get the admin prefs
        $this->admin_prefs = fof_db_get_admin_prefs();
        
        $this->populate_defaults();
    }
    
    function &instance() {
        static $instance;
        if(!isset($instance)) $instance = new FoF_Prefs(fof_current_user());
        
        return $instance;
    }
    
    function populate_defaults() {
        $defaults = array(
            'favicons' => True,
            'keyboard' => False,
            'newtabs' => True,
            'direction' => 'desc',
            'howmany' => 50,
            'sharing' => 'no',
            'feed_order' => 'feed_title',
            'feed_direction' => 'asc',
            );
        
        $admin_defaults = array(
            'purge' => 30,
            'autotimeout' => 30,
            'manualtimeout' => 15,
            'logging' => False,
             );
        
        $this->stuff_array($this->prefs, $defaults);
        $this->stuff_array($this->admin_prefs, $admin_defaults);
    }
    
    function stuff_array(&$array, $defaults) {
        foreach($defaults as $k => $v) {
            if(!isset($array[$k])) $array[$k] = $v;
        }
    }
    
    function get($k) {
        return $this->prefs[$k];
    }
    
    function set($k, $v) {
        $this->prefs[$k] = $v;
    }
    
    function setAdmin($k, $v) {
    	$this->admin_prefs[$k] = $v;
    }
    
    function getAdmin($k) {
    	return $this->admin_prefs[$k];
    }
    
    function adminPrefs(){
    	return $this->admin_prefs;
    }
    
    function save() {
        fof_db_save_prefs($this->user_id, $this->prefs);
        fof_db_set_admin_prefs($this->admin_prefs);
    }
}

?>
