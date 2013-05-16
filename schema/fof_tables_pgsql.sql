CREATE TABLE IF NOT EXISTS $FOF_FEED_TABLE (
  feed_id serial,
  feed_url text NOT NULL,
  feed_title text NOT NULL,
  feed_link text NOT NULL,
  feed_description text NOT NULL,
  feed_image text,
  feed_image_cache_date integer default '0',
  feed_cache_date integer default '0',
  feed_cache_attempt_date integer default '0',
  feed_cache text,
  PRIMARY KEY  (feed_id)
);

CREATE TABLE IF NOT EXISTS $FOF_ITEM_TABLE (
  item_id serial,
  feed_id integer NOT NULL default '0',
  item_guid text NOT NULL,
  item_link text NOT NULL,
  item_cached integer NOT NULL default '0',
  item_published integer NOT NULL default '0',
  item_updated integer NOT NULL default '0',
  item_title text NOT NULL,
  item_content text NOT NULL,
  PRIMARY KEY  (item_id)
);

CREATE INDEX feed_id_item_idx  on $FOF_ITEM_TABLE (feed_id);
CREATE INDEX item_guid_item_idx on $FOF_ITEM_TABLE (item_guid);
CREATE INDEX feed_id_item_cached_item_idx on $FOF_ITEM_TABLE (feed_id, item_cached);

CREATE TABLE IF NOT EXISTS $FOF_ITEM_TAG_TABLE (
  user_id integer NOT NULL default '0',
  item_id integer NOT NULL default '0',
  tag_id integer NOT NULL default '0',
  PRIMARY KEY  (user_id,item_id,tag_id)
);

CREATE TABLE IF NOT EXISTS $FOF_SUBSCRIPTION_TABLE (
  feed_id integer NOT NULL default '0',
  user_id integer NOT NULL default '0',
  subscription_prefs text,
  PRIMARY KEY  (feed_id,user_id)
);

CREATE TABLE IF NOT EXISTS fof_tag (
  tag_id serial,
  tag_name char(100) NOT NULL default '',
  PRIMARY KEY  (tag_id)
);
CREATE UNIQUE INDEX tag_name_tag_idx on $FOF_TAGE_TABLE (tag_name);

CREATE TYPE user_level_type as ENUM('user','admin');
CREATE TABLE IF NOT EXISTS $FOF_USER_TABLE (
  user_id serial,
  user_name varchar(100) NOT NULL default '',
  user_password_hash varchar(60) NOT NULL default '',
  user_level user_level_type default 'user',
  user_prefs text,
  PRIMARY KEY  (user_id)
);
CREATE UNIQUE INDEX user_name_user_idx on $FOF_USER_TABLE (user_name);

CREATE TABLE IF NOT EXISTS $FOF_COOKIE_TABLE (
  token_hash varchar(40) NOT NULL default '',
  user_id integer NOT NULL default '0',
  user_agent_hash varchar(40) NOT NULL default '',
  PRIMARY KEY  (token_hash)
);

CREATE TABLE IF NOT EXISTS $FOF_SESSION_TABLE (
  	session_id varchar(32) NOT NULL,
    session_access integer,
    session_data text,
    PRIMARY KEY (session_id)
);

CREATE TABLE IF NOT EXISTS $FOF_CONFIG_TABLE (
	param VARCHAR( 128 ) NOT NULL ,
	val TEXT NOT NULL ,
	PRIMARY KEY (param)
);
