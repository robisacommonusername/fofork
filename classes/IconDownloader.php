<?php
  /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 * 
 * IconDownloader.php - Download a website's favicon
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2015 Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */
class IconDownloader {
	function __construct($url){
		$this->url = $url;
		$this->icon_url = null;
		$this->cache_dir = './cache';
		$this->do_cache = true;
	}
	
	public function setCache($dir){
		if (file_exists($dir)){
			$this->cache_dir = $dir;
			return true;
		}
		return false;
	}
	
	public function enableCache(){
		$this->do_cache = true;
		return $this->do_cache;
	}
	
	public function disableCache(){
		$this->do_cache = false;
		return $this->do_cache;
	}
	
	public function getIconURL(){
		$url = $this->url;
		preg_match('|^((https?://)?[^/]+).*|', $url, $matches);
		$domain = $matches[1];
		if (array_key_exists(2, $matches)){
			$scheme = $matches[2];
		} else {
			$scheme = 'http://';
		}
		
		$icon_url = '';
		//look for favicon in link attribute
		$html = file_get_contents($domain);
		$dom = new DOMDocument();
		@$dom->loadHTML($html);
		$links = $dom->getElementsByTagName('link');
		for ($i = 0; $i < $links->length; $i++){ 
			$link = $links->item($i);
			
			//icon may be linked as rel="sortcut icon" or rel="icon"
			if(preg_match('/icon/i', $link->getAttribute('rel'))){
				$icon_url = $link->getAttribute('href');
				
				//fix relative urls, etc
				if (!preg_match('|^https?://.*|', $icon_url)){
					if (preg_match('|^//[^/]+.*|', $icon_url)) {
						$icon_url = $scheme . $icon_url;
					} else {
						$icon_url = (substr($icon_url, 0, 1) == '/') ? $domain.$icon_url : "$domain/$icon_url";
					}
				}
				
				//check that the icon exists
				$headers = get_headers($icon_url);
				if (strpos($headers[0], '200') === False) {
					$icon_url = '';
				}
			}
		}
		
		//look for favicon in document root
		if ($icon_url == ''){
			$headers = get_headers("$domain/favicon.ico");
			if (strpos($headers[0], '200') !== False) {
				$icon_url = "$domain/favicon.ico";
			}
		}
		
		//if no icon found, return default
		if ($icon_url == ''){
			$icon_url = 'image/feed_icon.png';
		}
		
		$this->icon_url = $icon_url;
		return $icon_url;
	}
	
	public function getCacheFile(){
		$cache_file = $this->cache_dir . '/' . md5($this->url) . '.png';
		return $cache_file;
	}
	
	public function getIconImage(){
		//check the cache
		$cache_file = $this->getCacheFile();
		if ($this->do_cache){
			if (file_exists($cache_file) && filemtime($cache_file)){
				return imagecreatefrompng($cache_file);
			}
		}
		
		if (is_null($this->icon_url)){
			$this->getIconURL();
		}
		$icon_url = $this->icon_url;
		//check filetype etc, return png
		$img = file_get_contents($icon_url);
		//ico file must begin with the 4 bytes 0x00 0x00 0x01 0x00
		if (substr($icon_url,-4,4) == '.ico'
			&& array_pop(unpack('N',$img)) == 0x00000100){
			$gd_img = IconDownloader::imagecreatefromico($img);
		} else {
			$gd_img = imagecreatefromstring($img);
		}
		if ($gd_img === False){
			$gd_img = imagecreatefrompng('image/feed_icon.png');
		}
		
		//save file to cache if required
		if ($this->do_cache){
			imagepng($gd_img, $cache_file);
		}
		
		return $gd_img;
	}
	
	public function getIconPng(){
		//return some bytes for the image in png format
		$img = $this->getIconImage();
		return IconDownloader::PNGBytes($img);
	}
	
	public function getCacheUpdateTime(){
		$cache_file = $this->getCacheFile();
		return filemtime($cache_file);
	}
	
	static function imagecreatefromico($data){
		//read in first icon entry (begins at offset 6, 16 bytes).
		//see https://en.wikipedia.org/wiki/ICO_%28file_format%29
		$entry_header = substr($data, 6, 16);
		$ico_header = unpack('Cwidth/Cheight/Cncols/x/vcolPlanes/vbpp/Vlen/Voffset',$entry_header);
		$width = $ico_header['width'];
		$height = $ico_header['height'];
		//limit the amount of bitmap data to 256kB. This allows a 255x255
		//pixel bitmap, uncompressed, with 24 bit colour depth
		$len = $ico_header['len'] < 256*1024 ? $ico_header['len'] : 256*1024;
		$offset = $ico_header['offset'];
		
		$img_data = substr($data, $offset, $len);
		//look at first few bytes to determine if it's a bitmap or a png
		$first4 = array_pop(unpack('N',$img_data));
		//png file begins with the bytes 0x89 0x50 0x4E 0x47
		//unpack as big endian so we can neatly write the comparison in
		//the order we've listed the bytes above
		if ($first4 == 0x89504e47){
			//its a png - gd can handle the png image for us
			$img = imagecreatefromstring($img_data); 
		} else {
			//its a bitmap
			$img = IconDownloader::imagecreatefromicobmp($img_data);
		}
		return $img;
		
	}
	
