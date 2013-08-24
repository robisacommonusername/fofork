CREATE TABLE IF NOT EXISTS $FOF_FEED_TABLE (
  feed_id int(11) NOT NULL auto_increment,
  feed_url text NOT NULL,
  feed_title text NOT NULL,
  feed_link text NOT NULL,
  feed_description text NOT NULL,
  feed_image text,
  feed_image_cache_date int(11) default '0',
  feed_cache_date int(11) default '0',
  feed_cache_attempt_date int(11) default '0',
  feed_cache text,
  PRIMARY KEY  (feed_id)
);

CREATE TABLE IF NOT EXISTS $FOF_ITEM_TABLE (
  item_id int(11) NOT NULL auto_increment,
  feed_id int(11) NOT NULL default '0',
  item_guid text NOT NULL,
  item_link text NOT NULL,
  item_cached int(11) NOT NULL default '0',
  item_published int(11) NOT NULL default '0',
  item_updated int(11) NOT NULL default '0',
  item_title text NOT NULL,
  item_content text NOT NULL,
  PRIMARY KEY  (item_id),
  KEY feed_id (feed_id),
  KEY item_guid (item_guid(255)),
  KEY feed_id_item_cached (feed_id,item_cached)
);

CREATE TABLE IF NOT EXISTS $FOF_ITEM_TAG_TABLE (
  user_id int(11) NOT NULL default '0',
  item_id int(11) NOT NULL default '0',
  tag_id int(11) NOT NULL default '0',
  PRIMARY KEY  (user_id,item_id,tag_id)
);

CREATE TABLE IF NOT EXISTS $FOF_SUBSCRIPTION_TABLE (
  feed_id int(11) NOT NULL default '0',
  user_id int(11) NOT NULL default '0',
  subscription_prefs text,
  PRIMARY KEY  (feed_id,user_id)
);

CREATE TABLE IF NOT EXISTS $FOF_TAG_TABLE (
  tag_id int(11) NOT NULL auto_increment,
  tag_name char(100) NOT NULL default '',
  PRIMARY KEY  (tag_id),
  UNIQUE KEY (tag_name)
);

CREATE TABLE IF NOT EXISTS $FOF_USER_TABLE (
  user_id int(11) NOT NULL auto_increment,
  user_name varchar(100) NOT NULL default '',
  user_password_hash varchar(60) NOT NULL default '',
  user_level enum('user','admin') NOT NULL default 'user',
  user_email varchar(511) NOT NULL default '',
  user_prefs text,
  PRIMARY KEY  (user_id), UNIQUE KEY (user_name), UNIQUE KEY (user_email)
);

CREATE TABLE IF NOT EXISTS $FOF_COOKIE_TABLE (
  token_hash varchar(40) NOT NULL default '',
  user_id int(11) NOT NULL default '0',
  user_agent_hash varchar(40) NOT NULL default '',
  PRIMARY KEY  (token_hash)
);

CREATE TABLE IF NOT EXISTS $FOF_SESSION_TABLE (
  	session_id varchar(32) NOT NULL,
    session_access int(11) unsigned,
    session_data text,
    PRIMARY KEY (session_id)
);

CREATE TABLE IF NOT EXISTS $FOF_CONFIG_TABLE (
	param VARCHAR( 128 ) NOT NULL ,
	val TEXT NOT NULL ,
	PRIMARY KEY (param)
);
