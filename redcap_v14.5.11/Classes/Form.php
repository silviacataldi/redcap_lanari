<?php

use MultiLanguageManagement\MultiLanguage;
use Vanderbilt\REDCap\Classes\ProjectDesigner;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * FORM Class
 * Contains methods used with regard to forms or general data entry
 */
class Form
{
	/**
	 * BRANCHING LOGIC & CALC FIELDS: CROSS-EVENT FUNCTIONALITY
	 */
	public static function addHiddenFieldsOtherEvents($record, $event_id, $form, $instance)
	{
		global $Proj, $fetched, $repeatingFieldsEventInfo;
		// Get list of unique event names
		$events = $Proj->getUniqueEventNames();
		// Collect the fields used for each event (so we'll know which data to retrieve)
		$eventFields = array();
		// If field is not on this form, then add it as a hidden field at bottom near Save buttons
		$sql = "select * from (
					select concat(if(branching_logic is null,'',branching_logic), ' ', if(element_enum is null,'',element_enum), ' ', if(misc is null,'',misc)) as bl_calc
					from redcap_metadata where project_id = ".PROJECT_ID." and (branching_logic is not null or element_type = 'calc' or (element_type = 'text' and misc like '%@CALC%'))
				) x where (bl_calc like '%-event-name]%' or bl_calc like '%[" . implode("]%' or bl_calc like '%[", $events) . "]%')";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q))
		{
			// Replace any Smart Variables first
			$row['bl_calc'] = Piping::pipeSpecialTags($row['bl_calc'], PROJECT_ID, $record, $event_id, $instance, null, true, null, $form, false, false, false, true, false, false, true);
			// Replace unique event name+field_name in brackets with javascript equivalent
			foreach (array_keys(getBracketedFields($row['bl_calc'], true, true)) as $this_field)
			{
				// Skip if doesn't contain a period (i.e. does not have an event name specified)
				if (strpos($this_field, ".") === false) continue;
				// Obtain event name and ensure it is legitimate
				list ($this_event, $this_field) = explode(".", $this_field, 2);
				if (in_array($this_event, $events))
				{
					// Get event_id of unique this event
					$this_event_id = array_search($this_event, $events);
					// Don't add to array if already in array
                    if (!isset($eventFields[$this_event_id]) || !in_array($this_field, $eventFields[$this_event_id])) {
						$eventFields[$this_event_id][] = $this_field;
					}
				}
			}
		}
		// Initialize HTML string
		$html = "";
		// Loop through each event where fields are used
		foreach ($eventFields as $this_event_id=>$these_fields)
		{
			// Don't create extra form if it's the same event_id (redundant)
			if ($this_event_id == $_GET['event_id']) continue;
			// Seed array with default data
			$these_fields_data = $these_fields_form_complete = array();
			foreach ($these_fields as $this_field) {
				if ($Proj->metadata[$this_field]['element_type'] != "checkbox") {
					$these_fields_data[$this_field][''] = '';
				}
				if ($Proj->isRepeatingEvent($this_event_id) || $Proj->isRepeatingForm($this_event_id, $Proj->metadata[$this_field]['form_name'])) {
					$these_fields_form_complete[] = $Proj->metadata[$this_field]['form_name']."_complete";
				}
			}
			$these_fields_form_complete = array_unique($these_fields_form_complete);
			// First, query each event for its data for this record
			$sql = "select field_name, value, if(instance is null,'',instance) as instance
					from ".\Records::getDataTable(PROJECT_ID)." where project_id = " . PROJECT_ID . " and event_id = $this_event_id and value != ''
					and record = '" . db_escape($fetched) . "' and (field_name in ('" . implode("', '", $these_fields) . "')
					or field_name in ('" . implode("', '", $these_fields_form_complete) . "'))";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// Save data in array
				if ($Proj->metadata[$row['field_name']]['element_type'] != "checkbox") {
					$these_fields_data[$row['field_name']][$row['instance']] = $row['value'];
				} else {
					$these_fields_data[$row['field_name']][$row['instance']][] = $row['value'];
				}
			}
			// If there are any repeating events/forms, then loop through the forms that have repeating instances, 
			// and make sure we have placeholder values for all existing instance for their fields
			foreach ($these_fields_form_complete as $this_form_complete) {
				$this_form = substr($this_form_complete, 0, -9);
                if (!isset($Proj->forms[$this_form]['fields']) || !is_array($Proj->forms[$this_form]['fields'])) continue;
				foreach (array_keys($Proj->forms[$this_form]['fields']) as $this_field) {
					if (!isset($these_fields_data[$this_field]) || !isset($these_fields_data[$this_form_complete]) || !is_array($these_fields_data[$this_form_complete])) continue;
					foreach (array_keys($these_fields_data[$this_form_complete]) as $this_instance) {
						if (isset($these_fields_data[$this_field][$this_instance])) continue;
						if ($Proj->metadata[$this_field]['element_type'] != "checkbox") { // Currently not sure how to do this with checkboxes
							$these_fields_data[$this_field][$this_instance] = '';
						}
					}
				}
			}
			// Get unique event name
			$this_unique_name = $events[$this_event_id];
			
			$isRepeatingEvent = $Proj->isRepeatingEvent($this_event_id);
		
