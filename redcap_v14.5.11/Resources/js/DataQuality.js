
// Highlight rule row in table
function highlightRuleRow(rule_id) {
	$('#ruleorder_'+rule_id).parent().parent().effect('highlight',3000);
	$('#rulename_'+rule_id).parent().parent().effect('highlight',3000);
	$('#rulelogic_'+rule_id).parent().parent().effect('highlight',3000);
	$('#ruleexe_'+rule_id).parent().parent().effect('highlight',3000);
	$('#ruledel_'+rule_id).parent().parent().effect('highlight',3000);
	$('#rulerte_'+rule_id).parent().parent().effect('highlight',3000);
	$('.dagr_'+rule_id).parent().parent().effect('highlight',3000);
}

// Enable table for editing
function enableRuleTableEdit() {
	// Set specific padding for the "discrepancies" column and DAG columns
	$('.exebtn').parent().css({'padding':'0','width':'100%'});
	// Determine if we should set the table as editable
	if (!allowTableEdit) return;
	// Enable mouseover for rule real-time execution checkbox
	$('#table-rules .editrte').mouseenter(function(){
		// If checkbox is disabled, then give pencil option to enable it
		var RTEcheckbox = $(this).children('input[type="checkbox"]:first');
		if (RTEcheckbox.length && RTEcheckbox.prop('disabled')) {
			// Activate
			$(this).css('cursor','pointer');
			$(this).addClass('edit_active');
			$(this).prop('title','Click to enable editing');
		}
	}).mouseleave(function() {
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
	});
	// Enable click event for rule real-time execution pencil
	$('#table-rules .editrte').click(function(){
		var RTEcheckbox = $(this).children('input[type="checkbox"]:first');
		var RTEcheckboxImg = $(this).children('img:first');
		var RTEcheckboxBtn = $(this).children('button:first');
		if (RTEcheckbox.length && RTEcheckbox.prop('disabled')) {
			RTEcheckbox.show().prop('disabled', false);
			RTEcheckboxBtn.show();
			RTEcheckboxImg.hide();
		}
	});
	// Enable rule name edit mouseover
	$('#table-rules .editname').mouseenter(function(){
		// If already clicked or is pre-defined rule, then do not enable editing of cell
		if ($(this).html().indexOf('<textarea ') > -1 || $(this).html().indexOf('pd-rule') > -1) {
			$(this).unbind('click');
			return;
		}
		// Activate
		$(this).css('cursor','pointer');
		$(this).addClass('edit_active');
		$(this).prop('title','Click to enable editing');
	}).mouseleave(function() {
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
	});
	// Rule name onclick edit action
	$('#table-rules .editname').click(function(){
		// If already clicked
		if ($(this).html().indexOf('<textarea ') > -1) {
			$(this).unbind('click');
			return;
		}
		// Undo css
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
		$(this).unbind('click');
		var thisRuleName = $(this).text();
		var thisRuleId = $(this).attr('rid');
		$(this).html( '<textarea id="input_rulename_id_'+thisRuleId+'" class="x-form-field notesbox" style="height:60px;margin:4px 0;width:95%;">'+thisRuleName+'</textarea>'
					+ '<br><button style="vertical-align:middle;" onclick="saveRuleName('+thisRuleId+');">Save</button>');
		// Enable widgets/buttons
		initWidgets();
	});
	// Enable rule logic edit mouseover
	$('#table-rules .editlogic').mouseenter(function(){
		// If already clicked
		if ($(this).html().indexOf('<textarea ') > -1 || $(this).html().indexOf('pd-rule') > -1) {
			$(this).unbind('click');
			return;
		}
		$(this).css('cursor','pointer');
		$(this).addClass('edit_active');
		$(this).prop('title','Click to enable editing');
	}).mouseleave(function() {
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
	});
	// Rule logic onclick edit action
	$('#table-rules .editlogic').click(function(){
		// If already clicked
		if ($(this).html().indexOf('<textarea ') > -1) {
			$(this).unbind('click');
			return;
		}
		// Undo css
		$(this).css('cursor','');
		$(this).removeClass('edit_active');
		$(this).removeAttr('title');
		$(this).unbind('click');
		var thisRuleLogic = $(this).text();
		var thisRuleId = $(this).attr('rid');
		$(this).html( '<textarea id="input_rulelogic_id_'+thisRuleId+'" class="x-form-field notesbox" onfocus=\'openLogicEditor($(this))\' onkeydown=\'logicSuggestSearchTip(this, event);\' style="font-size:12px;height:60px;margin:4px 0;width:95%;resize:auto;" onblur=\'var val = this; setTimeout(function() { logicHideSearchTip(val); if(!checkLogicErrors(this.value,1)){validate_logic(this.value,"",0);} }, 0);\'>'+thisRuleLogic+'</textarea>'
							+ '<div id="LSC_id_input_rulelogic_id_'+thisRuleId+'" class="fs-item-parent fs-item"></div>'
							+ '<br><button style="vertical-align:middle;" onclick=\'if(!checkLogicErrors($("#input_rulelogic_id_'+thisRuleId+'").val(),1)){validate_logic($("#input_rulelogic_id_'+thisRuleId+'").val(),"",2,'+thisRuleId+');}\'>Save</button>'
							+ '<div style="border: 0; font-weight: bold; text-align: left; vertical-align: middle; height: 20px;" id="input_rulelogic_id_'+thisRuleId+'_Ok">&nbsp;</div>'
		);
		// Enable widgets/buttons
		initWidgets();
		$('#input_rulelogic_id_'+thisRuleId).focus();
	});
	// Add dragHangle to each row of the table
	$("#table-rules tr").each(function() {
		this_rid = trim($(this.cells[0]).text());
		if (isNumeric(this_rid)) {
			$(this.cells[0]).addClass('dragHandle');
			$(this).addClass('dragRow');
		}
	});
	// Enable drag-n-drop on table for reordering
	$('#table-rules').tableDnD({
		onDrop: function(table, row) {
			// Loop through table
			var i = 1;
			var rids = "";
			var this_rid;
			var current_rid = trim($(row.cells[0]).text());
			$("#table-rules tr").each(function() {
				// Restripe table
				$(this).removeClass('erow');
				if (i%2 == 0) $(this).addClass('erow');
				// Gather link_nums
				this_rid = trim($(this.cells[0]).text());
				if (isNumeric(this_rid)) {
					rids += this_rid + ",";
					// Reorder the rule #s
					$('#ruleorder_'+this_rid).html(i);
					i++;
				}
			});
			// Save form order
			$.post(app_path_webroot+'DataQuality/edit_rule_ajax.php?pid='+pid, { rule_id: 0, action: 'reorder', rule_ids: rids }, function(data){
				if (data != '1') {
					alert(woops);
					window.location.reload();
				} else {
					highlightRuleRow(current_rid);
				}
			});
		},
		dragHandle: "dragHandle"
	});
	// Create mouseover image for drag-n-drop and enable button fading on row hover
	$("#table-rules tr.dragRow").mouseenter(function() {
		$(this.cells[0]).css('background','#ffffff url("'+app_path_images+'updown.gif") no-repeat center');
		$(this.cells[0]).css('cursor','move');
	}).mouseleave(function() {
		$(this.cells[0]).css('background','');
		$(this.cells[0]).css('cursor','');
	});
}
// Save the new rule name via ajax
function saveRuleName(thisRuleId) {
	var thisRuleName = trim($('#input_rulename_id_'+thisRuleId).val());
	$.post(app_path_webroot+'DataQuality/edit_rule_ajax.php?pid='+pid, { rule_name: thisRuleName, rule_id: thisRuleId }, function(data){
		var data2 = data;
		if (data.length<1) data2 = '&nbsp;';
		$('#rulename_'+thisRuleId).html(data2);
		$('#rulename_'+thisRuleId).addClass('edit_saved');
		setTimeout(function(){
			$('#rulename_'+thisRuleId).removeClass('edit_saved');
		},2000);
		enableRuleTableEdit();
	});
}
// Save the new rule logic via ajax
function saveRuleLogic(thisRuleId) {
	var thisRuleLogic = trim($('#input_rulelogic_id_'+thisRuleId).val());
	$.post(app_path_webroot+'DataQuality/edit_rule_ajax.php?pid='+pid, { rule_logic: thisRuleLogic, rule_id: thisRuleId }, function(data){
		var data2 = data;
		if (data.length<1) data2 = '&nbsp;';
		$('#rulelogic_'+thisRuleId).html(htmlspecialchars(data2));
		$('#rulelogic_'+thisRuleId).addClass('edit_saved');
		setTimeout(function(){
			$('#rulelogic_'+thisRuleId).removeClass('edit_saved');
		},2000);
		enableRuleTableEdit();
	});
}

