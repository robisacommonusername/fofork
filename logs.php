<?php
   /*
 * This file is part of fofork
 * 
 * http://robisacommonusername.github.io/fofork
 *
 * logs.php - read encrypted logfile
 *
 * fofork is derived from Feed on Feeds, by Steven Minutillo
 * http://feedonfeeds.com/
 * 
 * Copyright (C) 2012-2014 Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */
 
require_once('fof-main.php');
require_once('classes/AES.php');

if (!fof_is_admin()){
	$arr = array(
		'status' => 401,
		'message' => 'Only admin may view the logs!');
	die(json_encode($arr));
}

function make_decoder($pwd){
	return function($line) use ($pwd) {
		//Note that we create a new instance of Crypt_AES every time this
		//function is called. This is not as inefficient as it might seem,
		//since we're unable to reuse instances to decrypt multiple lines
		//with different IVs (the internal state of Crypt_AES gets stuffed up)
		$decoded = base64_decode($line);
		$aes = new Crypt_AES();
		$IV = substr($decoded, 0, 16);
		$ct = substr($decoded, 16);
		$aes->setIV($IV);
		$aes->setKey($pwd);
		return $aes->decrypt($ct);
	};
}

$pwd = fof_db_log_password();

switch ($_POST['action']){
	
///////////////////////////////////////////	
//export the decrypted log as a text file
///////////////////////////////////////////	
	case 'export':
	header('Content type: text/plain');
	header('Content-Disposition: attachment; filename="fof_log.txt"');
	if (!file_exists('fof.log')){
		exit();
	}
	$f = fopen('fof.log','r');
	$decoder = make_decoder($pwd);
	while (($line = fgets($f)) !== False){
		echo $decoder($line);
		echo "\n";
	}
	fclose($f);
	exit();
	
	
///////////////////////////////////////////	
//fetch some subset of the log file using ajax requests
//useful for large logs
//////////////////////////////////////////
	case 'ajax':
	$offset = intval($_POST['offset']);
	if (file_exists('fof.log')){
		$f = fopen('fof.log','r');
		fseek($f,-1,SEEK_END);
		$len = ftell($f);
		if ($offset < 0){
			if ($offset < -1*$len){
				$offset = -1*$len;
			}
			fseek($f,$offset,SEEK_END);
		} else {
			if ($offset > $len){
				$offset = $len;
			}
			fseek($f,$offset,SEEK_SET);
		}
		//read in 64K
		$text = fread($f,64*1024);
		fclose($f);
		//find first and last new line characters
		$first = strpos($text,"\n") + 1;
		$last = strrpos($text,"\n");
		$len = $last-$first+1;
		$encLines = explode("\n",substr($text,$first,$len));
		//remove the trailing empty string from the last newline
		if ($encLines[-1] == ''){
			array_pop($encLines);
		}
		$decLines = array_map(make_decoder($pwd), $encLines);
	} else {
		$decLines = array();
		$len = 0;
	}
	$arr = array(
		'status' => 200,
		'message' => '',
		'CSRF_hash' => fof_compute_CSRF_challenge(),
		'data' => array(
			'offset' => $offset+$first,
			'len' => $len,
			'lines' => $decLines));
	echo json_encode($arr);
	exit();
	
///////////////////////////////////////////	
//delete the log file	
//////////////////////////////////////////
	case 'clear':
	if (isset($_POST['CSRF_hash'])){
		if (fof_authenticate_CSRF_challenge($_POST['CSRF_hash'])){
			$f = fopen('fof.log','w');
			fwrite($f, '');
			fclose($f);
			echo 'Log file cleared.';
			exit;
		}
	}
	echo 'Bad request';
	exit();


//////////////////////////////////////////
//send html to browser
//////////////////////////////////////////
	default:
	include('header.php');
	?>
	<link rel="stylesheet" type="text/css" media="all" href="jsdatepick/jsDatePick_ltr.min.css" />
	<script type="text/javascript" src="jsdatepick/jsDatePick.full.1.3.js"></script>
	<script type="text/javascript" src="logs.js"></script>
	<script type="text/javascript">
	window.onload = function(){
		new JsDatePick({
		useMode:2,
		target:"before_id",
		dateFormat:"%Y-%m-%d"});
		
		new JsDatePick({
		useMode:2,
		target:"after_id",
		dateFormat:"%Y-%m-%d"});
		
		FofLogViewer.fetch();
	};
	</script>
	
	<?php
	//remember any previously submitted values
	$inc = isset($_POST['include']) ? htmlspecialchars($_POST['include']) : 'search string';
	$exc = isset($_POST['exclude']) ? htmlspecialchars($_POST['exclude']) : 'exclude';
	$before = isset($_POST['before']) ? htmlspecialchars($_POST['before']) : date('Y-m-d');
	$after = isset($_POST['after']) ? htmlspecialchars($_POST['after']) : date('Y-m-d');
	$include_checkbox_state = isset($_POST['include_checkbox']);
	$exclude_checkbox_state = isset($_POST['exclude_checkbox']);
	$before_checkbox_state = isset($_POST['before_checkbox']);
	$after_checkbox_state = isset($_POST['after_checkbox']);
	$headtail_checkbox_state = isset($_POST['headtail_checkbox']);
	$export_state = isset($_POST['export']);
 	?>
 	<h1>Feed on feeds log viewer</h1> <br />
 		<input type="checkbox" name="headtail_checkbox" id="headtail_checkbox" onclick="FofLogViewer.update()" <?php echo $headtail_checkbox_state ? 'checked' : ''; ?>>
 		Search the <select name="headtail" id="head_or_tail" onchange="FofLogViewer.update()"><option value="head">First</option><option value="tail">Last</option></select><select name="headTailQty" id="head_tail_qty" onchange="FofLogViewer.update()"><option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option></select> lines<br /><br />
 		
 		<input type="checkbox" name="include_checkbox" id="include_checkbox" onclick="FofLogViewer.update()" <?php echo $include_checkbox_state ? 'checked' : ''; ?>>
 		Show lines containing the text <input type="text" id = "include" value="<?php echo $inc; ?>" name="include"  onchange="FofLogViewer.update()"><br />
 		<input type="radio" id="include_insensitive_select_id" name="inc_insen" onclick="FofLogViewer.update()" checked>Case insensitive  <input type="radio" name = "inc_insen" onclick="FofLogViewer.update()"> Case sensitive<br />
 		<input type="radio" name = "inc_regex" onclick="FofLogViewer.update()" checked> Plain text search  <input type="radio" id="include_regex_select_id" name="inc_regex" onclick="FofLogViewer.update()">Regular expression<br /><br />
 
 		<input type="checkbox" name="exclude_checkbox" id="exclude_checkbox" onclick="FofLogViewer.update()" <?php echo $exclude_checkbox_state ? 'checked' : ''; ?>>
 		Exclude lines containing <input type="text" id="exclude" value="<?php echo $exc; ?>" name="exclude"  onchange="FofLogViewer.update()"><br />
 		<input type="radio" id="exclude_insensitive_select_id" name="exc_insen" onclick="FofLogViewer.update()" checked>Case insensitive  <input type="radio" name = "exc_insen" onclick="FofLogViewer.update()"> Case sensitive<br />
 		<input type="radio" name = "exc_regex" onclick="FofLogViewer.update()" checked> Plain text search  <input type="radio" id="exclude_regex_select_id" name="exc_regex" onclick="FofLogViewer.update()">Regular expression<br /><br />
 

		<input type="checkbox" name="before_checkbox" id="before_checkbox" onclick="FofLogViewer.update()" <?php echo $before_checkbox_state ? 'checked' : ''; ?>>
		Show results from before <input type="text" id="before_id" name="before" value="<?php echo $before;?>"  onchange="FofLogViewer.update()"><br />
		
		<input type="checkbox" name="after_checkbox" id="after_checkbox" onclick="FofLogViewer.update()" <?php echo $after_checkbox_state ? 'checked' : ''; ?>>
		Show results from after <input type="text" id="after_id" name="after" value="<?php echo $after;?>"  onchange="FofLogViewer.update()"><br /><br />

 		<form method="post" action="logs.php">
 		<input type="hidden" name="action" value="export">
 		<input type="submit" value="Export log as text file" id="export_btn"></form>

 	<br /><br />
 	<div id="log_throbber"><img src="image/throbber.gif">Loading...</div>
 	<textarea rows="20" cols="100" id="text_area" onscroll="FofLogViewer.scrollListener();">
	</textarea><br /><br />
	<center><a onclick="FofLogViewer.clear('<?php echo fof_compute_CSRF_challenge(); ?>')" href="#">Clear logs</a></center>
<?php
}

?>
