var FofLogViewer = (function () {
	my = {};
	
	var makeSearchFilter = function (needle, positive, insensitive, regex) {
		function escapeRegExp(str) {
  			return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
		}
		searchString = regex ? needle : escapeRegExp(needle);
		searchRegex = insensitive ? new RegExp(searchString,"i") : new RegExp(searchString);
		if (positive) {
			return function(x){
				return x.search(searchRegex) != -1;
			};
		} else {
			return function(x){
				return x.search(searchRegex) == -1;
			}
		}
	};
	
	var makeDateFilter = function(cutoffDateString, after) {
		var cutoff = Date.parse(cutoffDateString);
		return function(lineString) {
			var lineDateString = lineString.substring(0,31);
			var lineDate = Date.parse(lineDateString);
			if (after) {
				return (lineDate > cutoff);
			} else {
				return (lineDate < cutoff);
			}
		};
	};
	
		
	var DOMobjects = [
		{property: 'include',
		checkbox_id: 'include_checkbox',
		input_id: 'include',
		regex_select_id: 'include_regex_select_id',
		insensitive_select_id: 'include_insensitive_select_id',
		filterFunction: function (haystack){
			var regex = document.getElementById(this.regex_select_id).checked;
			var insensitive = document.getElementById(this.insensitive_select_id).checked;
			var needle = document.getElementById(this.input_id).value;
			return haystack.filter(makeSearchFilter(needle, true, insensitive, regex));
		}},
		
		{property: 'exclude',
		checkbox_id: 'exclude_checkbox',
		input_id: 'exclude',
		regex_select_id: 'exclude_regex_select_id',
		insensitive_select_id: 'exclude_insensitive_select_id',
		filterFunction: function (haystack){
			var regex = document.getElementById(this.regex_select_id).checked;
			var insensitive = document.getElementById(this.insensitive_select_id).checked;
			var needle = document.getElementById(this.input_id).value;
			return haystack.filter(makeSearchFilter(needle, false, insensitive, regex));
		}},
		
		{property: 'headtail',
		checkbox_id: 'headtail_checkbox',
		qty_input_id: 'head_tail_qty',
		head_tail_input_id: 'head_or_tail',
		filterFunction: function (haystack){
			var numLines = document.getElementById(this.qty_input_id).value;
			var head = (document.getElementById(this.head_tail_input_id).value == 'head');
			if (head) {
				return haystack.slice(0,numLines);
			}
			else {
				return haystack.slice(-1*numLines);
			}
		}},
		
		{property: 'before',
		checkbox_id: 'before_checkbox',
		input_id: 'before_id',
		filterFunction: function (haystack){
			var dateString = document.getElementById(this.input_id).value;
			return haystack.filter(makeDateFilter(dateString, false));
		}},
		
		{property: 'after',
		checkbox_id: 'after_checkbox',
		input_id: 'after_id',
		filterFunction: function (haystack){
			var dateString = document.getElementById(this.input_id).value;
			return haystack.filter(makeDateFilter(dateString, true));
		}}
		
	];
	
	my.update = function() {
		var newLines = my.allLines;
		DOMobjects.forEach(function (obj) {
			if (document.getElementById(obj.checkbox_id).checked) {
				newLines = obj.filterFunction(newLines);
			}
		});
		
		//place the new lines into the text area
		var logText = newLines.join('\n\n');
		document.getElementById('text_area').value = logText;
		
	};
	
	my.clear = function(hash) {
		//clears the log file
		if (confirm("Are you sure you wish to delete the log file?")){
			var url = "clear-logs.php";
			var params = {clear: 'true', CSRF_hash: hash};
			var complete = function (response) { alert(response.responseText); };
			var options = { method: 'post', parameters: params, onComplete: complete };
    
			new Ajax.Request(url, options);
		}
		return false;
	};
	return my;
})();