// Delete an existing rule
function deleteRule(rule_id) {
	if (confirm("Are you sure you wish to delete rule #"+$('#ruleorder_'+rule_id).text()+"?")) {
		$.post(app_path_webroot+'DataQuality/edit_rule_ajax.php?pid='+pid, { rule_id: rule_id, action: 'delete' }, function(data){
			if (data == '0') {
				alert(woops);
			} else {
				highlightRuleRow(rule_id);
				setTimeout(function(){
					$('#table-rules-parent').html(data);
					enableRuleTableEdit();
				},1000);
			}
			enableRuleTableEdit();
		});
	}
}

// Validate the fields in the user-defined logic as real fields
function validate_logic(thisRuleLogic,thisRuleName,saveIt,thisRuleId) {
	// First, make sure that the logic is not blank
	if (typeof thisRuleLogic == 'undefined' || trim(thisRuleLogic).length < 1) return;
	// Make ajax request to check the logic via PHP
	$.post(app_path_webroot+'Design/logic_validate.php?pid='+pid, { logic: thisRuleLogic, forceMetadataTable: 1 }, function(data){
		if (data == '1') {
			// Save new rule's name and logic via ajax
			if (saveIt == 1) {
				// Create new rule
				addNewRuleAjax(thisRuleName,thisRuleLogic);
			} else if (saveIt == 2 && thisRuleId != '') {
				// Edit existing rule
				saveRuleLogic(thisRuleId);
			}
		} else if (data == '0') {
			alert(woops);
			return false;
		} else {
			alert(data);
			return false;
		}
	});
}

