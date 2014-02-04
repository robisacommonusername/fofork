<?php
/*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * StrictSanitiser.php - A sanitizer class for SimplePie.
 * Significantly less permissive than the default sanitiser class.
 * Uses whitelist approach - only whitelisted html tags and attributes
 * are allowed to pass through.
 * Additionally, has stronger measures to prevent XSS in href attribute
 * of link (a) elements
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2004-2007 Stephen Minutillo, 2012-2014 Robert Palmer
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

class SimplePie_StrictSanitiser extends SimplePie_Sanitize {

	public $allowedElements = array(
		'img' => 'img_attr_handler',
		'a' => 'a_attr_handler',
		'body' => 'default_attr_handler',
		'p' => 'default_attr_handler',
		'b' => 'default_attr_handler',
		'center' => 'default_attr_handler',
		'em' => 'default_attr_handler',
		'br' => 'default_attr_handler',
		'h1' => 'default_attr_handler',
		'h2' => 'default_attr_handler',
		'h3' => 'default_attr_handler',
		'div' => 'default_attr_handler',
		'table' => 'default_attr_handler',
		'tr' => 'default_attr_handler',
		'td' => 'default_attr_handler',
		'li' => 'default_attr_handler',
		'ul' => 'default_attr_handler',
		'ol' => 'default_attr_handler'
	);
	
	protected function attrIterHelper($node){
		//convert attributes to an array
		$arr = array();
		foreach ($node->attributes as $name => $val){
			$arr[$name] = $val;
		}
		return $arr;
	}
	protected function sanitise_link($url){
		//output of this function should always be safe to place between
		//<a href="<?php echo $output">
		//note that this function is for injecting into html context,
		//NOT javascript context
		$url = html_entity_decode($url);
		$url = urldecode($url);
		//kill off javascript:, vbscript:, etc
		$processed = trim(strtolower($url));
		if (strpos($processed, 'javascript:') === False && strpos($processed, 'vbscript:') === False){
			$ret = $url;
		} else {
			$ret = '';
		}
		//look for ' or ".  These are commonly used to try and escape href="" and thus inject scripts
		//kill anything to the right
		$ret = preg_replace('/".*$/','',$ret);
		$ret = preg_replace("/'.*$/",'',$ret);
		
		//look for attempted path traversals (www.example.com/../../../systemfile)
		$ret = preg_replace('/([.]{2}\/?)+/', '', $ret);
		
		//remove any html tags
		$ret = strip_tags($ret);
		
		//absolutize the url
		$ret = $this->registry->call('Misc', 'absolutize_url', array($ret, $this->base));
		
		return $ret;
	}
	
	protected function img_attr_handler($node){
		foreach ($this->attrIterHelper($node) as $name => $attrNode){
			switch ($name){
				
				case 'src':
				//allow the src attribute, but transform the url
				$sanitised_src = '';
				$url = $node->getAttribute('src');
				
				//Check - do we need to cache the image?
				if (isset($this->image_handler) && ((string) $this->image_handler) !== '' && $this->enable_cache) {
					$image_url = call_user_func($this->cache_name_function, $url);
					$cache = $this->registry->call('Cache', 'get_handler', array($this->cache_location, $image_url, 'spi'));

					if ($cache->load()) {
						$sanitised_src = $this->image_handler . $image_url;
					} else {
						$file = $this->registry->create('File', array($img['attribs']['src']['data'], $this->timeout, 5, array('X-FORWARDED-FOR' => $_SERVER['REMOTE_ADDR']), $this->useragent, $this->force_fsockopen));
						$headers = $file->headers;

						if ($file->success && ($file->method & SIMPLEPIE_FILE_SOURCE_REMOTE === 0 || ($file->status_code === 200 || $file->status_code > 206 && $file->status_code < 300))) {
							if ($cache->save(array('headers' => $file->headers, 'body' => $file->body))) {
								$sanitised_src = $this->image_handler . $image_url;;
							} else {
								return null;
							}
						}
					}
				//if we don't need to cache, just apply normal link sanitisation
				} else {
					$sanitised_src = $this->sanitise_link($url);
				}
				//set src parameter to its corrected value
				$node->setAttribute('src', $sanitised_src);
				break;
			
				case 'alt':
				//no change required
				break;
				
				//delete all nodes other than src and alt
				default:
				$node->removeAttributeNode($attrNode);
			}
		}
		return $node;
	}
	protected function a_attr_handler($node){
		//allow the href attribute, but transform the url
		//TODO: iterate over attributes needs to convert from DOMNamedNodeMap to array first,
		//see http://www.php.net/manual/en/class.domnamednodemap.php
		foreach ($this->attrIterHelper($node) as $name => $attrNode){
			if ($name == 'href'){
				$url = $node->getAttribute('href');
				$node->setAttribute('href', $this->sanitise_link($url));
			} else {
				$node->removeAttributeNode($attrNode);
			}
		}
		return $node;
	}
	protected function default_attr_handler($node){
		if (!array_key_exists($node->nodeName, $this->allowedElements)){
			return null;
		}
		
		//strip all attributes
		foreach ($node->attributes as $attr){
			$node->removeAttributeNode($attr);
		}
		return $node;
	}

	protected function sanitize_html($data, $type, $base = ''){
		//build a sanitised html document
		$document = new DOMDocument();
		$document->encoding = 'UTF-8';
		$data = $this->preprocess($data, $type);

		set_error_handler(array('SimplePie_Misc', 'silence_errors'));
		$document->loadHTML($data);
		restore_error_handler();
		
		//build amended document - one pass over the dom tree.
		//maintaining two copies of the tree wastes a little memory,
		//but there are all kinds of problems with iterating over a
		//DOMDocument while deleting nodes.  
		//See http://www.php.net/manual/en/class.domnamednodemap.php
		$new_doc = new DOMDocument();
		$new_doc->encoding = 'UTF-8';
		
		//breadth first traverse of DOM tree is required, otherwise the
		//nodes will be in the "reverse" order. Use a queue, not a stack.
		$queue = array(array($document, $new_doc));
		while (count($queue) > 0){
			list($curr_node, $insertion_point) = array_shift($queue);
			//test - is it a comment, allowed element, etc
			//process and add, or reject
			switch ($curr_node->nodeType){
				
				case XML_ELEMENT_NODE:
				$type = $curr_node->nodeName;
				if (array_key_exists($type, $this->allowedElements)){
					$handler = $this->allowedElements[$type];
					$copy = $new_doc->importNode($curr_node);
					$copy = call_user_func(array($this,$handler), $copy);
					if ($copy instanceof DOMNode){
						$insertion_point->appendChild($copy);
						$insertion_point = $copy;
					}
				}
				break;
				
				case XML_TEXT_NODE:
				//insert
				$copy = $new_doc->importNode($curr_node);
				$insertion_point->appendChild($copy);
				$insertion_point = $copy;
				break;
				
				default:
				//reject all other node types
			}
			
			//does node have children? append to queue
			if ($curr_node->hasChildNodes()){
				foreach ($curr_node->childNodes as $child){
					$queue[] = array($child,$insertion_point);
				}
			}
		}
		
		// Remove the DOCTYPE
		// Seems to cause segfaulting if we don't do this
		if ($new_doc->firstChild instanceof DOMDocumentType) {
			$new_doc->removeChild($new_doc->firstChild);
		}

		// Move everything from the body to the root
		$real_body = $new_doc->getElementsByTagName('body')->item(0)->childNodes->item(0);
		$new_doc->replaceChild($real_body, $new_doc->firstChild);

		// Finally, convert to a HTML string
		$data = trim($new_doc->saveHTML());	

		if ($this->remove_div) {
			$data = preg_replace('/^<div' . SIMPLEPIE_PCRE_XML_ATTRIBUTE . '>/', '', $data);
			$data = preg_replace('/<\/div>$/', '', $data);
		} else {
			$data = preg_replace('/^<div' . SIMPLEPIE_PCRE_XML_ATTRIBUTE . '>/', '<div>', $data);
		}
		return $data;
	}
	
	public function sanitize($data, $type, $base = '') {
		$data = trim($data);
		//determine data type
		if ($data !== '' || $type & SIMPLEPIE_CONSTRUCT_IRI) {
			if ($type & SIMPLEPIE_CONSTRUCT_MAYBE_HTML) {
				if (preg_match('/(&(#(x[0-9a-fA-F]+|[0-9]+)|[a-zA-Z0-9]+)|<\/[A-Za-z][^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3E]*' . SIMPLEPIE_PCRE_HTML_ATTRIBUTE . '>)/', $data)){
					$type |= SIMPLEPIE_CONSTRUCT_HTML;
				} else {
					$type |= SIMPLEPIE_CONSTRUCT_TEXT;
				}
			}
			
			if ($type & SIMPLEPIE_CONSTRUCT_BASE64) {
				$data = base64_decode($data);
			}

			if ($type & (SIMPLEPIE_CONSTRUCT_HTML | SIMPLEPIE_CONSTRUCT_XHTML)) {
				$data = $this->sanitize_html($data, $type, $base);
			}

			if ($type & SIMPLEPIE_CONSTRUCT_IRI)
			{
				$absolute = $this->registry->call('Misc', 'absolutize_url', array($data, $base));
				if ($absolute !== false)
				{
					$data = $absolute;
				}
			}

			if ($type & (SIMPLEPIE_CONSTRUCT_TEXT | SIMPLEPIE_CONSTRUCT_IRI))
			{
				$data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8', False);
			}

			if ($this->output_encoding !== 'UTF-8')
			{
				$data = $this->registry->call('Misc', 'change_encoding', array($data, 'UTF-8', $this->output_encoding));
			}
		}
		return $data;
	}
}

?>
