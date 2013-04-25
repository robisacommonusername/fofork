<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * logs.php - read encrypted logfile
 *
 * Copyright (C) 2012, Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */
 
require_once('fof-main.php');
require_once('classes/AES.php');

if (!fof_is_admin()){
	die('Only admin may view the logs!');
}

function decrypt_line($line){
	$IV = substr($line, 0, 22);
	$data = substr($line, 22);
	$aes = new Crypt_AES();
	$aes->setKey(fof_db_log_password());
	$aes->setIV($IV);
	$decoded = $aes->decrypt(base64_decode($data));
	return $decoded;
}

function date_filter($line, $date, $laterThan){
	$regex = '/^\\D*(\\d{1,2})\\s*(\\w{3})\\s*(\\d{4}).*$/';
	preg_match($regex,$line,$match);
	$months = array('jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec');
	$monthi = array_search(strtolower($match[2]),$months) + 1;
	$parsedDate = mktime(0,0,0,$monthi,$match[1],$match[3]); //hour, min, sec, month, day, year
	return $laterThan ? ($parsedDate > $date) : ($parsedDate < $date);
}
//slurp all text, and split lines
if (file_exists('fof.log')) {
	$logLines = file('fof.log');
} else {
	$logLines = array();
}

//use head/tail.  Only look at last or first n lines
if (isset($_POST['headtail_checkbox'])){
	$qty = intval($_POST['headTailQty']);
	if ($_POST['headtail'] == "head"){
		$offset = 0;
		$len = $qty > 0 && $qty < count($logLines) ? $qty : 0;
	} else {
		//tail
		$len = $qty > 0 && $qty < count($logLines) ? $qty : 0;
		$offset = -1*$len;
	}
	$logLines = array_slice($logLines, $offset, $len);
}

//decrypt everything
$decodedLines = array_map('decrypt_line', $logLines);

//check for include and exclude strings
if (isset($_POST['include_checkbox']) || isset($_POST['exclude_checkbox'])){
	$state = 2*(isset($_POST['include_checkbox']) ? 1 : 0) + (isset($_POST['exclude_checkbox']) ? 1 : 0);
	if (isset($_POST['include']) && $_POST['include'] != ''){
		$inc = $_POST['include'];
	} else {
		$state &= 1; //clear the "include" bit
	}
	if (isset($_POST['exclude']) && $_POST['exclude'] != ''){
		$exc = $_POST['exclude'];
	} else {
		$state &= 2; //clear the exclude bit
	}

	switch ($state){
		case 1: //exclude only
		$callback = function($x) use($exc){
			return (strpos($x,$exc) === False);
		}; break;
		
		case 2: //include only
		$callback = function ($x) use($inc){
			return strpos($x,$inc) !== False;
		}; break;
		
		case 3: //include and exclude
		$callback = function($x) use($inc,$exc){
			return strpos($x,$inc) !== False && strpos($x,$exc) === False;
		}; break;
		
		default:
		$callback = function($a){return True;};
	}
	$decodedLines = array_filter($decodedLines, $callback);
}
//check by date
if (isset($_POST['before_checkbox']) || isset($_POST['after_checkbox'])){
	$state = 2*(isset($_POST['before_checkbox']) ? 1 : 0) + (isset($_POST['after_checkbox']) ? 1 : 0);
	if (isset($_POST['before']) && $_POST['before'] != ''){
		$beforeParams = date_parse($_POST['before']);
		$before = mktime(0,0,0,$beforeParams['month'],$beforeParams['day'],$beforeParams['year']);
	} else {
		$state &= 1; //if there's no before data, clear the before bit
	}
	if (isset($_POST['after']) && $_POST['after'] != ''){
		$afterParams = date_parse($_POST['after']);
		$after = mktime(0,0,0,$afterParams['month'],$afterParams['day'],$afterParams['year']);
	} else {
		$state &= 2; //if there's no after data, clear the after bit
	}

	switch ($state){
		case 3: //before and after
		$callback = function($line) use ($before, $after){
			return date_filter($line, $before, False) && date_filter($line, $after, True);
		}; break;
		
		case 2: //before only
		$callback = function($line) use($before){
			return date_filter($line, $before, False);
		}; break;
		
		case 1: //after only
		$callback = function($line) use($after){
			return date_filter($line, $after,True);
		}; break;
		
		default:
		$callback = function($line){return True;};
	}
	$decodedLines = array_filter($decodedLines, $callback);
}

if (isset($_POST['export'])){
	//export text file
	header('Content type: text/plain');
	header('Content-Disposition: attachment; filename="fof_log.txt"');
	foreach ($decodedLines as $line) echo "$line\n";
	
} else {
	//send html output to browser, including log viewer controls
	include('header.php');
	?>
	<link rel="stylesheet" type="text/css" media="all" href="jsdatepick/jsDatePick_ltr.min.css" />
	<script type="text/javascript" src="jsdatepick/jsDatePick.full.1.3.js"></script>
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
 	<form method="post" action="logs.php">
 		<input type="checkbox" name="include_checkbox" <?php echo $include_checkbox_state ? 'checked' : ''; ?>>
 		Show lines containing the text <input type="text" value="<?php echo $inc; ?>" name="include"><br />
 
 		<input type="checkbox" name="exclude_checkbox" <?php echo $exclude_checkbox_state ? 'checked' : ''; ?>>
 		Exclude lines containing <input type="text" value="<?php echo $exc; ?>" name="exclude"><br />
 
 		<input type="checkbox" name="headtail_checkbox" <?php echo $headtail_checkbox_state ? 'checked' : ''; ?>>
 		Search the <select name="headtail"><option value="head">First</option><option value="tail">Last</option></select><select name="headTailQty"><option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option></select> lines<br /><br />

		<input type="checkbox" name="before_checkbox" <?php echo $before_checkbox_state ? 'checked' : ''; ?>>
		Show results from before <input type="text" id="before_id" name="before" value="<?php echo $before;?>"><br />
		
		<input type="checkbox" name="after_checkbox" <?php echo $after_checkbox_state ? 'checked' : ''; ?>>
		Show results from after <input type="text" id="after_id" name="after" value="<?php echo $after;?>"><br /><br />
		
 		<input type="checkbox" name="export" <?php echo $export_state ? 'checked' : ''; ?>>
 		Export the log as a plain text file<br />
 
 		<input type="submit" value="Update" name="update_btn"><br /><br />
 	</form>
 	<textarea rows="20" cols="100">
	<?php
	foreach ($decodedLines as $line){
		$escapedLine = htmlspecialchars($line, ENT_QUOTES);
 		echo "$escapedLine\n\n";
	}
	?>
	</textarea>
<?php
}
?>