// Save new rule's name and logic via ajax (part 1)
function addNewRule() {
	var thisRuleName = trim($('#input_rulename_id_0').val());
	var thisRuleLogic = trim($('#input_rulelogic_id_0').val());
	if (thisRuleName.length < 1 || thisRuleLogic.length < 1) {
		alert('Please enter both a name and logic for the new rule');
		return;
	}
	// Do quick logic check
	if (checkLogicErrors(thisRuleLogic,1)) {
		return;
	}
	// Now validate the fields in the logic, which will also save the name/logic via ajax
	validate_logic(thisRuleLogic,thisRuleName,1,'');
}

// Save new rule's name and logic via ajax (part 2)
function addNewRuleAjax(thisRuleName,thisRuleLogic) {
	// Get value of real-time execution checkbox
	var rte = ($('#rulerte_id_0').prop('checked')) ? '1' : '0';
	// Do ajax call
	$.post(app_path_webroot+'DataQuality/edit_rule_ajax.php?pid='+pid, { rule_name: thisRuleName, rule_logic: thisRuleLogic, rule_id: 0, real_time_execute: rte }, function(data){
		var json_data = jQuery.parseJSON(data);
		if (json_data.length < 1) {
			alert(woops);
			return;
		}
		// Set variables
		var new_rule_id = json_data.new_rule_id;
		var html = json_data.payload;
		// Add html to page
		$('#table-rules-parent').html(html);
		enableRuleTableEdit();
		highlightRuleRow(new_rule_id);
		// Add new rule_id to delimited list var of rule_ids
		rule_ids = (rule_ids == '') ? ''+new_rule_id+'' : rule_ids+','+new_rule_id;
	});
}

