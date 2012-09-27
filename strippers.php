<?php

class WhitelistSanitiser{
	var $allowedTags = array();
	var $handlers = array();
	public function sanitise($dirty){
		$regex = '/<\\s*([\/\\w]*)([^>]*)>/'; //this will need to be expanded, most probably
		$allowed = $this->allowedTags;
		$handlers = $this->handlers;
		$clean = preg_replace_callback($regex, function($match) use($allowed, $handlers){
			if (in_array($match[1], $allowed)){
				$tag = $match[1];
				$data = $match[2];
				if (array_key_exists($tag,$handlers)){
					$newData = $handlers[$tag]($data);
					return "<$tag $newData>";
				} else {
					return $match[0];
				}
			} else {
				return '';
			}
		}, $dirty);
		return $clean;
		
	}
	function addTagHandler($tag,$handler){
		$this->handlers[] = $handler;
	}
	function whitelistTag($tag){
		$this->allowedTags[] = $tag;
	}
	static function sanitiseLink($url){
		$url = html_entity_decode($url);
		$url = urldecode($url);
		//kill off javascript:, vbscript:, etc
		$processed = trim(strtolower($url));
		if (strpos($processed, 'javascript:') === False && strpos($processed, 'vbscript:') === False){
			$ret = $url;
		} else {
			$ret = '';
		}
		
		//TODO - kill relative links as well, and strip all get parameters
		return $ret;
	}
}

class FofItemSanitiser extends WhitelistSanitiser{
	function __construct(){
		$this->allowedTags = array('p','/p','b','/b','a','/a','center','/center','em','/em','img','br', 'h1','/h1',
									'h2','/h2','h3','/h3', 'div','/div','table','/table','tr','/tr','td','/td',
									'li','/li','ul','/ul','ol','/ol');
		$this->handlers = array(
		'a' => function($data){
			//extract the href part, and sanitise it
			preg_match('/href\s*=\s*"([^"]*)"/', $data, $matches);
			if ($matches){
				$url = $matches[1];
			} else {
				$url = '';
			}
			$ret = FofItemSanitiser::sanitiseLink($url);
			return 'href="'.$ret.'"';	
		},
		'img' => function($data){
			//extract the src part, and sanitise it
			preg_match('/src\s*=\s*"([^"]*)"/', $data, $matches);
			if ($matches){
				$url = $matches[1];
			} else {
				$url = '';
			}
			$ret = FofItemSanitiser::sanitiseLink($url);
			return 'src="'.$ret.'"';
		});
	}

}

//testing code
//$a = new FofItemSanitiser();
//echo $a->sanitise('<a href="http://www.google.com">hello</a><a href="%4A%61%56%41%73%63%72%69%70%74%3A%20%61%6C%65%72%74%28%27%68%65%6C%6C%6F%27%29%3B">evil</a><img src="legit.jpg" onload="javascript:more_evil();">
//<br /><br> hello, this is some more text <p> a new paragraph </p> <em>italics</em>');
//echo "\n";
?>
