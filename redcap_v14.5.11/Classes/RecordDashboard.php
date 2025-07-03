<?php

/**
 * RecordDashboard
 * This class is used for processes related to the Record Status Dashboad.
 */
class RecordDashboard
{	
	// Return array of settings for Custom Record Status Dashboard using the rd_id
	public static function getRecordDashboardSettings($rd_id=null)
	{
		global $lang, $Proj;
		// Validate rd_id
		$rd_id = (int)$rd_id;
		// Set default dashboard settings
		$dashboard = getTableColumns('redcap_record_dashboards');
		// If we're showing the default dashboard, then return default array
		if (empty($rd_id)) return $dashboard;
		// Get the dashboard
		$sql = "select * from redcap_record_dashboards where rd_id = $rd_id and project_id = ".PROJECT_ID;
		$q = db_query($sql);
		if ($q && db_num_rows($q)) {
			// Overlay values from table
			$dashboard = db_fetch_assoc($q);
		}
		// Always force group_by event if classic project
		if (!$Proj->longitudinal) {
			$dashboard['group_by'] = 'event';
			$dashboard['sort_event_id'] = $Proj->firstEventId;
		}
		return $dashboard;		
	}
	
	// Return array as list of all Custom Record Status Dashboards for this project (including the default)
	public static function getRecordDashboardsList($addBlankArrayOption=false)
	{
		global $lang;
		$dashboard = array();
		// Set default dashboard settings
		if ($addBlankArrayOption) $dashboard[''] = array('title'=>$lang['data_entry_333']);
		// Get the dashboard
		$sql = "select * from redcap_record_dashboards where project_id = ".PROJECT_ID." order by title";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			unset($row['project_id']);
			$rd_id = $row['rd_id'];
			unset($row['rd_id']);
			$dashboard[$rd_id] = $row;
		}
		return $dashboard;
	}
	
	// Delete a Custom Record Status Dashboard
	public static function deleteDashboard()
	{
		global $lang;
		// Get existing dashboard, if exists, else get defaults
		$rd_id = (isset($_POST['rd_id']) && is_numeric($_POST['rd_id'])) ? (int)$_POST['rd_id'] : null;
		// Delete the dashboard
		$sql = "delete from redcap_record_dashboards where rd_id = $rd_id and project_id = ".PROJECT_ID;
		if (db_query($sql)) {
			// Log the event
			Logging::logEvent($sql, "redcap_record_dashboards", "MANAGE", $rd_id, "rd_id = $rd_id", "Delete custom record dashboard");
			// Return json payload
			$content = 	RCView::div(array('class'=>'darkgreen', 'style'=>'font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['data_entry_354']
						);
			print json_encode_rc(array('title'=>$lang['create_project_102'], 'content'=>$content,
									'button'=>$lang['calendar_popup_01']));
		} else {
			// Error
			print '0';
		}
	}
	
	// Save a Custom Record Status Dashboard
	public static function saveDashboard()
	{
		global $Proj, $lang;
		// Get existing dashboard, if exists, else get defaults
		$rd_id = (isset($_POST['rd_id']) && is_numeric($_POST['rd_id'])) ? (int)$_POST['rd_id'] : null;
		$dashboard = self::getRecordDashboardSettings($rd_id);
		// Apply new values on top of array
		$dashboard['project_id'] = PROJECT_ID;
		if ($rd_id === null) unset($dashboard['rd_id']);
		foreach (array_keys($dashboard) as $key) {
			if (isset($_POST[$key])) {
				// Get value from Post
				$val = $_POST[$key];
				// Parse the select instruments/events string
				if ($key == 'selected_forms_events') {
					$val = $Proj->convertSelectedFormsEventsToBackend($val);
				}
				// Set in array
				$dashboard[$key] = $val;
			}
		}
		// Save the dashboard
		$sql = "replace into redcap_record_dashboards (".implode(", ", array_keys($dashboard)).") 
				values (".prep_implode($dashboard, true, true).")";
		if (db_query($sql)) {
			// Success
			$rd_id_new = db_insert_id();
			// Log the event
			Logging::logEvent($sql, "redcap_record_dashboards", "MANAGE", $rd_id_new, "rd_id = $rd_id_new", ($rd_id == null ? "Create custom record dashboard" : "Modify custom record dashboard"));
			// Return json payload
			$content = 	RCView::div(array('class'=>'darkgreen', 'style'=>'font-size:14px;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['data_entry_351']
						);
			print json_encode_rc(array('title'=>$lang['survey_605'], 'content'=>$content, 'rd_id'=>$rd_id_new,
									'button'=>($rd_id === null ? $lang['data_entry_350'] : $lang['data_entry_349'])));
		} else {
			// Error
			print '0';
		}
	}
	
	// Generate the code to edit the filter
	public static function renderSetup($dashboard=array()) 
	{
		global $Proj, $lang;
		
		// Arms Options		
		$arms_select = false;		
		if ($Proj->multiple_arms) {		
			$arms_options = array(''=>$lang['data_entry_352']);		
			foreach ($Proj->events as $arm_num => $arm_detail) {		
				$arms_options[$arm_num] = $lang['global_08']." $arm_num".$lang['colon']." ".label_decode($arm_detail['name']);		
			}		
			$arms_select = RCView::select(array(		
				'id'=>'arm','name'=>'arm','class'=>'x-form-text x-form-field'),		
				$arms_options, $dashboard['arm']);		
		}
		
		// If no sort field is set, the select the record ID field
		if ($dashboard['sort_field_name'] == '') $dashboard['sort_field_name'] = $Proj->table_pk;
		
		// Set options for event order drop-down
		$event_dropdown_options = array();
		if ($Proj->longitudinal) {
			foreach ($Proj->eventInfo as $this_event_id=>$attr) {
				$event_dropdown_options[$this_event_id] = $attr['name_ext'];
			}
		}
		
		// Make the Config box (initially not displayed)
		$html =	RCView::form(array('id'=>'dashboard-config', 'class'=>'chklist trigger', 'style'=>"margin:25px 0;background-color: #eee;border:1px solid #ccc;max-width:775px;display:none;"),
			RCView::div(array('class'=>'chklisthdr', 'style'=>'font-size:15px;color:#393733;margin-bottom:5px;'), 				
				RCView::i(array('class'=>'fas fa-pencil-alt', 'style'=>'font-size:12px;'), '') . ' ' .$lang['data_entry_336']
			).	
			RCView::p(array(),$lang['data_entry_337']).
			RCView::table(array('class'=>'tbi', 'style'=>'width:100%'),
				RCView::tr(array(),
					RCView::td(array('class'=>'td1'), 
						$lang['data_entry_338']
					).
					RCView::td(array('class'=>'td2'),
						RCView::input(array(
							'id'=>'title', 
							'class'=>'x-form-text x-form-field',
							'style'=>'width:80%;max-width:500px;',
							'name'=>'title',
							'value'=>htmlentities($dashboard['title']??"",ENT_QUOTES))
						).
						RCView::input(array(
							'id'=>'rd_id', 
							'type'=>'hidden',
							'name'=>'rd_id',
							'value'=>$dashboard['rd_id'])
						)
					)
				).
				RCView::tr(array(),
					RCView::td(array('class'=>'td1', 'style'=>'padding-top:10px;'), $lang['data_entry_339'] .
						RCView::div(array('class'=>'cc_info'), $lang['data_entry_358'])).
					RCView::td(array('class'=>'td2', 'style'=>'padding-top:10px;'),
						RCView::textarea(array(
							'class'=>'x-form-text x-form-field',
							'style'=>'width:80%;max-width:500px;height:40px;',
							'id'=>'description',
							'name'=>'description'), 
						htmlentities($dashboard['description']??"",ENT_QUOTES))
					)
				).
				RCView::tr(array(),
					RCView::td(array('class'=>'td1', 'style'=>'padding-top:10px;padding-bottom:10px;'), $lang['data_entry_345']).
					RCView::td(array('class'=>'td2', 'style'=>'padding-top:10px;padding-bottom:10px;'),
						RCView::select(array(
							'id'=>'orientation', 'name'=>'orientation','class'=>'x-form-text x-form-field'),
							array('H'=>$lang['data_entry_360'],'V'=>$lang['data_entry_361']), $dashboard['orientation']
						) .
						RCView::span(array('class'=>'cc_info', 'style'=>'margin-left:10px;'), $lang['data_entry_359'])
					)
				).
				(!$Proj->longitudinal ? '' :
					RCView::tr(array(),
						RCView::td(array('class'=>'td1'), $lang['data_entry_340']).
						RCView::td(array('class'=>'td2'),
							RCView::select(array(
								'id'=>'group_by',
								'name'=>'group_by','class'=>'x-form-text x-form-field float-start'),
								array('event'=>$lang['global_141'],'form'=>$lang['global_89']), $dashboard['group_by']
							) .
							RCView::div(array('class'=>'cc_info float-start', 'style'=>'margin:0 0 0 15px;max-width:400px;'), $lang['data_entry_362'])
						)
					)
				).
				RCView::tr(array(),
					RCView::td(array('class'=>'td1', 'style'=>'padding-top:10px;padding-bottom:5px;'), 
						(!$Proj->longitudinal ? $lang['data_entry_343'] : $lang['data_entry_344']) .
						RCView::div(array('class'=>'cc_info'), $lang['data_entry_363'])
					).
					RCView::td(array('class'=>'td2', 'style'=>'padding-top:10px;padding-bottom:5px;'),
					 	RCView::div(array('class'=>'x-form-text  x-form-field','style'=>'font-weight:normal; width:80%;max-width:500px;','onclick'=>"openExcludeFormsEvents();"),
							RCView::img(array('src'=>'pencil_small2.png')).
							RCView::input(array('id'=>'selected_forms_events', 'name'=>'selected_forms_events', 'style'=>'font-size:12px;',
								'value'=>$Proj->convertSelectedFormsEventsFromBackend($dashboard['selected_forms_events']),'disabled'=>'disabled')
							)
						).
						$Proj->renderSelectedFormsEvents($dashboard['selected_forms_events'])
					)
				).
				RCView::tr(array(),
					RCView::td(array('class'=>'td1'), $lang['data_entry_346'] .
						RCView::div(array('class'=>'cc_info'), $lang['data_entry_364'])).
					RCView::td(array('class'=>'td2'),
					 	RCView::textarea(array(
							'class'=>'x-form-text x-form-field',
							'style'=>'width:80%;max-width:500px;height:40px;font-size:12px;',
							'id'=>'filter_logic',
							'name'=>'filter_logic',
							'onblur'=>"var val = this; 
									setTimeout(function() { 
										logicHideSearchTip(val); 
										this.value=trim(val.value); 
										if(!checkLogicErrors(val.value,1,true)){
											validate_auto_invite_logic($(val));
										} 
									}, 0);",
							'onkeydown'=>"logicSuggestSearchTip(this, event);", 'onfocus'=>'openLogicEditor($(this))'), $dashboard['filter_logic']) .
						RCView::div(array('style'=>'font-size:11px;color:#777;font-weight:normal;margin-top: 2px;'),
							($Proj->longitudinal ? '(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = "1")' : '(e.g., [age] > 30 and [sex] = "1")')
						) .
						logicAdd("filter_logic") .
						RCView::div(array('id'=>'filter_logic_Ok', 'style'=>'font-weight:bold;height:12px;margin-top:2px;', 'class'=>'logicValidatorOkay'), ' ')
					)
				).
				($arms_select ? RCView::tr(array(),		
					RCView::td(array('class'=>'td1', 'style'=>'padding-bottom:5px;'), $lang['data_entry_342'] .
						RCView::div(array('class'=>'cc_info'), $lang['data_entry_365'])).		
					RCView::td(array('class'=>'td2', 'style'=>'padding-bottom:5px;'),		
						$arms_select	
					)		
				) : '').
				RCView::tr(array(),
					RCView::td(array('class'=>'td1'), $lang['data_entry_341'] .
						RCView::div(array('class'=>'cc_info'), $Proj->longitudinal ? $lang['data_entry_367'] : $lang['data_entry_366'])).
					RCView::td(array('class'=>'td2', 'style'=>'padding-top:6px;'),
						($Proj->longitudinal
							? RCView::select(array('name'=>'sort_event_id', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:140px;'), $event_dropdown_options, $dashboard['sort_event_id'])
							: RCView::hidden(array('name'=>'sort_event_id', 'value'=>$Proj->firstEventId))
						) .
						RCView::select(array(
							'name'=>'sort_field_name','class'=>'x-form-text x-form-field', 'style'=>($Proj->longitudinal ? 'max-width:200px;' : '')), 
							Form::getFieldDropdownOptions(true), $dashboard['sort_field_name']
						) .
						RCView::span(array('style'=>'margin:0 6px 0 2px;'), $lang['global_107']) .
						RCView::select(array(
							'name'=>'sort_order', 'class'=>'x-form-text x-form-field', 'style'=>'max-width:150px;'),
							array('ASC'=>$lang['report_builder_22'],'DESC'=>$lang['report_builder_23']), $dashboard['sort_order']
						)
					)
				).
				RCView::tr(array(),
					RCView::td(array('class'=>'td1'), "").
					RCView::td(array('class'=>'td2', 'style'=>'padding-bottom:10px;'),
						RCView::button(array('id'=>'btn_save', 'class'=>'btn btn-primaryrc','onclick'=>'saveDashboard();return false;', 'style'=>'margin-top: 5px;'), $lang['data_entry_347']).
						RCView::a(array('href'=>'javascript:;', 'onclick'=>"$('#dashboard-config').hide('fast');return false;", 'style'=>'text-decoration:underline;margin:5px 0 5px 20px;font-size:14px;'), $lang['global_53']) .
						RCView::button(array('id'=>'btn_delete', 'class'=>'btn btn-defaultrc btn-sm','onclick'=>"deleteDashboardConfirm('".js_escape($lang['data_entry_355'])."','".js_escape($lang['data_entry_348'])."','".js_escape($lang['global_53'])."','".js_escape($lang['data_entry_348'])."');return false;", 'style'=>'margin:5px 0 0 100px;color:#C00000;' . (empty($dashboard['rd_id']) ? 'display:none;' : '')), $lang['data_entry_348'])
					)
				)
			)
		);
		return $html;
	}
}