// Run some things before firing the actual ajax requests
function preExecuteRulesAjax(rule_ids,show_exclusions,excludeAB) {
	if (typeof excludeAB == 'undefined') excludeAB = 0;
	// Set current_rule_ids
	current_rule_ids = rule_ids;
	if (excludeAB) {
		current_rule_ids.replace('pd-3,','').replace('pd-6,','');
	}
	if (rule_ids.length < 1) {
		alert('No rule is selected. Select a rule and try again.');
		$('.execRuleBtn').css('color','#000000').prop('disabled',false);
		return;
	}
	// Reset all divs, buttons, etc.
	$('#rule_num_progress').html('0');
	$('#rule_num_total').html( rule_ids.split(",").length );
	$('#execRuleProgress, #execRuleProgress').show();
	$('#execRuleComplete').hide();
	// Loop through rule_id's and set spinning icon for each
	var rule_array = rule_ids.split(',');
	var progressIcon = $('#progressIcon').html();
	var resetDagCounts = ($('.exegroup').length > 0);
	for (k=0; k<rule_array.length; k++) {
		$('#ruleexe_'+rule_array[k]).html(progressIcon).removeClass('red').removeClass('darkgreen');
		if (resetDagCounts) {
			$('.dagr_'+rule_array[k]).html('');
		}
	}
	// Execute rule(s) - run 3 simultaneous threads
	executeRulesAjax(null,show_exclusions,0);
	setTimeout(function(){
		executeRulesAjax(null,show_exclusions,0);
	}, 500);
	setTimeout(function(){
		executeRulesAjax(null,show_exclusions,0);
	}, 1000);
}

// Reload a single results table
function reloadRuleAjax(rule_id,show_exclusions,replaceRuleTable) {
	$('#reload_dq_'+rule_id).show();
	executeRulesAjax(rule_id,show_exclusions,replaceRuleTable);
}

function exportRulesDiscrepancies(rule_id) {
	var dag = $('#dqRuleDag').length ? $('#dqRuleDag').val() : "";
	var url = app_path_webroot+'DataQuality/download_dq_discrepancies.php?pid='+pid+'&rule_id='+rule_id+'&record='+$('#dqRuleRecord').val()+'&dag='+dag;
	showDownloadProgress(1);
	$.fileDownload(url)
		.done(function () { showDownloadProgress(0); })
		.fail(function () { showDownloadProgress(2); });

}

