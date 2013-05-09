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
	return addslashes($decoded);
}

//slurp all text, and split lines
if (file_exists('fof.log')) {
	$logLines = file('fof.log');
} else {
	$logLines = array();
}

//decrypt everything
$decodedLines = array_map('decrypt_line', $logLines);
$lineArray = '["' . implode('","', $decodedLines) . '"]';

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
		
		FofLogViewer.allLines = <?php echo $lineArray; ?>;
		FofLogViewer.update();
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
 		<input type="checkbox" name="headtail_checkbox" id="headtail_checkbox" onclick="FofLogViewer.update()" <?php echo $headtail_checkbox_state ? 'checked' : ''; ?>>
 		Search the <select name="headtail" id="head_or_tail" onchange="FofLogViewer.update()"><option value="head">First</option><option value="tail">Last</option></select><select name="headTailQty" id="head_tail_qty" onchange="FofLogViewer.update()"><option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option></select> lines<br /><br />
 		
 		<input type="checkbox" name="include_checkbox" id="include_checkbox" onclick="FofLogViewer.update()" <?php echo $include_checkbox_state ? 'checked' : ''; ?>>
 		Show lines containing the text <input type="text" id = "include" value="<?php echo $inc; ?>" name="include"  onchange="FofLogViewer.update()"><br />
 
 		<input type="checkbox" name="exclude_checkbox" id="exclude_checkbox" onclick="FofLogViewer.update()" <?php echo $exclude_checkbox_state ? 'checked' : ''; ?>>
 		Exclude lines containing <input type="text" id="exclude" value="<?php echo $exc; ?>" name="exclude"  onchange="FofLogViewer.update()"><br /><br />
 

		<input type="checkbox" name="before_checkbox" id="before_checkbox" onclick="FofLogViewer.update()" <?php echo $before_checkbox_state ? 'checked' : ''; ?>>
		Show results from before <input type="text" id="before_id" name="before" value="<?php echo $before;?>"  onchange="FofLogViewer.update()"><br />
		
		<input type="checkbox" name="after_checkbox" id="after_checkbox" onclick="FofLogViewer.update()" <?php echo $after_checkbox_state ? 'checked' : ''; ?>>
		Show results from after <input type="text" id="after_id" name="after" value="<?php echo $after;?>"  onchange="FofLogViewer.update()"><br /><br />
 
 		<input type="submit" value="Update" name="update_btn"></form>
 		<form method="post" action="logs.php">
 		<input type="hidden" name="export" value="yes">
 		<input type="submit" value="Export log as text file" id="export_btn"></form>

 	<br /><br />
 	<textarea rows="20" cols="100" id="text_area">
	</textarea>
<?php
}
?>