			// Create HTML form
			$html .= "\n<form name=\"form__$this_unique_name\" enctype=\"multipart/form-data\">";
			// Loop through all fields in array
			foreach ($these_fields as $this_field)
			{
				// Determine if field is on a repeating form or event
				$this_form = $Proj->metadata[$this_field]['form_name'];
				$isRepeatingForm = $isRepeatingEvent ? false : $Proj->isRepeatingForm($this_event_id, $this_form);	
				$repeat_instrument = $isRepeatingForm ? $this_form : "";
				// Non-checkbox field
				if ($Proj->metadata[$this_field]['element_type'] != "checkbox")
				{
					foreach ($these_fields_data[$this_field] as $this_instance=>$value) {
						if ($this_instance == '' && ($isRepeatingForm || $isRepeatingEvent)) {
							$this_instance = '1';
						}
						// Remove from array
						unset($repeatingFieldsEventInfo[$this_event_id][$repeat_instrument][$this_instance][$this_field]);
						// If this is really a date[time][_seconds] field that is hidden, then make sure we reformat the date for display on the page
                        $fv = "";
						if ($Proj->metadata[$this_field]['element_validation_type'] !== null && $Proj->metadata[$this_field]['element_type'] == 'text')
						{
                            $fv = "fv=\"".$Proj->metadata[$this_field]['element_validation_type']."\"";
							if (substr($Proj->metadata[$this_field]['element_validation_type'], -4) == '_mdy') {
								$this_date = $value;
								$this_time = "";
							    if (strpos($value, " ") !== false) {
									list ($this_date, $this_time) = explode(" ", $value);
                                }
								$value = trim(DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);
							} elseif (substr($Proj->metadata[$this_field]['element_validation_type'], -4) == '_dmy') {
								$this_date = $value;
								$this_time = "";
								if (strpos($value, " ") !== false) {
									list ($this_date, $this_time) = explode(" ", $value);
								}
								$value = trim(DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
							}
						}
						$this_instance_name = "";
						if (is_numeric($this_instance) && ($isRepeatingForm || $isRepeatingEvent)) {
							$this_instance_name = "____I{$this_instance}";
						}
						$html .= "\n  <input type=\"hidden\" name=\"{$this_field}{$this_instance_name}\" value=\"".htmlspecialchars($value, ENT_QUOTES)."\" $fv>";
					}
				}
				// Checkbox field
				else
				{
					$field_choices = parseEnum($Proj->metadata[$this_field]['element_enum']);
					$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($this_event_id, $Proj->metadata[$this_field]['form_name']);
					if (!isset($these_fields_data[$this_field])) {
						// No choices have been selected, so nothing in data table						
						foreach ($field_choices as $this_code=>$this_label)
						{
							// Set with no value
							$html .= "\n  <input type=\"hidden\" value=\"\" name=\"__chk__{$this_field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."\">";
						}
					} else {
						// Loop through data
						foreach ($these_fields_data[$this_field] as $this_instance=>$value) {
							// Remove from array
							unset($repeatingFieldsEventInfo[$this_event_id][$repeat_instrument][$this_instance][$this_field]);
							$this_instance_name = "";
							if (is_numeric($this_instance) && ($this_instance > 1 || $isRepeatingFormOrEvent)) {
								$this_instance_name = "____I{$this_instance}";
							}
							foreach ($field_choices as $this_code=>$this_label)
							{
								if (in_array($this_code, $these_fields_data[$this_field][$this_instance])) {
									$default_value = $this_code;
								} else {
									$default_value = ''; //Default value is 'null' if no present value exists
								}
								$html .= "\n  <input type=\"hidden\" value=\"".htmlspecialchars($default_value, ENT_QUOTES)."\" name=\"__chk__{$this_field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."{$this_instance_name}\">";
							}
						}
					}
				}
			}
			
			// Loop through fields that are being referenced but have no data
			if (isset($repeatingFieldsEventInfo[$this_event_id]) && !empty($repeatingFieldsEventInfo[$this_event_id]))
			{
				foreach ($repeatingFieldsEventInfo[$this_event_id] as $attr) {
					foreach ($attr as $this_instance=>$bttr) {
						foreach (array_keys($bttr) as $this_field) {
							$isRepeatingForm = $isRepeatingEvent ? false : $Proj->isRepeatingForm($this_event_id, $Proj->metadata[$this_field]['form_name']);			
							$this_instance_name = "";
							if (is_numeric($this_instance) && ($isRepeatingForm || $isRepeatingEvent)) {
								$this_instance_name = "____I{$this_instance}";
							}
							if (!$Proj->isCheckbox($this_field)) {
								$html .= "\n  <input type=\"hidden\" name=\"{$this_field}{$this_instance_name}\" value=\"\">";
							} else {
								$field_choices = parseEnum($Proj->metadata[$this_field]['element_enum']);
								foreach ($field_choices as $this_code=>$this_label)
								{
									// Set with no value
									$html .= "\n  <input type=\"hidden\" value=\"\" name=\"__chk__{$this_field}_RC_".DataEntry::replaceDotInCheckboxCoding($this_code)."{$this_instance_name}\">";
								}
							}
						}
					}
				}
			}
			
			// End form
			$html .= "\n</form>\n";
		}
		if ($html != "") $html = "\n\n<!-- Hidden forms containing data from other events -->$html\n";
		// Return the other events' fields in an HTML form for each event
		return $html;
	}

	// Delete a form from all database tables EXCEPT metadata tables and user_rights table and surveys table
	public static function deleteFormFromTables($form)
	{
		if (!defined("PROJECT_ID") || $form == '') return;
		$sql = "delete from redcap_events_forms where form_name = '".db_escape($form)."'
				and event_id in (" . pre_query("select m.event_id from redcap_events_arms a, redcap_events_metadata m where a.arm_id = m.arm_id and a.project_id = " . PROJECT_ID . "") . ")";
		db_query($sql);
		$sql = "delete from redcap_library_map where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);
		$sql = "delete from redcap_locking_labels where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);
		$sql = "delete from redcap_locking_data where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);
		$sql = "delete from redcap_esignatures where project_id = " . PROJECT_ID . " and form_name = '".db_escape($form)."'";
		db_query($sql);		
		$sql = "delete from redcap_events_repeat where event_id in (" . prep_implode(array_keys(Event::getEventsByProject(PROJECT_ID))) . ") and form_name = '".db_escape($form)."'";
		db_query($sql);
        $sql = "DELETE FROM redcap_mycap_tasks_schedules WHERE task_id IN (" . pre_query("SELECT task_id FROM redcap_mycap_tasks WHERE project_id = " . PROJECT_ID . " AND form_name = '".db_escape($form)."'") . ")";
        db_query($sql);
        $sql = "DELETE FROM redcap_mycap_tasks WHERE project_id = " . PROJECT_ID . " AND form_name = '".db_escape($form)."'";
        db_query($sql);
	}
	
	//Function for rendering the data entry form list on the right-hand menu
	public static function renderFormMenuList($fetched, $hidden_edit)
	{
		global $surveys_enabled, $Proj, $user_rights, $longitudinal, $userid, $lang, $table_pk_label, $double_data_entry;

		// Collect string of html
		$html = "";
		//Get project_id for this project (may be parent/child project)
		$project_id = PROJECT_ID;
		// Determine the current event_id (may change if using Parent/Child linking)
		$event_id   = (isset($_GET['event_id']) && is_numeric($_GET['event_id'])) ? $_GET['event_id'] : getSingleEvent($project_id);
		if (!isset($_GET['page'])) $_GET['page'] = "";
		
		$entry_num = ($double_data_entry && $user_rights['double_data'] != '0') ? "--".$user_rights['double_data'] : "";
		
		$isCalPopup = (PAGE == "Calendar/calendar_popup.php");

        // Form Display Logic
		if (PAGE == "DataEntry/index.php" && isset($_GET['page']) && isset($fetched)) {
			$formsAccess = FormDisplayLogic::getAccess('left_form_menu', $fetched.$entry_num, $event_id, $_GET['page'], $_GET['instance']);
		} elseif ($isCalPopup && isset($fetched)) {
			$formsAccess = FormDisplayLogic::getAccess('left_form_menu', $fetched.$entry_num, $event_id, $_GET['page'], $_GET['instance']);
        }

		$auto_num_flag = "";
		if (isset($_GET['auto'])) {
			// If creating a new record via auto-numbering, make sure that the "auto" parameter gets perpetuated in the query string, just in case
			$auto_num_flag = "&auto=1";
		}

		//For lock/unlock records and e-signatures, show locks by any forms that are locked (if a record is pulled up on data entry page)
		$locked_forms = array();
		if ((PAGE == "DataEntry/index.php" || $isCalPopup) && isset($fetched))
		{
			$entry_num = isset($entry_num) ? $entry_num : "";
			// Lock records
			$sql = "select form_name, timestamp from redcap_locking_data where project_id = $project_id and event_id = $event_id
					and record = '" . db_escape($fetched.$entry_num). "' and instance = '".db_escape($_GET['instance'])."'";
			$q = db_query($sql);
			while ($row = db_fetch_array($q))
			{
				$locked_forms[$row['form_name']] = " <img id='formlock-{$row['form_name']}' src='".APP_PATH_IMAGES."lock_small.png' title='".js_escape($lang['bottom_59'])." " . DateTimeRC::format_ts_from_ymd($row['timestamp']) . "'>";
			}
			// E-signatures
            if ($GLOBALS['esignature_enabled_global']) {
                $sql = "select form_name, timestamp from redcap_esignatures where project_id = $project_id and event_id = $event_id
                        and record = '" . db_escape($fetched . $entry_num) . "' and instance = '" . db_escape($_GET['instance']) . "'";
                $q = db_query($sql);
                while ($row = db_fetch_array($q)) {
                    $this_esignts = " <img id='formesign-{$row['form_name']}' src='" . APP_PATH_IMAGES . "tick_shield_small.png' title='" . js_escape($lang['data_entry_224'] . " " . DateTimeRC::format_ts_from_ymd($row['timestamp'])) . "'>";
                    if (isset($locked_forms[$row['form_name']])) {
                        $locked_forms[$row['form_name']] .= $this_esignts;
                    } else {
                        $locked_forms[$row['form_name']] = $this_esignts;
                    }
                }
            }
		}

		//Build array with form_name, form_menu_description, and the form status value for this record
		$form_names = $form_info = array();
		foreach ($Proj->forms as $form=>$attr) {
			$form_names[] = $form;
			$form_info[$form]['form_menu_description'] = $attr['menu'];
			$form_info[$form]['form_status'] = array();
		}

		$fetchedlink = $instance_url = '';

		// Data entry page only
		$surveyResponses = array();
		if ((PAGE == "DataEntry/index.php" || $isCalPopup) && isset($fetched))
		{
			// REPEATING FORMS/EVENTS: Check for "instance" number if the form is set to repeat
			$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($_GET['event_id'], $_GET['page']);
			$isRepeatingEvent = ($isRepeatingFormOrEvent && $Proj->isRepeatingEvent($_GET['event_id']));
			
			// Adapt for Double Data Entry module
			if ($entry_num != "") {
				//This is #1 or #2 Double Data Entry person
				$fetched .= $entry_num;
			}

			// Aggregate all the form statuses
			$formStatusValues = Records::getFormStatus($project_id, array($fetched), getArm());
			if (isset($formStatusValues[$fetched][$event_id])) {
				foreach ($formStatusValues[$fetched][$event_id] as $form => $instances) {
					if (empty($instances)) continue;
					$form_info[$form]['form_status'] = $instances;
				}
			}
			unset($formStatusValues);
			// Adapt for Double Data Entry module
			if ($entry_num != "") {
				//This is #1 or #2 Double Data Entry person
				$fetchedlink = RCView::escape(substr($fetched, 0, -3));
			} else {
				//Normal
				$fetchedlink = RCView::escape($fetched);
			}

			// Determine if record also exists as a survey response for some instruments
			if ($surveys_enabled)
			{
				$surveyResponses = Survey::getResponseStatus($project_id, $fetched, $event_id);
			}
		}
		
		//Loop through each form and display text and colored button
		foreach ($form_info as $form_name=>$info)
		{
			// Skip form when it's disabled by Form Display Logic and the option to hide disabled forms is on
			if ($Proj->project["hide_disabled_forms"] == 1 && isset($formsAccess[$fetched][$event_id][$form_name]) && $formsAccess[$fetched][$event_id][$form_name] != 1) {
				continue;
			}

			$menu_text = filter_tags($info['form_menu_description']);
			$menu_form_complete_field = $form_name . "_complete";
			$menu_page = APP_PATH_WEBROOT."DataEntry/index.php?pid=$project_id&page=$form_name&id=$fetchedlink&event_id=$event_id{$auto_num_flag}";

			// Default
			$hold_color_link = "";
			$form_link_classes = "";
			$iconTitle = "{$lang['bottom_48']} $table_pk_label $fetched";
			$hideForm = false;

			//Produce HTML for colored button if an existing record and on data entry page
			if ((PAGE == "DataEntry/index.php" || $isCalPopup) && isset($fetched))
			{
				// Determine if this form is set to repeat
				$isRepeatingForm = $Proj->isRepeatingForm($_GET['event_id'], $form_name);
				$numInstances = count($info['form_status']);
				$form_has_multiple_instances = (isset($info['form_status']) && is_array($info['form_status']) && ($numInstances > 1 || ($numInstances > 0 && max(array_keys($info['form_status'])) > 1)));
				$maxInstance = ($form_has_multiple_instances) ? max(array_keys($info['form_status'])) : 1;
				
				// Determine the event instance
				$instance = $_GET['instance'];
				$instance_url = "";
				if (($isRepeatingForm && $form_name == $_GET['page']) || ($longitudinal && $isRepeatingEvent)) {
					// If the current form is a repeating form OR if event is an entire repeating event, then add current instance to form link
					$instance_url = "&instance=$instance";
				} elseif (!$isRepeatingEvent && $form_name != $_GET['page']) {
					// If this is not a repeating event, then always return it back to instance 1 (since form instances are independent)
					$instance = 1;
					// If this is a repeating instrument, then point to the minimum instance number, which may or may not be the first instance
                    if ($isRepeatingForm && $form_name != $_GET['page'] && $form_has_multiple_instances) {
						$instance_url = "&instance=" . min(array_keys($info['form_status']));
                    }
				}
				
				// If it's a survey response, display different icons
				$form_status_instance = '';
				if (isset($surveyResponses[$fetched][$event_id][$form_name][$instance])) {
					//Determine color of button based on response status
					switch ($surveyResponses[$fetched][$event_id][$form_name][$instance]) {
						case '2':
							$holder_color = APP_PATH_IMAGES . ($form_has_multiple_instances ? 'circle_green_tick_stack.png' : 'circle_green_tick.png');
							$iconTitle = $lang['global_94'];
							break;
						default:
							$holder_color = APP_PATH_IMAGES . ($form_has_multiple_instances ? 'circle_orange_tick_stack.png' : 'circle_orange_tick.png');
							$iconTitle = $lang['global_95'];
					}
				} else {
					$form_status_instance = '';
					$all0s = $all1s = $all2s = false;
					if (!$form_has_multiple_instances && isset($info['form_status']) && isset($info['form_status'][$instance])) {
						$form_status_instance = $info['form_status'][$instance];
					} elseif ($form_has_multiple_instances && isset($info['form_status'])) {
						$status_concat = trim(implode('', $info['form_status']));
						$all0s = (isset($info['form_status'][$instance]) && str_replace('0', '', $status_concat) == '');
						$all1s = (isset($info['form_status'][$instance]) && str_replace('1', '', $status_concat) == '');
						$all2s = (isset($info['form_status'][$instance]) && str_replace('2', '', $status_concat) == '');
						if (($isRepeatingEvent || ($form_name == $_GET['page'] && !$isRepeatingEvent)) && isset($info['form_status'][$instance])) {
							$form_status_instance = $info['form_status'][$instance];
							$all0s = $all1s = $all2s = false;
							switch ($form_status_instance) {
								case '1':
									$all1s = true;
									break;
								case '2':
									$all2s = true;
									break;
								default:
									$all0s = true;
							}					
						} else {
							if ($all0s) $form_status_instance = '0';
							elseif ($all1s) $form_status_instance = '1';
							elseif ($all2s) $form_status_instance = '2';
							else $form_status_instance = '';
						}
					}

					//Determine color of button based on form status value
					switch ($form_status_instance) {
						case '0':
							$holder_color = APP_PATH_IMAGES . ($form_has_multiple_instances ? ($all0s ? 'circle_red_stack.png' : 'circle_blue_stack.png') : 'circle_red.png');
							$iconTitle = $lang['global_92'];
							break;
						case '1':
							$holder_color = APP_PATH_IMAGES . ($form_has_multiple_instances ? ($all1s ? 'circle_yellow_stack.png' : 'circle_blue_stack.png') : 'circle_yellow.png');
							$iconTitle = $lang['global_93'];
							break;
						case '2':
							$holder_color = APP_PATH_IMAGES . ($form_has_multiple_instances ? ($all2s ? 'circle_green_stack.png' : 'circle_blue_stack.png') : 'circle_green.png');
							$iconTitle = $lang['survey_28'];
							break;
						default:
							$holder_color = APP_PATH_IMAGES . ($form_has_multiple_instances ? ((!isset($info['form_status'][$instance]) && ($isRepeatingEvent || ($form_name == $_GET['page'] && !$isRepeatingEvent))) ? 'circle_gray_stack.png' : 'circle_blue_stack.png') : 'circle_gray.png');
							$iconTitle = $lang['global_92'];
					}
				}
				
				// Check if this form in the menu is the current form
				$statusIconStyle = ($form_has_multiple_instances) ? 'width:22px;margin-left:-3px;' : 'width:16px;';
				if ($form_name == $_GET['page']) {
					$form_link_classes = "round form_menu_selected";
				}
				// Make other forms faded out if on an instance of a repeating form
				elseif ($form_name != $_GET['page'] && !$isRepeatingEvent && $_GET['instance'] > 1) {
					$form_link_classes = "formMenuListGrayed";
					// Set status icon as invisible
					$statusIconStyle = 'visibility:hidden;width:16px;';
				}
				
				// If on a repeating form that has multiple instances saved already
				if ($isRepeatingForm && $form_has_multiple_instances) {
					// Add instance number to form label on menu
					$menu_text .= "<span class='repeat_event_count_menu'>(";				
					if ($form_name == $_GET['page']) {
						$menu_text .= "$instance<span>/</span>";
					}
					if ($maxInstance != $numInstances) {
						if ($form_name != $_GET['page']) {
							$menu_text .= ($instance > $maxInstance ? $instance : "{$lang['data_entry_496']} $maxInstance") . "{$lang['comma']} {$lang['data_entry_495']} $numInstances)</span>";
						} else {
							$menu_text .= ($instance > $maxInstance ? $instance : $maxInstance) . "{$lang['comma']} {$lang['data_entry_495']} $numInstances)</span>";
                        }
                    } else {
						$menu_text .= ($instance > $maxInstance ? $instance : $maxInstance) . ")</span>";
                    }
				}

				// HTML for colored button
				if ($hidden_edit) {
					$href = $isCalPopup ? "javascript:;" : "{$menu_page}{$instance_url}";
					$onclick = $isCalPopup ? "onclick=\"window.opener.location.href='{$menu_page}{$instance_url}';self.close();\"" : "";
                    // For Display Logic
                    $link_style = '';
                    if (isset($formsAccess[$fetched][$event_id][$form_name]) && $formsAccess[$fetched][$event_id][$form_name] != 1) {
                        $link_style = 'style="pointer-events: none; opacity: 0.2;"';
                    }
					$hold_color_link = "<a title='$iconTitle' href='$href' $onclick $link_style><img src='$holder_color' style='height:16px;$statusIconStyle'></a>";
				}
			}

			// Set lock icon html, if record-event-form is locked
			$show_lock = isset($locked_forms[$form_name]) ? $locked_forms[$form_name] : "";
			
			// Display normal form links ONLY if user has rights to the form
			if (isset($Proj->forms[$form_name]) && isset($user_rights['forms'][$form_name]) && $user_rights['forms'][$form_name] != "0"
				&& (!$longitudinal || ((PAGE == "DataEntry/index.php" || $isCalPopup) && isset($event_id) && isset($Proj->eventsForms[$event_id]) && in_array($form_name, $Proj->eventsForms[$event_id])))
			) {
				// If this is not the Data Entry Page, then keep forms hidden
				$showFormsList = (!$longitudinal && UIState::getUIStateValue(PROJECT_ID, 'sidebar', 'show-instruments-toggle') == '1');
				$hideForm = ((PAGE == "DataEntry/index.php" || $isCalPopup) || $showFormsList) ? "" : "hidden";
				// For Display Logic
                $link_style = '';
                if (isset($fetched) && isset($event_id) && isset($formsAccess[$fetched][$event_id][$form_name]) && $formsAccess[$fetched][$event_id][$form_name] != 1) {
                    $link_style = 'style="pointer-events: none; opacity: 0.5;"';
                }
				$href = $isCalPopup ? "javascript:;" : "{$menu_page}{$instance_url}";
				$onclick = $isCalPopup ? "onclick=\"window.opener.location.href='{$menu_page}{$instance_url}';self.close();\"" : "";				
				// Add form to menu
				$html .= "<div class='formMenuList $hideForm'>$hold_color_link &nbsp;";
				$html .= "<a id='form[$form_name]' class='$form_link_classes' href='$href' $onclick $link_style><span data-mlm='form-name' data-mlm-name='$form_name'>$menu_text</span></a>{$show_lock}";
				// Add + button (if not already on a yet-to-exist instance)
				if (isset($fetched) && isset($isRepeatingForm) && $isRepeatingForm && ($form_status_instance != '' || ($form_has_multiple_instances && $maxInstance != $numInstances))) {
					$instance_url = "&instance=".($maxInstance+1);
					$href = $isCalPopup ? "javascript:;" : "{$menu_page}{$instance_url}";
					$onclick = $isCalPopup ? "onclick=\"window.opener.location.href='{$menu_page}{$instance_url}';self.close();\"" : "";	
					$html .= "<a href='$href' $onclick class='btn btn-defaultrc btnAddRptEv' $link_style title='".js_escape($lang['grid_43'])."'>+</a>";
				}
				$html .= "</div>";
			}
		}

		// Return form count, HTML, and locked form count
		return array(count($form_names), $html, count($locked_forms));
	}
	// ACTION TAGS: Return array of all action tags with tag name as array key and description as array value.
	// If the $onlineDesigner param is passed, it will return only those that are utilized on the Online Designer.
	public static function getActionTags($onlineDesigner=false)
	{
		global $lang, $mobile_app_enabled, $mycap_enabled, $mycap_enabled_global;
		// Set all elements of array
		$action_tags = array();
		if (!$onlineDesigner) {
			$action_tags['@DEFAULT'] = $lang['design_659'];
            $action_tags['@DOWNLOAD-COUNT'] = $lang['design_1071'];
			$action_tags['@PREFILL'] = $lang['design_948'];
			$action_tags['@SETVALUE'] = $lang['design_948'];
			$action_tags['@HIDDEN'] = $lang['design_609'];
			$action_tags['@HIDDEN-FORM'] = $lang['design_610'];
			$action_tags['@HIDDEN-SURVEY'] = $lang['design_611'];
			$action_tags['@HIDDEN-PDF'] = $lang['design_790'];
			$action_tags['@READONLY'] = $lang['design_612'];
			$action_tags['@READONLY-FORM'] = $lang['design_613'];
			$action_tags['@READONLY-SURVEY'] = $lang['design_614'];
			$action_tags['@USERNAME'] = RCView::tt_i("design_1007", [System::SURVEY_RESPONDENT_USERID]);
			$action_tags['@LATITUDE'] = $lang['design_629'];
			$action_tags['@LONGITUDE'] = $lang['design_630'];
			$action_tags['@NOW'] = "(e.g., 2017-08-01 12:34:56) " . $lang['design_763'];
            $action_tags['@NOW-SERVER'] = "(e.g., 2017-08-01 12:34:56) " . $lang['design_785'];
            $action_tags['@NOW-UTC'] = "(e.g., 2017-08-01 12:34:56) " . $lang['design_786'];
			$action_tags['@TODAY'] = "(e.g., 2017-08-01) " . $lang['design_762'];
            $action_tags['@TODAY-SERVER'] = "(e.g., 2017-08-01) " . $lang['design_787'];
            $action_tags['@TODAY-UTC'] = "(e.g., 2017-08-01) " . $lang['design_788'];
			$action_tags['@WORDLIMIT'] = $lang['data_entry_405'];
			$action_tags['@CHARLIMIT'] = $lang['data_entry_406'];
			$action_tags['@RANDOMORDER'] = $lang['data_entry_407'];
			$action_tags['@HIDECHOICE'] = $lang['data_entry_612'];
			$action_tags['@SHOWCHOICE'] = $lang['data_entry_611'];
			$action_tags['@NONEOFTHEABOVE'] = $lang['data_entry_414'];
			$action_tags['@MAXCHOICE'] = $lang['data_entry_419'];
			$action_tags['@MAXCHOICE-SURVEY-COMPLETE'] = $lang['data_entry_499'];
			$action_tags['@MAXCHECKED'] = $lang['data_entry_420'];
			$action_tags['@NOMISSING'] = $lang['data_entry_472'];
			$action_tags['@INLINE'] = $lang['data_entry_497'];
			$action_tags['@INLINE-PREVIEW'] = $lang['data_entry_604'];
			$action_tags['@IF'] = $lang['data_entry_500'];
			$action_tags['@FORCE-MINMAX'] = $lang['data_entry_571'];
			// Always display multilanguage action tags so that users can learn about them even before enabling MLM
            $action_tags['@LANGUAGE-CURRENT-FORM'] = $lang['multilang_03'];
            $action_tags['@LANGUAGE-CURRENT-SURVEY'] = $lang['multilang_200'];
            $action_tags['@LANGUAGE-SET'] = $lang['multilang_144'];
            $action_tags['@LANGUAGE-SET-FORM'] = $lang['multilang_708'];
            $action_tags['@LANGUAGE-SET-SURVEY'] = $lang['multilang_709'];
            $action_tags['@LANGUAGE-FORCE'] = $lang['multilang_125'];
            $action_tags['@LANGUAGE-FORCE-FORM'] = $lang['multilang_126'];
            $action_tags['@LANGUAGE-FORCE-SURVEY'] = $lang['multilang_127'];
			// The following tags are only for when using the Mobile App
			if ($mobile_app_enabled) {
				$action_tags['@HIDDEN-APP'] = $lang['design_625'];
				$action_tags['@APPUSERNAME-APP'] = $lang['design_661'];
				$action_tags['@READONLY-APP'] = $lang['design_626'];
				$action_tags['@BARCODE-APP'] = $lang['design_633'];
				$action_tags['@SYNC-APP'] = $lang['design_702'];
			}
            // The following tags are only for when using the MyCap
            $is_mycap_enabled = $mycap_enabled; // Project-level settings
            if (is_null($mycap_enabled)) {
                $is_mycap_enabled = $mycap_enabled_global; // System-level settings
            }
            if ($is_mycap_enabled) {
                $action_tags['@MC-PARTICIPANT-CODE'] = $lang['data_entry_608'];
                $action_tags['@MC-PARTICIPANT-JOINDATE'] = $lang['data_entry_607'];
                $action_tags['@MC-PARTICIPANT-JOINDATE-UTC'] = $lang['data_entry_615'];
                $action_tags['@MC-FIELD-FILE-IMAGECAPTURE'] = $lang['data_entry_587'];
                $action_tags['@MC-FIELD-FILE-VIDEOCAPTURE'] = $lang['data_entry_588'];
                $action_tags['@MC-FIELD-HIDDEN'] = $lang['data_entry_589'];
                $action_tags['@MC-FIELD-SLIDER-BASIC'] = $lang['data_entry_586'];
                $action_tags['@MC-FIELD-SLIDER-CONTINUOUS'] = $lang['data_entry_598'];
                $action_tags['@MC-FIELD-TEXT-BARCODE'] = $lang['data_entry_590'];
                $action_tags['@MC-TASK-UUID'] = $lang['data_entry_591'];
                $action_tags['@MC-TASK-STARTDATE'] = $lang['data_entry_592'];
                $action_tags['@MC-TASK-ENDDATE'] = $lang['data_entry_593'];
                $action_tags['@MC-TASK-SCHEDULEDATE'] = $lang['data_entry_594'];
                $action_tags['@MC-TASK-STATUS'] = $lang['data_entry_595'];
                $action_tags['@MC-TASK-SUPPLEMENTALDATA'] = $lang['data_entry_596'];
                $action_tags['@MC-TASK-SERIALIZEDRESULTS'] = $lang['data_entry_597'];
                $action_tags['@MC-PARTICIPANT-TIMEZONE'] = $lang['data_entry_616'];
            }
		}
		// The following tags will additionally be implemented on the Online Designer as a preview
		$action_tags['@PLACEHOLDER'] = $lang['design_703'];
		$action_tags['@PASSWORDMASK'] = $lang['design_624'];
		$action_tags['@HIDEBUTTON'] = $lang['design_662'];
		$action_tags['@CALCDATE'] = $lang['design_836'].RCView::div(['class'=>'mt-2'], $lang['design_1000']);
		$action_tags['@CALCTEXT'] = $lang['design_837'].RCView::div(['class'=>'mt-2'], $lang['design_1083']).RCView::div(['class'=>'mt-2'], $lang['design_1000']);
		$action_tags['@RICHTEXT'] = $lang['design_1008'];
		$action_tags['@CONSENT-VERSION'] = $lang['econsent_64'];
		// Order the tags alphabetically by name
		ksort($action_tags);
		// Return array
		return $action_tags;
	}


	// ACTION TAGS: Determine if field has specific action tag
	public static function hasActionTag($action_tag, $misc)
	{
		if ($action_tag === null) return false;
        if ($misc === null) return false;
		// Explode all action tags into array
		$misc_array = explode(" ", $misc);
		return in_array($action_tag, $misc_array);
	}
	

	// ACTION TAGS: Determine if field has a @HIDDEN or @HIDDEN-SURVEY action tag
	public static function hasHiddenOrHiddenSurveyActionTag($action_tags, $project_id=null, $record=null, $event_id=null, $instrument=null, $instance=null)
	{
        if ($action_tags === null) return false;
		if (isinteger($project_id)) {
			$action_tags = Form::replaceIfActionTag($action_tags, $project_id, $record, $event_id, $instrument, $instance);
		}
		// Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @HIDDEN or @HIDDEN-SURVEY?
		return (in_array('@HIDDEN', $action_tags_array) || in_array('@HIDDEN-SURVEY', $action_tags_array));
	}


	// ACTION TAGS: Determine if field has a @HIDDEN-PDF action tag
	public static function hasHiddenPdfActionTag($action_tags, $project_id=null, $record=null, $event_id=null, $instrument=null, $instance=null)
	{
        if ($action_tags === null) return false;
        if (isinteger($project_id)) {
			$action_tags = Form::replaceIfActionTag($action_tags, $project_id, $record, $event_id, $instrument, $instance);
        }
		// Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @HIDDEN or @HIDDEN-SURVEY?
		return in_array('@HIDDEN-PDF', $action_tags_array);
	}
	

	// ACTION TAGS: Determine if field has a @HIDDEN or @HIDDEN-FORM action tag
	public static function hasHiddenOrHiddenFormActionTag($action_tags, $project_id=null, $record=null, $event_id=null, $instrument=null, $instance=null)
	{
        if ($action_tags === null) return false;
		if (isinteger($project_id)) {
			$action_tags = Form::replaceIfActionTag($action_tags, $project_id, $record, $event_id, $instrument, $instance);
		}
	    // Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @HIDDEN or @HIDDEN-SURVEY?
		return (in_array('@HIDDEN', $action_tags_array) || in_array('@HIDDEN-FORM', $action_tags_array));
	}


	// ACTION TAGS: Parse the Field Annotion attribute and return array of values for @MAXCHOICE action tag
	public static function parseMaxChoiceActionTag($field_annot="", $tag="@MAXCHOICE")
	{
		$maxChoices = array();
		if ($field_annot != "")
		{
			// Obtain the MAXCHOICE text for this field
			$maxChoiceText = Form::getValueInParenthesesActionTag($field_annot, $tag);
			if ($maxChoiceText != "") {
				foreach (explode(",", $maxChoiceText) as $thisVal) {
					list ($thisChoice, $thisAmount) = explode("=", $thisVal, 2);
					$thisChoice = trim($thisChoice)."";
					$thisAmount = trim($thisAmount)."";
					if ($thisChoice == "" || $thisAmount == "" || !is_numeric($thisAmount) || $thisAmount < 0) continue;
					$maxChoices[$thisChoice] = $thisAmount;
				}
			}
		}
		return $maxChoices;
	}
	

	// ACTION TAGS: For a field with a @MAXCHOICE action tag, return array of ONLY the choices that have already reached the max.
	public static function getMaxChoiceReached($field="", $currentEventId="", $tag="@MAXCHOICE")
	{
		global $Proj;
		// Parse the choice and their values
		$maxChoices = self::parseMaxChoiceActionTag($Proj->metadata[$field]['misc'], $tag);
		if (empty($maxChoices)) return array();
		// Get survey ID for this field's instrument (if exists)
        $form = $Proj->metadata[$field]['form_name'];
        $survey_id = isset($Proj->forms[$Proj->metadata[$field]['form_name']]['survey_id']) ? $Proj->forms[$Proj->metadata[$field]['form_name']]['survey_id'] : null;
		// Get the choices that we have reached the maximum
		$maxChoicesReached = array();
		if ($tag == "@MAXCHOICE") {
		    // @MAXCHOICE
			$sql = "select value, count(*) as saved from (
                        select distinct record, event_id, field_name, instance, value from ".\Records::getDataTable(PROJECT_ID)." 
                        where project_id = ".PROJECT_ID." and event_id = '".db_escape($currentEventId)."' 
                        and field_name = '".db_escape($field)."' and value in (".prep_implode(array_keys($maxChoices)).")
                    ) x
                    group by value";
        } else {
		    // If this instrument is not enabled as a survey, then this action tag doesn't even work, so return empty array
            if ($survey_id == null) return array();
			// @MAXCHOICE-SURVEY-COMPLETE    r.completion_time, p.participant_email
			$sql = "select value, count(*) as saved from (
                        select distinct d.record, d.event_id, d.field_name, d.instance, d.value
                        from ".\Records::getDataTable(PROJECT_ID)." d, redcap_surveys_participants p, redcap_surveys_response r
                        where d.project_id = ".PROJECT_ID." and d.event_id = '".db_escape($currentEventId)."' and d.field_name = '".db_escape($field)."'
                        and d.value in (".prep_implode(array_keys($maxChoices)).") and p.event_id = d.event_id and p.survey_id = $survey_id
                        and p.participant_id = r.participant_id and r.record = d.record and r.instance = ifnull(d.instance, 1)
                        and r.completion_time is not null
                    ) x
                    group by value";
        }
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			if ($row['saved'] >= $maxChoices[$row['value']]) {
				$maxChoicesReached[] = $row['value'];
			}
		}
		// Add any "0" choices, which will not have data (just in case)
		foreach ($maxChoices as $thisCode=>$thisMax) {
			if ($thisMax."" === "0") {
				$maxChoicesReached[] = $thisCode;
			}
		}
		// Return array
		return $maxChoicesReached;
	}
	

	// ACTION TAGS: For a field with a @MAXCHOICE action tag, return array of ONLY the choices that have already reached the max.
	public static function hasReachedMaxChoiceForChoice($field="", $currentEventId="", $choiceValue="", $tag='@MAXCHOICE')
	{
		if ($choiceValue == "") return array();
		$choicesReached = self::getMaxChoiceReached($field, $currentEventId, $tag);
		return in_array($choiceValue, $choicesReached);
	}
	

	// ACTION TAGS: Find fields in POST array using @MAXCHOICE and @MAXCHOICE-SURVEY-COMPLETE action tags, check if they reached their max, and return any
	public static function hasReachedMaxChoiceInPostFields($post=array(), $fetched="", $currentEventId="")
	{
		global $Proj;
		// Get array of all fields utilizing maxchoice
		$maxChoiceFields = self::getMaxChoiceFields(array_keys($Proj->forms[$_GET['page']]['fields']), "@MAXCHOICE");
		$maxChoiceFields = array_merge($maxChoiceFields, self::getMaxChoiceFields(array_keys($Proj->forms[$_GET['page']]['fields']), "@MAXCHOICE-SURVEY-COMPLETE"));
		if (empty($maxChoiceFields)) return array();		
		$_GET['maxChoiceFieldsReached'] = array();
		// Build sql for data retrieval for checking if new data or if overwriting old data
		$current_data = array();
		$sql = "select field_name, value from ".\Records::getDataTable(PROJECT_ID)." where record = '" . db_escape($fetched) . "'
				and event_id = $currentEventId and project_id = " . PROJECT_ID .
				($Proj->hasRepeatingFormsEvents() ? " and instance ".($_GET['instance'] == '1' ? "is NULL" : "= ".$_GET['instance']) : "") .  
				" and field_name in (".prep_implode($maxChoiceFields).")";
		$q = db_query($sql);
		while ($row = db_fetch_array($q))
		{
			//Checkbox: Add data as array
			if ($Proj->isCheckbox($row['field_name'])) {
				$current_data[$row['field_name']][$row['value']] = $row['value'];
			//Non-checkbox fields: Add data as string
			} else {
				$current_data[$row['field_name']] = $row['value'];
			}
		}
		// Loop through POST fields
		foreach ($post as $field_name=>$value)
		{
			$post_key = $field_name;
			// Skip if blank value
			if ($value == '') continue;
			$reached = $is_checkbox = false;
			$chkval = '';
			// Reformat the fieldnames of any checkboxes
			if (substr($field_name, 0, 7) == '__chk__') {
				// Parse out the field name and the checkbox coded value
				list ($field_name, $chkval) = explode('_RC_', substr($field_name, 7), 2);
				$chkval = DataEntry::replaceDotInCheckboxCodingReverse($chkval);
				$is_checkbox = true;
			}
			// Skip if not maxchoice
			if (!in_array($field_name, $maxChoiceFields)) continue;
			// Because all GET/POST elements get HTML-escaped, we need to HTML-unescape them here
			$value = html_entity_decode($value, ENT_QUOTES);
			if (
				## OPTION 1: If data exists for this field (and it's not a checkbox), update the value
				(isset($current_data[$field_name]) && !$is_checkbox && $value !== $current_data[$field_name]) ||				
				## OR OPTION 3: If there is no data for this field (checkbox or non-checkbox)
				(((isset($chkval) && !isset($current_data[$field_name][$chkval]) && $is_checkbox) || (!isset($current_data[$field_name]) && !$is_checkbox)) 
					&& $value != '' && strpos($field_name, '___') === false)
			) {
			    // Does field have MAXCHOICE-SURVEY-COMPLETE or MAXCHOICE?
                $actionTag = strpos($Proj->metadata[$field_name]['misc'], '@MAXCHOICE-SURVEY-COMPLETE') !== false ? '@MAXCHOICE-SURVEY-COMPLETE' : '@MAXCHOICE';
				// If this choice for this field has been reached, then add to array to return
				$reached = self::hasReachedMaxChoiceForChoice($field_name, $currentEventId, $value, $actionTag);
				if ($reached) $_GET['maxChoiceFieldsReached'][] = $field_name;
			}
		}
		$_GET['maxChoiceFieldsReached'] = array_unique($_GET['maxChoiceFieldsReached']);
		// If empty, then just unset
		if (empty($_GET['maxChoiceFieldsReached'])) unset($_GET['maxChoiceFieldsReached']);
	}
	

	// ACTION TAGS: Get array of fields that have @MAXCHOICE action tag. 
	// Will check all fields in project or can check specific fields in $fields array provided.
	public static function getMaxChoiceFields($fields=array(), $actionTag="@MAXCHOICE")
	{
		global $Proj;
		$maxChoiceFields = array();
		if (empty($fields)) $fields = array_keys($Proj->metadata);
		$sql = "select field_name, misc from redcap_metadata where project_id = ".PROJECT_ID." 
				and field_name in (".prep_implode($fields).") and misc like '%$actionTag%'";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {			
			$maxChoiceText = Form::getValueInParenthesesActionTag($row['misc'], $actionTag);
			if (!empty($maxChoiceText)) {
				$maxChoiceFields[] = $row['field_name'];
			}
		}
		return $maxChoiceFields;
	}
	

	// ACTION TAGS: Determine if field has a @READONLY action tag
	public static function disableFieldViaActionTag($action_tags, $isSurveyPage=false)
	{
        if ($action_tags === null) return false;
		// Convert line breaks to spaces
		$action_tags = str_replace(array("\r", "\n", "\t"), array(" ", " ", " "), $action_tags);
		// Explode all action tags into array
		$action_tags_array = explode(" ", $action_tags);
		// @READONLY
		if (in_array('@READONLY', $action_tags_array)) return true;
		// @READONLY-FORM
		if (!$isSurveyPage && in_array('@READONLY-FORM', $action_tags_array)) return true;
		// @READONLY-SURVEY
		if ($isSurveyPage && in_array('@READONLY-SURVEY', $action_tags_array)) return true;
		// Return false if we got tot his point
		return false;
	}
	

	// ACTION TAGS: Return the value after equals sign for certain action tags - allows quotes 
	// but they are not required (@WORDLIMIT, @CHARLIMIT, etc.)
	public static function getValueInActionTag($field_annotation, $actionTag="@WORDLIMIT")
	{
		if($field_annotation === null){
			return '';
		}

		// Obtain the quoted value via regex
		preg_match("/(".$actionTag."\s*=\s*)(([\"][^\"]+[\"])|(['][^']+['])|([\"]?[^\"]+[\"]?)|([']?[^']+[']?))/", $field_annotation, $matches);
		if (isset($matches[2]) && $matches[2] != '') {
			// Remove wrapping quotes
            $value = $matches[2];
            $wrappedInQuotes = (strpos($value, "'") === 0 || strpos($value, '"') === 0);
            // If not wrapped in quotes, remove any text occurring after whitespace (because it's not part of the action tag)
            if (!$wrappedInQuotes) {
                $value = preg_split('/[\\s]+/', $value);
                $value = $value[0];
            }
			$value = trim($value,"'");
            $value = trim($value,'"');
            $value = trim($value);
            if (!$wrappedInQuotes) {
                $value = explode(" ", $value, 2)[0]; // Reduce to only value if not wrapped in quotes
            }
		} else {
			$value = '';
		}
		// Return the value
		return $value;
	}
	

	// ACTION TAGS: Return the value inside quotes for certain action tags (@DEFAULT="?", @PLACEHOLDER='?', etc.)
	public static function getValueInQuotesActionTag($field_annotation, $actionTag="@DEFAULT")
	{
		// Obtain the quoted value via regex
		preg_match("/(".$actionTag."\s*=\s*)((\"[^\"]+\")|('[^']+'))/", $field_annotation, $matches);
		if (isset($matches[2]) && $matches[2] != '') {
			// Remove wrapping quotes
			$defaultValue = substr($matches[2], 1, -1);
		} else {
			$defaultValue = '';
		}
		// Return the value inside the quotes
		return $defaultValue;
	}

	// ACTION TAGS: Return the value inside parentheses for certain action tags - @MAXCHOICE(1=50,2=75)
	public static function getValueInParenthesesActionTag($string, $actionTag="@MAXCHOICE")
	{
		if (strpos($string, $actionTag) === false) return "";
		// REPLACE ANY STRINGS IN QUOTES WITH A PLACEHOLDER BEFORE DOING THE OTHER REPLACEMENTS:
		$lqp = new LogicQuoteProtector();
		$string = $lqp->sub($string);
		// Get string length
		$stringlen = strlen($string);
	    // Remove space between action tag and first parentheses
	    $string = preg_replace("/(".$actionTag.")(\s*)(\()/", "$1$3", $string);
	    // Find beginning of the parentheses
		$begin = strpos($string, $actionTag."(")+strlen($actionTag."(")-1;
		// Loop through each character until we hit an equal number of opening/closing parentheses
        $openParen = 0;
        for ($k = $begin; $k < $stringlen; $k++) {
            $thisChar = $string[$k];
            if ($thisChar == "(") $openParen++;
            elseif ($thisChar == ")") $openParen--;
            if ($openParen == 0) {
                // We found the closing parenthesis, so return only what is inside the outer parentheses
                $string = trim(substr($string, $begin+1, $k-$begin-1));
				// UNDO THE REPLACEMENT BEFORE EVALUATING THE EXPRESSION
				$string = $lqp->unsub($string);
                return $string;
            }
        }
		// We couldn't find an opening/closing parentheses pair
		return "";
	}

	// ACTION TAGS: Replace the evaluated value of @IF in a field's Field Annotation
	public static function replaceIfActionTag($misc, $project_id, $record, $event_id, $instrument, $instance)
	{
        if ($misc == null || $misc == '') return "";
        // Pre-format the logic for easier parsing below
        $misc = preg_replace("/@IF\s*\(\s*/", "@IF(", $misc);
        $i = 0;
		while (preg_match("/@IF\(/", $misc) && $i < 10000) {
			$ifText = Form::getValueInParenthesesActionTag($misc, "@IF");
			if ($ifText != "") {
				$evaldIfText = Form::evaluateIfActionTag($ifText, $project_id, $record, $event_id, $instrument, $instance);
				// Replace the @IF() expression with the evaluated one
				$misc = preg_replace("/@IF\(\s*" . preg_quote($ifText, '/') . "\s*\)/", $evaldIfText, $misc);
			}
            $i++;
		}
        // If end up with 2 quotes, then revert to blank string
        if ($misc == '""' || $misc == "''") $misc = "";
        // Return new misc value
        return $misc;
	}

	// ACTION TAGS: Return the evaluated value inside @IF(condition,val1,val2)
    // $ifText should be provided as all the text inside a single @IF(...)
	public static function evaluateIfActionTag($ifText, $project_id, $record, $event_id, $instrument, $instance)
	{
        if (trim($ifText) == '') return '';
		// Remove all text inside quotes for easier parsing
		$lqp = new LogicQuoteProtector();
		$ifText = $lqp->sub($ifText);
		// Do more pre-formatting for easier parsing
		$ifText = str_replace(["\r\n", "\r", "\n", "\t", "@IF("], [" ", " ", " ", " ", "\nif("], $ifText);
		$ifText = LogicTester::convertIfStatement("if(".$ifText.")", 0, "\n?{RCT}", "\n:{RCT}", "IF-RC");
		// Loop through each line and add line break in front of the first closing parenthesis on each line that does not have an opening parenthesis.
		// We're essentially doing this so that the True/False parts of the IF are isolating on their own lines, after which we can replace them and then un-replace them in the end.
		$ifText2 = "";
		foreach (explode("\n", $ifText) as $curstr) {
			// The entire IF statement MIGHT be in this_string. Check if it is (commas and parens in correct order).
			$curstr_len = strlen($curstr);
			$nested_paren_count = 0;
			// Loop through the string letter by letter
			for ($i = 0; $i < $curstr_len; $i++) {
				// Get current letter
				$letter = substr($curstr, $i, 1);
				// Perform logic based on current letter and flags already set
				if ($letter == "(") {
					// Increment the count of how many nested parentheses we're inside of
					$nested_paren_count++;
				} elseif ($letter == ")" && $nested_paren_count > 0) {
					// We just left a nested parenthesis, so reduce count by 1 and keep looping
					$nested_paren_count--;
				} elseif ($letter == ")" && $nested_paren_count == 0) {
					$curstr = substr($curstr, 0, $i) . "\n" . substr($curstr, $i);
					break;
				}
			}
			$ifText2 .= "\n".$curstr;
		}
		$ifText = trim($ifText2);
		// Go through text line by line and replace all True/False conditions with numbers that correspond to keys in a replacement array
		$ifTextReplacements = [];
		$ifTextKey = 0;
		$ifText2 = "";
		foreach (explode("\n", $ifText) as $curstr) {
			$beginsQM = (strpos($curstr, "?{RCT}") === 0);
			if ($beginsQM || strpos($curstr, ":{RCT}") === 0) {
				$locFirstSpace = strpos($curstr, " ");
				$trueFalsePart = trim(substr($curstr, $locFirstSpace));
				if (trim($trueFalsePart) != "") {
					// Set replacement for line and place in array
					$curstr = ($beginsQM ? "?{RCT}" : ":{RCT}") . " \"$ifTextKey\"";
					$ifTextReplacements[$ifTextKey] = $trueFalsePart;
					$ifTextKey++;
				}
			}
			$ifText2 .= "\n".$curstr;
		}
		// Un-replace the question marks and remove the line breaks
		$ifText = "if" . trim(str_replace(["?{RCT}", ":{RCT}", "\n", "IF-RC"], [",", ",", " ", "if"], $ifText2));
		$ifText = str_replace("ifif", "if", $ifText);
		$ifText = preg_replace("/\s+/", " ", $ifText);
		// Un-replace the text inside quotes
		$ifText = $lqp->unsub($ifText);
        // If record doesn't exist yet, then submit empty array of redcap_data
        $record_data = null;
        if ($GLOBALS['hidden_edit'] == 0 && PAGE != 'PdfController:index') {
            $record_data = [" "=>Records::getDefaultValues($project_id)];
        }
		// Now let's evaluate the expression to return a single value that matches a key in $ifTextReplacements
		$Proj = new Project($project_id);
		$evaluatedVal = REDCap::evaluateLogic($ifText, $project_id, $record, $event_id, $instance, ($Proj->isRepeatingForm($event_id, $instrument) ? $instrument : ""), $instrument, $record_data, true, $GLOBALS['hidden_edit']);
        $thisReturnVal = "";
		if ($evaluatedVal != null) {
			$evaluatedVal = (int)$evaluatedVal;
			if (isset($ifTextReplacements[$evaluatedVal])) {
				$thisReturnVal = $ifTextReplacements[$evaluatedVal];
				// Un-replace the text inside quotes one more time to catch the return values that had quotes
				$thisReturnVal = $lqp->unsub($thisReturnVal);
			}
		}
		// Return the evaluated value of the @IF()
		return $thisReturnVal;
	}


	// @DOWNLOAD-COUNT ACTION TAG: Get all fields that have @DOWNLOAD-COUNT referencing a specific File Upload field
	public static function getDownloadCountTriggerFields($project_id, $uploadField)
	{
        if (!isinteger($project_id)) return [];
	    $tag = '@DOWNLOAD-COUNT';
		$Proj = new Project($project_id);
		// Use cache
		if (is_array(Project::$download_count_fields) && isset(Project::$download_count_fields[$project_id][$uploadField])) {
			$fields = Project::$download_count_fields[$project_id][$uploadField];
        }
		// Find fields and add to cache
		else {
			$fields = [];
			foreach ($Proj->metadata as $field => $attr) {
				if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
					$downloadCountContent = Form::getValueInParenthesesActionTag($attr['misc'], $tag);
					$downloadCountContent = str_replace(["[", "]", " "], ["", "", ""], $downloadCountContent);
					if (isset($Proj->metadata[$downloadCountContent]) && $downloadCountContent == $uploadField) {
						$fields[] = $field;
					}
				}
			}
			Project::$download_count_fields[$project_id][$uploadField] = $fields;
        }
		// Return the array of fields
		return $fields;
	}
	

	// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
	public static function getActionTagMatchRegex()
	{
		$action_tags_regex_quote = array();
		foreach (self::getActionTags() as $this_trig=>$this_descrip) {
			$action_tags_regex_quote[] = preg_quote($this_trig);
		}
		return "/(" . implode("|", $action_tags_regex_quote) .")($|[\s*\(]|[^(\-)])/";
	}
	

	// ACTION TAGS: Create regex string to detect all action tags being used in the Field Annotation
	// for ONLINE DESIGNER only (this will only be a minority of the action tags)
	public static function getActionTagMatchRegexOnlineDesigner()
	{
		$action_tags_regex_quote = array();
		foreach (self::getActionTags(true) as $this_trig=>$this_descrip) {
			$action_tags_regex_quote[] = preg_quote($this_trig);
		}
		return "/(" . implode("|", $action_tags_regex_quote) .")($|[^(\-)])/";
	}


	// Render data history log
	public static function renderDataHistoryLog($record, $event_id, $field_name, $instance)
	{
		global $lang, $require_change_reason, $Proj, $missingDataCodes, $user_rights;
        // Set field values
        $field_type = $Proj->metadata[$field_name]['element_type'];
        $field_form = $Proj->metadata[$field_name]['form_name'];
        $field_val_type = $Proj->metadata[$field_name]['element_validation_type'];
        // Version history enabled
        $version_history_enabled = ($field_type == 'file' && $field_val_type != 'signature' && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID));
		// Do URL decode of name (because it original was fetched from query string before sent via Post)
		$record = urldecode($record);
		// Set $instance
		$instance = is_numeric($instance) ? (int)$instance : 1;
		// Get data history log
		$time_value_array = array_values(self::getDataHistoryLog($record, $event_id, $field_name, $instance));
		// Get highest array key
		$max_dh_key = count($time_value_array)-1;
		// Set file download page
        $file_download_page = APP_PATH_WEBROOT . "DataEntry/file_download.php?pid=".PROJECT_ID."&page=" . $Proj->metadata[$field_name]['form_name']
                            . "&s=&record=$record&event_id=$event_id&field_name=$field_name&instance=$instance";
        $file_delete_page = APP_PATH_WEBROOT . "DataEntry/file_delete.php?pid=".PROJECT_ID."&page=" . $Proj->metadata[$field_name]['form_name'];
		// Loop through all rows and add to $rows
		$rows = "";
        foreach ($time_value_array as $key=>$row)
		{
		    $isLastRow = ($max_dh_key == $key);
			$rows .= RCView::tr(array('id'=>($isLastRow ? 'dh_table_last_tr' : '')),
						RCView::td(array('class'=>'data nowrap', 'style'=>'padding:5px 8px;text-align:center;width:170px;'),
							DateTimeRC::format_ts_from_ymd($row['ts'], true, true) .
							// Display "lastest change" label for the last row
							($isLastRow ? RCView::div(array('style'=>'color:#C00000;font-size:11px;padding-top:5px;'), $lang['dataqueries_277']) : '')
						) .
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;text-align:center;width:100px;word-wrap:break-word;'),
							$row['user']
						) .
						RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'),
							($row['missing_data_code'] == ""
                                ? $row['value']
                                : RCView::i(array('style'=>'color:#777;'), $lang['missing_data_12']).RCView::br().$missingDataCodes[$row['missing_data_code']] . " (".$row['missing_data_code'].")"
                            )
						) .
                        ($version_history_enabled
                            ?   RCView::td(array('class'=>'data text-center', 'style'=>'color:#000088;border:1px solid #ddd;padding:3px 8px;width:60px;'),
                                    ($row['doc_version'] != '' ? "V".$row['doc_version'] : "")
                                ) .
                                RCView::td(array('class'=>'data text-center', 'style'=>'border:1px solid #ddd;padding:3px 8px;width:200px;'),
									($row['missing_data_code'] != ""
                                        ? ""
                                        : ($row['doc_deleted'] != ""
                                            // Show deletion time
                                            ? RCView::i(array('style'=>'color:#777;font-size:12px;'),
                                                $lang['data_entry_465'] . " " .
                                                DateTimeRC::format_ts_from_ymd($row['doc_deleted'], false, false)
                                            )
                                            // Show download/delete buttons
                                            : RCView::button(array('class'=>'float-start btn btn-xs btn-primaryrc fs12', 'onclick'=>"window.open('$file_download_page&doc_id_hash=".Files::docIdHash($row['doc_id'])."&id=".$row['doc_id']."&doc_version=".$row['doc_version']."&doc_version_hash=".Files::docIdHash($row['doc_id']."v".$row['doc_version'])."','_blank');"),
                                                '<i class="fas fa-download"></i> '.$lang['docs_58']
                                              ) .
                                              ($user_rights['forms'][$field_form] == '2' ? "" : // Don't display "delete" button if user has read-only rights
                                                  RCView::a(array('href'=>'javascript:;', 'class'=>'mt-1 fs11 float-end', 'style'=>'color:#A00000;', 'onclick'=>'deleteDocumentConfirm('.$row['doc_id'].',"'.$field_name.'","'.$record.'",'.$event_id.','.$instance.',"'.$file_delete_page.'","'.($isLastRow ? '' : $row['doc_version']).'","'.($isLastRow ? '' : Files::docIdHash($row['doc_id']."v".$row['doc_version'])).'");'),
                                                    '<i class="far fa-trash-alt"></i> '.$lang['global_19']
                                                  )
                                              )
                                        )
                                    )
                                )
                            : ""
                        ) .
						($require_change_reason
							? 	RCView::td(array('class'=>'data', 'style'=>'border:1px solid #ddd;padding:3px 8px;'),
									$row['change_reason']
								)
							: 	""
						)
					);
		}
		// If no data history log exists yet for field, give message
		if (empty($time_value_array))
		{
			$rows .= RCView::tr('',
						RCView::td(array('class'=>'data', 'colspan'=>($require_change_reason ? '4' : '3'), 'style'=>'border-top: 1px #ccc;padding:6px 8px;text-align:center;'),
							$lang['data_history_05']
						)
					);
		}
		// Output the table headers as a separate table (so they are visible when scrolling)
		$table = RCView::table(array('class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:95%;'),
					RCView::tr('',
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:170px;'),
                            ($version_history_enabled ? $lang['data_history_06'] : $lang['data_history_01'])
						) .
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;width:100px;'),
							$lang['global_17']
						) .
						RCView::td(array('class'=>'label_header', 'style'=>'padding:5px 8px;'),
                            ($version_history_enabled ? $lang['data_entry_466'] : $lang['data_history_03'])
						) .
                        ($version_history_enabled
                            ?   RCView::td(array('class'=>'label_header fs11 text-center wrap', 'style'=>'padding:5px 8px;width:60px;'),
                                    $lang['data_entry_458']
                                ) .
                                RCView::td(array('class'=>'label_header fs11 text-center', 'style'=>'padding:5px 8px;width:200px;'),
                                    $lang['data_entry_467']
                                )
                            : ""
                        ) .
						($require_change_reason
							? 	RCView::td(array('class'=>'label_header fs11', 'style'=>'padding:5px 8px;'),
									$lang['data_history_04']
								)
							: 	""
						)
					)
				);
		// Output table html
		$table .= RCView::div(array('id'=>'data_history3', 'style'=>'overflow-y:scroll;'),
					RCView::table(array('id'=>'dh_table', 'class'=>'form_border', 'style'=>'table-layout:fixed;border:1px solid #ddd;width:97%;'),
						$rows
					)
				  );
		// Return html
		return $table;
	}


	// Get log of data history (returns in chronological ASCENDING order)
	public static function getDataHistoryLog($record, $event_id, $field_name, $instance=1)
	{
		global $double_data_entry, $user_rights, $longitudinal, $Proj, $lang, $missingDataCodes;

		// Set field values
		$field_type = $Proj->metadata[$field_name]['element_type'];
        $field_val_type = $Proj->metadata[$field_name]['element_validation_type'];

		// Version history enabled
        $version_history_enabled = ($field_type == 'file' && $field_val_type != 'signature' && Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID));
		
		// Does user have access to this field's form? If not, do not display the field's data.
		$hasFieldViewingRights = (isset($user_rights) && $user_rights['forms'][$Proj->metadata[$field_name]['form_name']] > 0);

		// Determine if a multiple choice field (do not include checkboxes because we'll used their native logging format for display)
		$isMC = ($Proj->isMultipleChoice($field_name) && $field_type != 'checkbox');
		if ($isMC) {
			$field_choices = parseEnum($Proj->metadata[$field_name]['element_enum']);
		}

		// Format the field_name with escaped underscores for the query
		$field_name_q = str_replace("_", "\\_", $field_name);
		
		// REPEATING FORMS/EVENTS: Check for "instance" number if the form is set to repeat
		$instanceSql = "and data_values not like '[instance = %'";
		$isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $Proj->metadata[$field_name]['form_name']);
		if ($isRepeatingFormOrEvent) {
			// Set $instance
			$instance = is_numeric($instance) ? (int)$instance : 1;
			if ($instance > 1) {
				$instanceSql = "and data_values like '[instance = $instance]%'";
			}
		}

		// Adjust record name for DDE
		if ($double_data_entry && isset($user_rights) && $user_rights['double_data'] != 0) {
			$record .= "--" . $user_rights['double_data'];
		}

		// Default
		$time_value_array = array();
		$arm = isset($Proj->eventInfo[$event_id]) ? $Proj->eventInfo[$event_id]['arm_num'] : getArm();

		// Retrieve history and parse field data values to obtain value for specific field
		$sql = "SELECT user, timestamp(ts) as ts, data_values, description, change_reason, event 
                FROM ".Logging::getLogEventTable(PROJECT_ID)." WHERE project_id = " . PROJECT_ID . " and pk = '" . db_escape($record) . "'
				and (
				(
					(event_id = $event_id " . ($longitudinal ? "" : "or event_id is null") . ")
					and legacy = 0 $instanceSql
					and
					(
						(
							event in ('INSERT', 'UPDATE')
							and description in ('Create record', 'Update record', 'Update record (import)',
								'Create record (import)', 'Merge records', 'Update record (API)', 'Create record (API)',
								'Update record (DTS)', 'Update record (DDP)', 'Erase survey responses and start survey over',
								'Update survey response', 'Create survey response', 'Update record (Auto calculation)',
								'Update survey response (Auto calculation)', 'Delete all record data for single form',
								'Delete all record data for single event', 'Update record (API) (Auto calculation)')
							and (data_values like '%\\n{$field_name_q} = %' or data_values like '{$field_name_q} = %' 
								or data_values like '%\\n{$field_name_q}(%) = %' or data_values like '{$field_name_q}(%) = %')
						)
						or
						(event = 'DOC_DELETE' and data_values = '$field_name')
						or
						(event = 'DOC_UPLOAD' and (data_values like '%\\n{$field_name_q} = %' or data_values like '{$field_name_q} = %' 
													or data_values like '%\\n{$field_name_q}(%) = %' or data_values like '{$field_name_q}(%) = %'))
					)
				)
				or 
				(event = 'DELETE' and description like 'Delete record%' and (event_id is null or event_id in (".prep_implode(array_keys($Proj->events[$arm]['events'])).")))
				)
				order by log_event_id";
		$q = db_query($sql);
		// Loop through each row from log_event table. Each will become a row in the new table displayed.
        $version_num = 0;
        $this_version_num = "";
        $rows = array();
        $deleted_doc_ids = array();
        while ($row = db_fetch_assoc($q))
        {
            $rows[] = $row;
            // For File Version History for file upload fields, get doc_id all any that were deleted
            if ($version_history_enabled) {
                $value = html_entity_decode($row['data_values'], ENT_QUOTES);
                foreach (explode(",\n", $value) as $this_piece) {
                    $doc_id = self::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
                    if (is_numeric($doc_id)) {
                        $doc_delete_time = Files::wasEdocDeleted($doc_id);
                        if ($doc_delete_time) {
                            $deleted_doc_ids[$doc_id] = $doc_delete_time;
                        }
                    }
                }
            }
        }
        // Loop through all rows
		foreach ($rows as $row)
		{
			// If the record was deleted in the past, then remove all activity before that point
			if ($row['event'] == 'DELETE') {
				$time_value_array = array();
                $version_num = 0;
				continue;
			}
			// Flag to denote if found match in this row
			$matchedThisRow = false;
			// Get timestamp
			$ts = $row['ts'];
			// Get username
			$user = $row['user'];
			// Decode values
			$value = html_entity_decode($row['data_values'], ENT_QUOTES);
            // Default return string
            $this_value = "";
            // Split each field into lines/array elements.
            // Loop to find the string match
            foreach (explode(",\n", $value) as $this_piece)
            {
                $isMissingCode = false;
                // Does this line match the logging format?
                $matched = self::dataHistoryMatchLogString($field_name, $field_type, $this_piece);
                if ($matched !== false || ($field_type == "file" && ($this_piece == $field_name || strpos($this_piece, "$field_name = ") === 0)))
                {
                    // Set flag that match was found
                    $matchedThisRow = true;
                    // File Upload fields
                    if ($field_type == "file")
                    {
						if (isset($missingDataCodes[$matched])) {
							// Set text
							$this_value = $matched;
							$doc_id = null;
							$this_version_num = "";
							$isMissingCode = true;
						} elseif ($matched === false || $matched == '') {
                            // For File Version History, don't show separate rows for deleted files
                            if ($version_history_enabled) continue 2;
                            // Deleted
                            $doc_id = null;
                            $this_version_num = "";
                            // Set text
                            $this_value = RCView::span(array('style'=>'color:#A00000;'), $lang['docs_72']);
                        } elseif (is_numeric($matched)) {
                            // Uploaded
                            $doc_id = $matched;
                            $doc_name = Files::getEdocName($doc_id);
                            $version_num++;
                            $this_version_num = $version_num;
                            // Set text
                            $this_value = RCView::span(array('style'=>'color:green;'),
                                            $lang['data_import_tool_20']
                                            ). " - \"{$doc_name}\"";
                        }
                        break;
                    }
                    // Stop looping once we have the value (except for checkboxes)
                    elseif ($field_type != "checkbox")
                    {
                        $this_value = $matched;
                        break;
                    }
                    // Checkboxes may have multiple values, so append onto each other if another match occurs
                    else
                    {
                        $this_value .= $matched . "<br>";
                    }
                }
            }

            // If a multiple choice question, give label AND coding
            if ($isMC && $this_value != "")
            {
                if (isset($missingDataCodes[$this_value])) {
					$this_value = decode_filter_tags($missingDataCodes[$this_value]) . " ($this_value)";
                } else {
					$this_value = decode_filter_tags($field_choices[$this_value]) . " ($this_value)";
				}
            }

			// Add to array (if match was found in this row)
			if ($matchedThisRow) {			
				// If user does not have privileges to view field's form, redact data
				if (!$hasFieldViewingRights) {
					$this_value = "<code>".$lang['dataqueries_304']."</code>";
				} elseif ($field_type != "file") {
					$this_value = nl2br(htmlspecialchars(br2nl(label_decode($this_value)), ENT_QUOTES));
				}
				// Set array key as timestamp + extra digits for padding for simultaneous events
				$key = strtotime($ts)*100;
				// Ensure that we don't overwrite existing logged events
				while (isset($time_value_array[$key.""])) $key++;
				// Display missing data code?
				$returningMissingCode = (isset($missingDataCodes[$this_value]) && !Form::hasActionTag("@NOMISSING", $Proj->metadata[$field_name]['misc']));
				// Add to array
				$time_value_array[$key.""] = array( 'ts'=>$ts, 'value'=>$this_value, 'user'=>$user, 'change_reason'=>nl2br($row['change_reason']??""),
                                                    'doc_version'=>$this_version_num, 'doc_id'=>(isset($doc_id) ? $doc_id : null),
                                                    'doc_deleted'=>(isset($doc_id) && isset($deleted_doc_ids[$doc_id]) ? $deleted_doc_ids[$doc_id] : ""),
                                                    'missing_data_code'=>($returningMissingCode ? $this_value : ''));
			}
		}

        // Fixed: Entries were displayed twice for secondary ID field
        $time_value_array = array_unique($time_value_array,SORT_REGULAR);

		// Sort by timestamp
		ksort($time_value_array);
		// Return data history log
		return $time_value_array;
	}


	// Determine if string matches REDCap logging format (based upon field type)
	public static function dataHistoryMatchLogString($field_name, $field_type, $string)
	{
		// If matches checkbox logging
		if ($field_type == "checkbox" && substr($string, 0, strlen("$field_name(")) == "$field_name(") // && preg_match("/^($field_name\()([a-zA-Z_0-9])(\) = )(checked|unchecked)$/", $string))
		{
			return $string;
		}
		// If matches logging for all fields (excluding checkboxes)
		elseif ($field_type != "checkbox" && substr($string, 0, strlen("$field_name = '")) == "$field_name = '")
		{
			// Remove apostrophe from end (if exists)
			if (substr($string, -1) == "'") $string = substr($string, 0, -1);
			$value = substr($string, strlen("$field_name = '"));
			return ($value === false ? '' : $value);
		}
		// Did not match this line
		else
		{
			return false;
		}
	}


	// Parse the element_enum column into the 3 slider labels (if only 1 assume Left; if 2 asssum Left&Right)
	public static function parseSliderLabels($element_enum)
	{
		// Explode into array, where strings should be delimited with pipe |
		$slider_labels  = array();
		$slider_labels2 = array('left'=>'','middle'=>'','right'=>'');
		foreach (explode("|", $element_enum, 3) as $label)
		{
			$slider_labels[] = trim($label);
		}
		// Set keys
		switch (count($slider_labels))
		{
			case 1:
				$slider_labels2['left']   = $slider_labels[0];
				break;
			case 2:
				$slider_labels2['left']   = $slider_labels[0];
				$slider_labels2['right']  = $slider_labels[1];
				break;
			case 3:
				$slider_labels2['left']   = $slider_labels[0];
				$slider_labels2['middle'] = $slider_labels[1];
				$slider_labels2['right']  = $slider_labels[2];
				break;
		}
		// Return array
		return $slider_labels2;
	}


	// Get all options for drop-down displaying all project fields
	public static function getFieldDropdownOptions($removeCheckboxFields=false, $includeMultipleChoiceFieldsOnly=false, $includeDAGoption=false, 
												   $includeEventsOption=false, $limitToValidationType=null, $addBlankOption=true, 
												   $addFormLabelDividers=true, $alsoIncludeRecordIdField=false, $limitToFieldType=null,
                                                   $overrideBlankOptionText=null, $includeSqlFieldsInMCFields=false)
	{
		global $Proj, $lang;
		$rc_fields = array();
		// Set array with initial "select a field" option
		if ($addBlankOption) {
			$rc_fields[''] = ($overrideBlankOptionText == null) ? '-- '.$lang['random_02'].' --' : $overrideBlankOptionText;
		}
		// Add the events field (if specified)
		if ($includeEventsOption) {
			$rc_fields[DataExport::LIVE_FILTER_EVENT_FIELD] = '['.$lang['global_45'].']';
		}
		// Add the DAG field (if specified)
		if ($includeDAGoption) {
			$rc_fields[DataExport::LIVE_FILTER_DAG_FIELD] = '['.$lang['global_22'].']';
		}
		// Set format of valtype param to array
		if ($limitToValidationType !== null && !is_array($limitToValidationType)) {
			$limitToValidationType = array($limitToValidationType);
        }
		// Set format of fieldtype param to array
		if (!empty($limitToFieldType) && !is_array($limitToFieldType)) {
			$limitToFieldType = array($limitToFieldType);
		}
		// Build an array of drop-down options listing all REDCap fields
		foreach ($Proj->metadata as $this_field=>$attr1) {
			// Add the record ID field?
			if (!($alsoIncludeRecordIdField && $this_field == $Proj->table_pk)) {
				// Skip descriptive fields
				if ($attr1['element_type'] == 'descriptive') continue;
				// Skip checkbox fields if flag is set
				if ($removeCheckboxFields && $attr1['element_type'] == 'checkbox') continue;
				// Skip non-multiple choice fields, if specified
				if ($includeMultipleChoiceFieldsOnly && !$Proj->isMultipleChoice($this_field)) {
                    if (!($includeSqlFieldsInMCFields && $Proj->isSqlField($this_field))) {
						continue;
                    } elseif (!$includeSqlFieldsInMCFields) {
                        continue;
                    }
				}
				// If limiting fields to a specific validation type(s), then exclude all others
				if ($limitToValidationType !== null && !in_array($attr1['element_validation_type'], $limitToValidationType)) continue;
				// If limiting fields to a specific field type(s), then exclude all others
				if (!empty($limitToFieldType) && !in_array($attr1['element_type'], $limitToFieldType)) continue;
			}
			// Add to fields/forms array. Get form of field.
			$this_form_label = $Proj->forms[$attr1['form_name']]['menu'];
			// Clean the label
			$attr1['element_label'] = trim(str_replace(array("\r\n", "\n"), array(" ", " "), strip_tags($attr1['element_label']."")));
			// Truncate label if long
			if (mb_strlen($attr1['element_label']) > 65) {
				$attr1['element_label'] = trim(mb_substr($attr1['element_label'], 0, 47)) . "... " . trim(mb_substr($attr1['element_label'], -15));
			}
			if ($addFormLabelDividers) {
				$rc_fields[$this_form_label][$this_field] = "$this_field \"{$attr1['element_label']}\"";
			} else {
				$rc_fields[$this_field] = "$this_field \"{$attr1['element_label']}\"";
			}
		}
		// Return all options
		return $rc_fields;
	}


	// Return boolean if a calc field's equation in Draft Mode is being changed AND that field contains some data
	public static function changedCalculationsWithData()
	{
		global $Proj, $status;
		// On error, return false
		if ($status < 1 || empty($Proj->metadata_temp)) return false;
		// Add field to array if has a calculation change
		$calcs_changed = array();
		// Loop through drafted changes
		foreach ($Proj->metadata_temp as $this_field=>$attr1) {
			// Skip non-calc fields
			if ($attr1['element_type'] != 'calc') continue;
			// If field does not yet exist, then skip
			if (!isset($Proj->metadata[$this_field])) continue;
			// Compare the equation for each
			if (trim(label_decode($attr1['element_enum'])) != trim(label_decode($Proj->metadata[$this_field]['element_enum']))) {
				$calcs_changed[] = $this_field;
			}
		}
		// Return false if no calculations changed
		if (empty($calcs_changed)) return false;
		// Query to see if any data exists for any of these changed calc fields
		$sql = "select 1 from ".\Records::getDataTable(PROJECT_ID)." where project_id = ".PROJECT_ID."
				and field_name in (".prep_implode($calcs_changed).") and value != '' limit 1";
		$q = db_query($sql);
		// Return true if any calc fields that were changed have data in them
		return (db_num_rows($q) > 0);
	}


	// Add web service values into cache table
	public static function addWebServiceCacheValues($project_id, $service, $category, $value, $label, $checkCache=true)
	{
		// First, check if it's already in the table. If so, return false
		if ($checkCache && self::getWebServiceCacheValues($project_id, $service, $category, $value) != '') {
			return false;
		}
		// Add to table
		$sql = "insert into redcap_web_service_cache (project_id, service, category, value, label)
				values ($project_id, '".db_escape($service)."', '".db_escape($category)."', '".db_escape($value)."', '".db_escape($label)."')";
		$q = db_query($sql);
		return db_insert_id();
	}


	// Obtain web service label from cache table of one item
	public static function getWebServiceCacheValues($project_id, $service, $category, $value)
	{
		// If value is blank, then return blank
		if ($value == '') return '';
		// Query table
		$sql = "select label from redcap_web_service_cache where project_id = $project_id and
				service = '".db_escape($service)."' and category = '".db_escape($category)."' and value = '".db_escape($value)."'";
		$q = db_query($sql);
		if (db_num_rows($q)) {
		    return db_result($q, 0);
        } else {
		    // If value has not had its label cached in the db table, then see if it has been cached for another project. If so, add it to this one.
			$sql = "select label from redcap_web_service_cache where 
                    service = '".db_escape($service)."' and category = '".db_escape($category)."' and value = '".db_escape($value)."' limit 1";
			$q = db_query($sql);
			// Label is not cached by any other project, so return blank value
			if (!db_num_rows($q)) return '';
			$label = db_result($q, 0);
            // Place value into cache table
			Form::addWebServiceCacheValues($project_id, $service, $category, $value, $label, false);
			// Return new label from other project
			return $label;
        }
	}


	// Obtain web service label from cache table of one item
	public static function getWebServiceCacheValuesBulk($project_id, $limitFields=array())
	{
		// Get fields using web service
		$fieldValuesLabels = $services = $categories = array();
		$limit = !empty($limitFields);
		$Proj = new Project($project_id);
		foreach ($Proj->metadata as $field=>$attr) {
			if ($limit && !in_array($field, $limitFields)) continue;
			if ($attr['element_type'] == 'text' && $attr['element_enum'] != '' && strpos($attr['element_enum'], ":") !== false) {
				// Get the name of the name of the web service API and the category (ontology) name
				list ($autosuggest_service, $autosuggest_cat) = explode(":", $attr['element_enum'], 2);
				$services[$autosuggest_service][$autosuggest_cat][$field] = array();
				$categories[] = $autosuggest_cat;
			}
		}
		// Query table
		$sql = "select service, category, value, label from redcap_web_service_cache where project_id = $project_id
				and service in (".prep_implode(array_keys($services)).") and category in (".prep_implode($categories).")";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			// Add this value/label to ALL fields using this ontology
			foreach (array_keys($services[$row['service']][$row['category']]) as $this_field) {
				$fieldValuesLabels[$this_field][$row['value']] = $row['label'];
			}
		}
		// Return array of fields with values/labels
		return $fieldValuesLabels;
	}


	// Perform server-side validation
	public static function serverSideValidation($postValues=array())
	{
		global $Proj, $fetched, $missingDataCodes;
		// Set array to collect any errors in server side validation
		$errors = array();
		// Create array of all field validation types and their attributes
		$valTypes = getValTypes();
		
		//get missing data codes
		$missingDataCodeKeys=array_keys($missingDataCodes);
				
		// Loop through submitted fields
				
		foreach ($postValues as $field=>$val)
		{
			// Make sure this is a real field, first
			if (!isset($Proj->metadata[$field])) continue;
			// Skip the record ID field
			if ($field == $Proj->table_pk) continue;
			// If a blank value then skip
			if ($val == '' || in_array($val, $missingDataCodeKeys)) continue;
			// Get validation type
			$val_type = $Proj->metadata[$field]['element_validation_type'];
			// If field is multiple choice field, then validate its value
			if ($Proj->isMultipleChoice($field) || $Proj->metadata[$field]['element_type'] == 'sql') {
				// Parse the field's choices
				$enum = $Proj->metadata[$field]['element_enum'];
				$choices = ($Proj->metadata[$field]['element_type'] == 'sql') ? parseEnum(getSqlFieldEnum($enum, PROJECT_ID, $fetched, $_GET['event_id'], $_GET['instance'], null, null, $_GET['page'])) : parseEnum($enum);
				// If not a valid choice, then add to errors array
				if (!isset($choices[$val])) $errors[$field] = $val;
			} 
			// If field is text field with validation
			elseif ($Proj->metadata[$field]['element_type'] == 'text' && $val_type != '') {
				// Get the regex
				if ($val_type == 'int') $val_type = 'integer';
				elseif ($val_type == 'float') $val_type = 'number';
				if (!isset($valTypes[$val_type])) continue;
				$regex = $valTypes[$val_type]['regex_php'];
				// Run the value through the regex pattern
				preg_match($regex, $val, $regex_matches);
				// Was it validated? (If so, will have a value in 0 key in array returned.)
				$failed_regex = (!isset($regex_matches[0]));
				// Set error message if failed regex
				if ($failed_regex) $errors[$field] = $val;
			}			
		}		
		
		// Remove any fields from POST if they failed server-side validation
		Form::removeFailedServerSideValidationsPost($errors);
		
		// Add the failed server-side validations to session to pick up elsewhere
		if (!empty($errors)) $_SESSION['serverSideValErrors'] = $errors;
		
		// Return errors
		return $errors;
	}
	
	// Remove any fields from POST if they failed server-side validation
	public static function removeFailedServerSideValidationsPost($serverSideValErrors=array())
	{
		foreach ($serverSideValErrors as $field=>$val) {
			unset($_POST[$field]);
		}
	}
	
	// Display dialog if server-side validation was violated
	public static function displayFailedServerSideValidationsPopup($serverSideValErrors, $is_survey)
	{
		global $Proj;

		// MultiLanguage
		$context = \REDCap\Context::Builder()
			->project_id($Proj->project_id);
		if ($is_survey) {
			$context->is_survey();
		}
		else {
			$context->is_dataentry();
		}
		$context = $context->Build();
		
		$data_entry_271 = MultiLanguage::getUITranslation($context, "data_entry_271");
		$data_entry_272 = MultiLanguage::getUITranslation($context, "data_entry_272");
		$data_entry_530 = MultiLanguage::getUITranslation($context, "data_entry_530");
		$calendar_popup_01 = MultiLanguage::getUITranslation($context, "calendar_popup_01");

		// Obtain the field labels
		$fieldLabels = array();
		$fields = explode(",", strip_tags($serverSideValErrors));
		foreach ($fields as $field) {
			if (!isset($Proj->metadata[$field])) continue;
			$label = MultiLanguage::getDDTranslation($context, "field-label", $field, "", $Proj->metadata[$field]['element_label']);
			$label = strip_tags(label_decode($label));
			if (mb_strlen($label) > 60) $label = mb_substr($label, 0, 40)."...".mb_substr($label, -18);
			$fieldLabels[] = $label;			
		}
		// Output hidden dialog div 
		print 	RCView::div(array('id'=>'serverside_validation_violated', 'class'=>'simpleDialog'),
					RCView::div(array('style'=>'padding-bottom:10px;'), $data_entry_271) .
					RCView::div(array('style'=>'font-weight:bold;'), $data_entry_272) .
					"<ul><li>\"" . implode("\"</li><li>\"", $fieldLabels) . "\"</li></ul>"
				);
		// Javascript
		?>
		<script type='text/javascript'>
		$(function(){
			setTimeout(function(){
				// POP-UP DIALOG
				$('#serverside_validation_violated').dialog({
					bgiframe: true, 
					modal: true, 
					width: (isMobileDevice ? $(window).width() : 500), 
					open: function(){fitDialog(this)},
					title: '<i class="fas fa-exclamation-triangle text-danger" style="vertical-align:middle;"></i> <span style="vertical-align:middle;" data-rc-lang="data_entry_530"><?=$data_entry_530?></span>',
					buttons: [
						{
							html: '<span data-rc-lang="calendar_popup_01"><?=$calendar_popup_01?></span>',
							click: function() { $(this).dialog('close'); }
						}
					]
				});
			},(isMobileDevice ? 1500 : 0));
		});
		</script>
		<?php
	}
	
	// Display dialog for @MAXCHOICE error pop-up message
	public static function displayFailedSaveMaxChoicePopup($maxChoiceErrors)
	{
		global $Proj;
		// Obtain the field labels
		$fieldLabels = array();
		$fields = explode(",", strip_tags($maxChoiceErrors));
		foreach ($fields as $field) {
			if (!isset($Proj->metadata[$field])) continue;
			$label = strip_tags(label_decode($Proj->metadata[$field]['element_label']));
			if (mb_strlen($label) > 60) $label = mb_substr($label, 0, 40)."...".mb_substr($label, -18);
			$fieldLabels[] = $label;			
		}
		// Output hidden dialog div 
		print 	RCView::div(array('id'=>'maxchoice_violated', 'class'=>'simpleDialog'),
					RCView::div(array('style'=>'padding-bottom:10px;'), RCView::tt("data_entry_423")) .
					RCView::div(array('style'=>'font-weight:bold;'), RCView::tt("data_entry_424")) .
					"<ul><li>\"" . implode("\"</li><li>\"", $fieldLabels) . "\"</li></ul>"
				);
		// Javascript
		?>
		<script type='text/javascript'>
		$(function(){
			setTimeout(function(){
				// POP-UP DIALOG
				$('#maxchoice_violated').dialog({ bgiframe: true, modal: true, width: (isMobileDevice ? $(window).width() : 780), open: function(){fitDialog(this)},
					title: '<?php echo js_escape(RCView::img(array('src'=>'exclamation_frame.png','style'=>'vertical-align:middle;')) . RCView::tt("data_entry_531", "span", array('style'=>'vertical-align:middle;'))) ?>',
					buttons: {
						'<?=RCView::tt_js("calendar_popup_01")?>': function() { $(this).dialog('close'); }
					}
				});
			},(isMobileDevice ? 1500 : 0));
		});
		</script>
		<?php
	}

    /**
     * Get all fields that have @MC-PARTICIPANT-JOINDATE action tag
     *
     * @param int $project_id
     * @return array
     */
    public static function getMyCapParticipantInstallDateFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_JOINDATE;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_installdate_fields) && isset(Project::$mycap_participant_installdate_fields[$project_id])) {
            $fields = Project::$mycap_participant_installdate_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_installdate_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Check if project contains field to capture Install Date (Field with annotation @MC-PARTICIPANT-JOINDATE)
     *
     * @param int $projectId
     * @return void
     */
    public static function checkInstallDateFieldExists($projectId, $annotation = Annotation::PARTICIPANT_JOINDATE)
    {
        $dictionary = \REDCap::getDataDictionary($projectId, 'array');
        foreach ($dictionary as $field) {
            if (strpos(
                    $field['field_annotation'],
                    $annotation
                ) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add New MyCap Field - Install Date with action tag @MC-PARTICIPANT-JOINDATE
     *
     * @param int $projectId
     * @return void
     */
    public static function addMyCapInstallDateField($projectId) {
	    $Proj = new Project($projectId);
	    // Check if install date field exists or not
        $installDateFieldExists = self::checkInstallDateFieldExists($projectId);
        if ($installDateFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_joindate');
            $field['field_label'] = "Install Date";
            $field['field_annotation'] = Annotation::PARTICIPANT_JOINDATE . ' @HIDDEN';
            $field['val_type'] = "datetime_seconds_ymd";
            $projectDesigner->createField($Proj->firstForm, $field);
        }
    }

    /**
     * Add New MyCap Field - Code with action tag @MC-PARTICIPANT-CODE @HIDDEN
     *
     * @param int $projectId
     * @return void
     */
    public static function addMyCapCodeField($projectId) {
        $Proj = new Project($projectId);
        // Check if code field exists or not
        $codeFieldExists = self::checkCodeFieldExists($projectId);
        if ($codeFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_code');
            $field['field_label'] = "Participant Code";
            $field['field_annotation'] = Annotation::PARTICIPANT_CODE . ' @HIDDEN';
            $projectDesigner->createField($Proj->firstForm, $field);
        }
    }

    /**
     * Check if project contains field to capture Code (Field with annotation @MC-PARTICIPANT-CODE)
     *
     * @param int $projectId
     * @return void
     */
    public static function checkCodeFieldExists($projectId)
    {
        $dictionary = \REDCap::getDataDictionary($projectId, 'array');
        foreach ($dictionary as $field) {
            if (strpos(
                    $field['field_annotation'],
                    Annotation::PARTICIPANT_CODE
                ) !== false) {
                return true;
            }
        }
        return false;
    }

    // Get all fields that have @MC-PARTICIPANT-CODE action tag
    public static function getMyCapParticipantCodeFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_CODE;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_code_fields) && isset(Project::$mycap_participant_code_fields[$project_id])) {
            $fields = Project::$mycap_participant_code_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_code_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Add New MyCap Field - Install Date (UTC) and timezone with action tag @MC-PARTICIPANT-JOINDATE-UTC and @MC-PARTICIPANT-TIMEZONE
     *
     * @param int $projectId
     * @return void
     */
    public static function addExtraMyCapInstallDateField($projectId) {
        $Proj = new Project($projectId);
        // Check if install date (UTC) field exists or not
        $installDateUtcFieldExists = self::checkInstallDateFieldExists($projectId, Annotation::PARTICIPANT_JOINDATE_UTC);
        if ($installDateUtcFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field = [];
            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_joindate_utc');
            $field['field_label'] = "Install Date (UTC)";
            $field['field_annotation'] = Annotation::PARTICIPANT_JOINDATE_UTC . ' @HIDDEN';
            $field['val_type'] = "datetime_seconds_ymd";
            $projectDesigner->createField($Proj->firstForm, $field);
        }

        // Check if Participant timezone field exists or not
        $timezoneFieldExists = self::checkInstallDateFieldExists($projectId, Annotation::PARTICIPANT_TIMEZONE);
        if ($timezoneFieldExists == false) {
            $Proj->loadMetadata();
            $projectDesigner = new ProjectDesigner($Proj);

            $field = [];
            $field['field_type'] = "text";
            $field['field_name'] = ActiveTask::getNewFieldName('par_timezone');
            $field['field_label'] = "Participant Timezone";
            $field['field_annotation'] = Annotation::PARTICIPANT_TIMEZONE . ' @HIDDEN';
            $projectDesigner->createField($Proj->firstForm, $field);
        }
    }

    /**
     * Get all fields that have @MC-PARTICIPANT-JOINDATE-UTC action tag
     *
     * @param int $project_id
     * @return array
     */
    public static function getMyCapParticipantInstallDateUTCFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_JOINDATE_UTC;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_installdate_utc_fields) && isset(Project::$mycap_participant_installdate_utc_fields[$project_id])) {
            $fields = Project::$mycap_participant_installdate_utc_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_installdate_utc_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Get all fields that have @MC-PARTICIPANT-TIMEZONE action tag
     *
     * @param int $project_id
     * @return array
     */
    public static function getMyCapParticipantTimezoneFields($project_id)
    {
        if (!isinteger($project_id)) return [];
        $tag = Annotation::PARTICIPANT_TIMEZONE;
        $Proj = new Project($project_id);
        // Use cache
        if (is_array(Project::$mycap_participant_timezone_fields) && isset(Project::$mycap_participant_timezone_fields[$project_id])) {
            $fields = Project::$mycap_participant_timezone_fields[$project_id];
        }
        // Find fields and add to cache
        else {
            $fields = [];
            foreach ($Proj->metadata as $field => $attr) {
                if ($attr['misc'] !== null && strpos($attr['misc'], $tag) !== false) {
                    $fields[] = $field;
                }
            }
            Project::$mycap_participant_timezone_fields[$project_id] = $fields;
        }
        // Return the array of fields
        return $fields;
    }

    /**
     * Save values (install date, install date UTC, timezone) to all fields for records
     *
     * @param int $projectId
     * @param array $fields
     * @param string $record
     * @param string $newValue
     * @return array
     */
    public static function saveMyCapInstallDateInfo($projectId, $fields, $record, $newValue)
    {
        $records = json_decode(\REDCap::getData($projectId, 'json', [$record], $fields), true);
        $Proj = new Project($projectId);
        $instance = null;
        $event_id = null;
        foreach ($records as $attr) {
            if ($attr['redcap_repeat_instance'] == $instance || $attr['redcap_repeat_instance'] == "") {
                foreach ($fields as $field) {
                    if ($field != $Proj->table_pk) { // Skip when its primary key
                        // Save field value
                        $record_data = [[$Proj->table_pk => $record, $field => $newValue]];
                        if ($Proj->longitudinal) $record_data[0]['redcap_event_name'] = $attr['redcap_event_name'];
                        $hasRepeatingInstances = ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $attr['redcap_repeat_instrument']));
                        if ($hasRepeatingInstances) {
                            $record_data[0]['rexdcap_repeat_instrument'] = $attr['redcap_repeat_instrument'];
                            $record_data[0]['redcap_repeat_instance'] = $attr['redcap_repeat_instance'];
                        }

                        $params = ['project_id'=>$projectId, 'dataFormat'=>'json', 'data'=>json_encode($record_data)];
                        $response = \REDCap::saveData($params);
                    }
                }
            }
        }
    }

    /**
     * Check if field exists in a form
     *
     * @param int $projectId
     * @param string $form
     * @param string $field
     * @return boolean
     */
    public static function checkFieldExists($projectId, $form, $field) {
        $Proj = new Project($projectId);
        return (isset($Proj->forms[$form]) && isset($Proj->metadata[$field]) && isset($Proj->forms[$form]['fields'][$field]));
    }
}