// Begin series of ajax requests to handle each rule
function executeRulesAjax(rule_ids,show_exclusions,replaceRuleTable,action) {
	// If rule_ids is null, then use current_rule_ids
	var use_current_rule_ids = false;
	if (rule_ids == null) {
		if (current_rule_ids == '') return;
		use_current_rule_ids = true;
		// Pop off the first 3 rule_ids and run those
		var current_rule_ids_array = current_rule_ids.split(",");
		rule_ids = current_rule_ids_array.shift();
		current_rule_ids = current_rule_ids_array.join(",");
	}
	$('#clearBtn').css('color','#000000').prop('disabled',false);
	// Increment the progress rule count
	var rule_num_progress = $('#rule_num_progress').html()*1 + 1;
	$('#rule_num_progress').html(rule_num_progress);
	// Show progress message
	if (action != null) showProgress(1);
	// Ajax request
	var thisAjax = $.post(app_path_webroot+'DataQuality/execute_ajax.php?pid='+pid, { record: $('#dqRuleRecord').val(), dag: $('#dqRuleDag').val(), rule_ids: rule_ids, show_exclusions: show_exclusions, action: action }, function(data){
		// Get data returned
		if (data == '') {
			simpleDialog('ERROR: An unknown error occurred! This may mean that the Data Quality rule took too long or that it was trying '
				+ 'to process too much data. Please reload the page and try again. If the rule never finishes executing, then it may never successfully complete for this project, unfortunately.');
			return;
		}
		var json_data = jQuery.parseJSON(data);
		if (json_data.length < 1) {
			alert(woops);
			window.location.reload();
			return;
		}
		// Do doing specific action and not just displaying discrepancies, then hide progress message
		if (action != null) {
			showProgress(0,0);
			setTimeout(function(){
				$('#results_table_'+rule_ids).dialog('close');
				simpleDialog(json_data.payload, json_data.title);
				preExecuteRulesAjax(rule_ids,0);
			},300);
			return;
		}
		// Set variables
		var rule_id = json_data.rule_id;
		var next_rule_ids = json_data.next_rule_ids;
		var html = json_data.payload;
		var title = json_data.title;
		var discrep = json_data.discrepancies*1;
		var discrepf = json_data.discrepancies_formatted;
		var dag_discrep = json_data.dag_discrepancies;
		var exclusion_count = json_data.exclusion_count;
		// Add html to page
		if (replaceRuleTable == 0) {
			// Append to last table
			$('#dq_results').append(html);
			// Replace spinning icon with number of discrepancies
			if (discrep > 0) {
				var discrepclass = 'red';
				var textColor = 'font-weight:bold;color:red;';
			} else {
				var discrepclass = 'darkgreen';
				var textColor = 'color:green;';
			}
			var discrep_text = "<div style='display:inline-block;font-size:15px;width:60px;text-align:center;"+textColor+"'>"+discrepf+"</div>"
								+ "<div style='display:inline-block;'>";
			if (discrep > 0 || exclusion_count > 0) {
				if (discrep > 0) {
					discrep_text += "<a href=\"javascript:;\" onclick=\"exportRulesDiscrepancies('" + rule_id + "');\" style='font-size:11px;text-decoration:underline;'>export</a>";
					discrep_text += "<span style=\"margin:0 2px;color:#999;\">|</span>";
				}
				discrep_text += "<a href=\"javascript:;\" onclick=\"viewResults('" + rule_id + "','" + escapeHtml(title) + "');\" style='font-size:11px;text-decoration:underline;'>view</a></div>";
			}
			discrep_text += "<div style='clear:both:height:0;'></div>";

			$('#ruleexe_'+rule_id).html(discrep_text).addClass(discrepclass).css({'margin':'0','border':'0'});
			// If DAG columns exist in table, then add their values	to the table cells
			if (dag_discrep.length > 0) {
				for (k=0; k<dag_discrep.length; k++) {
					var dag_discrep_temp = dag_discrep[k].split(",");
					var dag_count = dag_discrep_temp[1];
					var group_id = dag_discrep_temp[0];
					$('#ruleexe_'+rule_id+'-'+group_id).html(dag_count);
					var dagcolor = (dag_count*1 > 0) ? 'red' : 'green';
					$('#ruleexe_'+rule_id+'-'+group_id).css({'color':dagcolor});
				}
			}
			// Adjust cell height so it has no whitespace around it (looks nicer this way)
			var td_height = $('#ruleexe_'+rule_id).parent().parent().outerHeight(true)-8; // 8 comes from padding top/bottom of div
			if (td_height > $('#ruleexe_'+rule_id).height()) {
				$('#ruleexe_'+rule_id).height(td_height);
			}
			// Add "title" attribute to results table div
			//$('#results_table_'+rule_id).attr('title',title);
		} else {
			// Replace existing table
			if ($('#results_table_'+replaceRuleTable).hasClass('ui-dialog-content')) $('#results_table_'+replaceRuleTable).dialog('destroy');
			$('#results_table_'+replaceRuleTable).remove();
			$('#dq_results').append(html);
			//$('#results_table_'+replaceRuleTable).attr('title',title);
			viewResults(replaceRuleTable,title);
		}
		// Perform the next ajax request if more rules still need to be processed
		if (use_current_rule_ids && current_rule_ids != '') {
			executeRulesAjax(null,show_exclusions,replaceRuleTable);
		} else if (!use_current_rule_ids && next_rule_ids.length > 0) {
			executeRulesAjax(next_rule_ids,show_exclusions,replaceRuleTable);
		} else {
			$('#execRuleComplete').show();
			$('#execRuleProgress').hide();
		}
	});	
	// If Ajax call does not return after X seconds, then throw error
	var maxAjaxTime = 20; // minutes
	setTimeout(function(){
		if (thisAjax.readyState == 1) {
			// Abort, which will trigger ajax error
			thisAjax.abort();
			$('#execRuleComplete').show();
			$('#execRuleProgress').hide();
			$('div.exebtn img').parent().html('ERROR');
			simpleDialog('Sorry, but the Data Quality rule exceeded the maximum processing time. It may be that there is just too much data in'
				+' the project for it to process all at once. Please try again, but if it fails again, then it will likely not ever work successfully. Our apologies for any inconvenience.','ERROR: Request timed out');
		}
	},maxAjaxTime*60000);
}