	static function imagecreatefromicobmp($img_data){
		//read bitmap data into a gd image
		//Bitmap info header is 40 bytes, then colour table, then
		//bitmap data
		//need to find out number of entries in colourtable
		//note that width and height are SIGNED little endian, 4 bytes,
		//php unpack only has option to read in as unsigned if we insist
		//on little endian-ness, will need to convert manually
		//off_x	off_d	len		desc
		//0E 	14 		4 		the size of this header (40 bytes)
		//12 	18 		4 		the bitmap width in pixels (signed integer)
		//16 	22 		4 		the bitmap height in pixels (signed integer)
		//1A 	26 		2 		the number of color planes must be 1
		//1C 	28 		2 		the number of bits per pixel, which is the color depth of the image. Typical values are 1, 4, 8, 16, 24 and 32.
		//1E 	30 		4 		the compression method being used. See the next table for a list of possible values
		//22 	34 		4 		the image size. This is the size of the raw bitmap data; a dummy 0 can be given for BI_RGB bitmaps.
		//26 	38 		4 		the horizontal resolution of the image. (pixel per meter, signed integer)
		//2A 	42 		4 		the vertical resolution of the image. (pixel per meter, signed integer)
		//2E 	46 		4 		the number of colors in the color palette, or 0 to default to 2n
		//32 	50 		4 		the number of important colors used, or 0 when every color is important; generally ignored
		$bmp_info = unpack('x4/Vwidth/Vheight/x2/vbpp/Vcompression/Vsize/x8/Vncolours/x4',$img_data);
		$width = $bmp_info['width'];
		//bitmaps specified in ico files are "double height", as the top
		//part of the image encodes the "AND mask" (i.e. transperancy)
		$height = $bmp_info['height']/2;
		$bpp = $bmp_info['bpp'];
		$ncolours = $bmp_info['ncolours'] == 0 ? 1<<$bmp_info['bpp'] : $bmp_info['ncolours'];
		$bitmap_data = unpack('C*',substr($img_data,40+4*$ncolours));
		//TODO: decompress if necessary
		
		//draw the bitmap
		$img = imagecreatetruecolor($width,$height);
		//allocate colours from the colour table
		if ($ncolours > 0) {
			$colour_map = unpack('V*', substr($img_data,40,$ncolours*4));
			//note that unpack gives us keys starting from 1, we use
			//array_values to get index starting from 0
			$gd_colour_table = array_map(function($x) use($img){
					$r = $x>>16 & 0xff;
					$g = $x>>8 & 0xff;
					$b = $x & 0xff;
					return imagecolorallocate($img, $r, $g, $b); 
				}, array_values($colour_map));
		}
		
		$excess_bits = 0;
		$acc = 0;
		$row = 0;
		$col = 0;
		$bpp_mask = (1 << $bpp) - 1;
		//rows of bitmap data must be padded to multiple of 4 bytes = 32 bits
		//add some 'dummy' pixels that won't get drawn to image width
		//we have $width*$bpp bits per row, need to make it up to multiple of 32
		$bits_per_row = $width*$bpp;
		$words_per_row = ceil($bits_per_row/32);
		$pad_bits = $words_per_row*32 - $bits_per_row;
		$pad_width = $width + ceil($pad_bits/$bpp);
		$nbytes = $height*$words_per_row*4;
		for ($i=1; $i<=$nbytes; $i++){
			$acc = $acc<<8 | $bitmap_data[$i];
			$excess_bits += 8;
			while ($excess_bits >= $bpp){
				//we've got enough data for a new pixel (or several)
				$shift = $excess_bits - $bpp;
				$next_pixel_unshifted = $acc & ($bpp_mask << $shift);
				$next_pixel = $next_pixel_unshifted >> $shift;
				//clear the current pixel from the accumulator
				$acc = $acc ^ $next_pixel_unshifted;
				$excess_bits -= $bpp;
				
				//get the colour for the pixel. If there's a colour
				//table, look it up, otherwise for 24bpp image just
				//read value directly
				if ($ncolours > 0){
					$colour = $gd_colour_table[$next_pixel];
				} else {
					$r = $next_pixel>>16 & 0xff;
					$g = $next_pixel>>8 & 0xff;
					$b = $next_pixel & 0xff;
					$colour = imagecolorallocate($img,$r,$g,$b);
				}
				
				//draw pixel
				if ($col < $width){
					//don't draw on dummy (pad) columns
					imagesetpixel($img,$col,$height-$row-1,$colour);
				}
				$col++;
				if ($col >= $pad_width){
					$col = 0;
					$row++;
				}
			}
		}
		return $img;
	}
}
?>
