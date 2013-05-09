FofLogViewer = (function () {
	my = {};
	
	var makeSearchFilter = function (needle, positive = true) {
		if (positive) {
			return function(x){
				return x.search(needle) != -1;
			};
		} else {
			return function(x){
				return x.search(needle) == -1;
			}
		}
	};
	
	var makeDateFilter = function(cutoffDateString, after = true) {
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
		filterFunction: function (haystack){
			var needle = document.getElementById(this.input_id).value;
			return haystack.filter(makeSearchFilter(needle, true));
		}},
		
		{property: 'exclude',
		checkbox_id: 'exclude_checkbox',
		input_id: 'exclude',
		filterFunction: function (haystack){
			var needle = document.getElementById(this.input_id).value;
			return haystack.filter(makeSearchFilter(needle, false));
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
	return my;
})();