//Display "Working" div as progress indicator
function showDownloadProgress(show,ms) {
	// Set default time for fade-in/fade-out
	if (ms == null) ms = 500;
	if (!$("#downloading").length) 	$('body').append('<div id="downloading"><img alt="Downloading..." src="'+app_path_images+'downloading.gif">&nbsp; '+lang_download_message_text+' <br>'+lang_wait_text+'</div>');
	if (!$("#fade").length) 	$('body').append('<div id="fade"></div>');
	if (show == 1) { // In Process
		$('#fade').addClass('black_overlay').show();
		$('#downloading').center().fadeIn(ms);
	} else if (show == 0) { // Success
		setTimeout(function(){
			$("#fade").removeClass('black_overlay').hide();
			$("#downloading").fadeOut(ms);
		},ms);
		simpleDialogAlt("<div id='success-download' style='color:green;font-size:13px;'><img src='"+app_path_images+"tick.png'> "+lang_download_success_text+"</div>", 3, 450);
	} else { // Error
		setTimeout(function(){
			$("#fade").removeClass('black_overlay').hide();
			$("#downloading").fadeOut(ms);
		},ms);
		simpleDialogAlt("<div id='error-download' style='color:red;font-size:13px;'><img src='"+app_path_images+"cross.png'> "+land_download_error+"</div>", 3, 450);
	}
}

// Highlight a specific results table
function viewResults(rule_id,title) {
	$('#results_table_'+rule_id).dialog({ title: title, bgiframe: true, modal: true, width: 720, height: 600,
		buttons: {'Close':function(){$(this).dialog("close");}}
	});
	initWidgets();
}

// Change the current status
function changeStatus(rule_id,record,event_id,field_name) {
	// Do ajax call to save status
	$.post(app_path_webroot+'DataQuality/edit_comlog_ajax.php?pid='+pid, { field_name: field_name, status: $('#currentStatusEdit').val(), rule_id: rule_id, record: record, event_id: event_id }, function(data){
		if (data == '1') {
			// Close this dialog and reload the one underneath it
			if ($('#comLog').hasClass('ui-dialog-content')) $('#comLog').dialog('destroy');
			showComLog(rule_id,record,event_id,field_name);
		} else {
			alert(woops);
		}
	});
}

// Loads Communication Log pop-up for a specific rule-record-event
function showComLog(rule_id,record,event_id,field_name) {
	$('#comLogLoading').show();
	// Show dialog with "loading..."
	$('#comLog').dialog({ bgiframe: true, modal: true, width: 800, height: 500, close: function(){ $('#comLogComments').html(''); },
		buttons: {
			'Close': function() {
				$(this).dialog("close");
			},
			'Add New Comment': function() {
				// Open "add new" pop-up
				$('#newComment').val('');
				$('#comLogAddNew').dialog({ bgiframe: true, modal: true, width: 400,
					buttons: {
						'Close': function() { $(this).dialog("close");},
						'Add': function() {
							$('#newComment').val(trim($('#newComment').val()));
							var newComment = $('#newComment').val();
							if (newComment.length < 1) {
								alert('Please enter a comment first.');
								return;
							}
							// Do ajax call to save comment
							$.post(app_path_webroot+'DataQuality/edit_comlog_ajax.php?pid='+pid, { field_name: field_name, comment: newComment, rule_id: rule_id, record: record, event_id: event_id }, function(data){
								if (data == '1') {
									// Close this dialog and reload the one underneath it
									if ($('#comLogAddNew').hasClass('ui-dialog-content')) $('#comLogAddNew').dialog('destroy');
									if ($('#comLog').hasClass('ui-dialog-content')) $('#comLog').dialog('destroy');
									showComLog(rule_id,record,event_id,field_name);
								} else {
									alert(woops);
								}
							});
						}
					}
				});
			}
		}
	});
	// Do ajax call to get content for dialog
	$.post(app_path_webroot+'DataQuality/communication_log.php?pid='+pid, { field_name: field_name, rule_id: rule_id, record: record, event_id: event_id }, function(data){
		$('#comLogLoading').hide();
		$('#comLogComments').html(data);
	});
}

