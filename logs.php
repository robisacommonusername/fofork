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
 * Copyright (C) 2012-2013 Robert Palmer
 *
 * Distributed under the GPL - see LICENSE
 *
 */
 
require_once('fof-main.php');
require_once('classes/AES.php');

if (!fof_is_admin()){
	die('Only admin may view the logs!');
}

//slurp all text, and split lines
if (file_exists('fof.log')) {
	$logLines = file('fof.log');
} else {
	$logLines = array();
}

//decrypt everything
//Things misbehave if we try reusing the same aes instance with different
//IVs but same key (for some reason).  Thus we create a new AES instance
//on every iteration here.
$pwd = fof_db_log_password();
$decodedLines = array_map(function($line) use ($pwd) {
		$decoded = base64_decode($line);
		$aes = new Crypt_AES();
		$IV = substr($decoded, 0, 16);
		$ct = substr($decoded, 16);
		$aes->setIV($IV);
		$aes->setKey($pwd);
		return addslashes($aes->decrypt($ct));
	}, $logLines);
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
 		<input type="hidden" name="export" value="yes">
 		<input type="submit" value="Export log as text file" id="export_btn"></form>

 	<br /><br />
 	<textarea rows="20" cols="100" id="text_area">
	</textarea>
<?php
}
?>