// Enable/disable the DQ real-time execution
function enableDQRTE(rule_id) {
	var real_time_execute = ($('#rulerte_newvalue_'+rule_id).prop('checked') ? 1 : 0);
	// Do ajax call to get content for dialog
	$.post(app_path_webroot+'DataQuality/edit_rule_ajax.php?pid='+pid, { action: 'enableRTE', rule_id: rule_id, real_time_execute: real_time_execute }, function(data){
		// Success!
		$('#rulerte_'+rule_id).children('input[type="checkbox"]:first').prop('disabled',true).hide();
		// Swap icons now that it's changed
		$('#rulerte_'+rule_id).children('button:first').hide();
		var newRTEcheckboxImgOb = $('#rulerte_'+rule_id).children('img:first');
		var newRTEcheckboxImgSrc = (real_time_execute) ? 'accept.png' : 'stop_gray.png';
		newRTEcheckboxImgOb.attr('src', app_path_images+newRTEcheckboxImgSrc).show();
		$('#rulerte_'+rule_id).parent().effect('highlight',{ },2500);
	});
}
// Data Quality: Display the explainRTE dialog
function explainDQRTE() {
	simpleDialog(null,null,'explain_rte',550);
}

// Open DRW Introduction pop-up
function openDataResolutionIntroPopup() {
	$.post(app_path_webroot+"DataQuality/data_resolution_intro_popup.php?pid="+pid, { }, function(data){
		var json_data = jQuery.parseJSON(data);
		simpleDialog(json_data.content,json_data.title,'drw_intro_popup',700);
		fitDialog($('#drw_intro_popup'));
	});
}

// Reload the Data Resolution Log table in Data Quality module
function dataResLogReload(show_progress) {
	var status_type = $('#choose_status_type').val();
	var field_rule = $('#choose_field_rule').val();
	var group_id = ($('#choose_dag').length) ? $('#choose_dag').val() : '';
	var event_id = ($('#choose_event').length) ? $('#choose_event').val() : '';
	var assigned_user_id = ($('#choose_assigned_user').length) ? $('#choose_assigned_user').val() : '';
	var query_string = 'pid='+pid+'&status_type='+status_type+'&field_rule_filter='+field_rule;
	if (group_id != '') query_string += '&group_id='+group_id;
	if (event_id != '') query_string += '&event_id='+event_id;
	if (assigned_user_id != '') query_string += '&assigned_user_id='+assigned_user_id;
	show_progress = !!show_progress;
	if (show_progress) showProgress(1);
	$.post(app_path_webroot+'DataQuality/resolve_ajax.php?'+query_string,{},function(data){
		// Parse JSON
		var json_data = jQuery.parseJSON(data);
		// Replace table html
		$('#resTableParent').html(json_data.html);
		// Update count in tab badge
		$('#dq_tab_issue_count').html(json_data.num_issues);
		// Initialize other things
		initWidgets();
		if (show_progress) showProgress(0);
		// Modify URL without reloading page
		modifyURL(app_path_webroot+page+'?'+query_string);
	});
}

$(function(){
	// Click button to open DRW popup if status_id is passed in URL
	if (getParameterByName('status_id') != '') {
		$('#dq-statusid-'+getParameterByName('status_id')).trigger('click');
	}
});