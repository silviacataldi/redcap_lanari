<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// If no survey id, assume it's the first form and retrieve
if (!isset($_GET['survey_id'])) $_GET['survey_id'] = Survey::getSurveyId();

// Obtain current event_id
$_GET['event_id'] = getEventId();

// Ensure the survey_id belongs to this project and that Post method was used
if (!(isset($_GET['survey_id']) && Survey::checkSurveyProject($_GET['survey_id']) && isset($_GET['event_id'])
	&& isset($Proj->eventInfo[$_GET['event_id']])) || $_SERVER['REQUEST_METHOD'] != "POST") {
	exit("0");
}

// Instantiate scheduler object
$surveyScheduler = new SurveyScheduler();

// Initialize vars
$popupContent = $popupTitle = "";
$response = $isRepeatInstrument = "0";

## DISPLAY DIALOG CONTENT
if (isset($_POST['action']) && $_POST['action'] == 'view')
{
	// Obtain the HTML for the setup table
	$popupContent = $surveyScheduler->renderConditionalInviteSetupTable($_GET['survey_id'], $_GET['event_id']);
	// Set dialog title
	$popupTitle = '<i class="fas fa-mail-bulk fs15 me-2"></i>' . $lang['survey_337'];
	// Set response as successful
	$response = "1";
}

## SAVE SCHEDULE
elseif (isset($_POST['action']) && $_POST['action'] == 'save')
{
	// Decode posted values
	foreach ($_POST as $key=>$val) $_POST[$key] = html_entity_decode($val, ENT_QUOTES);

    $isRepeatInstrument = $Proj->isRepeatingForm($_GET['event_id'], $Proj->surveys[$_GET['survey_id']]['form_name']) ? "1" : "0";

    // Get existing values for Step 2 of the ASI (if it already exists)
    $existingASIvaluesChanged = false;
    $sql = "select condition_surveycomplete_survey_id, condition_surveycomplete_event_id, condition_andor, condition_logic, reeval_before_send 
			from redcap_surveys_scheduler 
            where survey_id = {$_GET['survey_id']} and event_id = {$_GET['event_id']}
            limit 1";
    $q = db_query($sql);
    if ($q && db_num_rows($q) > 0) {
        $existingASIvalues = db_fetch_assoc($q);
        foreach ($existingASIvalues as $key=>$val) {
            if (!isset($_POST[$key])) continue;
            if (trim($_POST[$key]."") !== trim($val."")) {
                $existingASIvaluesChanged = true;
            }
        }
    }

	// Get the delivery type - default to EMAIL
	$delivery_methods = Survey::getDeliveryMethods(true);
	if (!($twilio_enabled && $Proj->twilio_enabled_surveys) || !isset($_POST['delivery_type']) || !isset($delivery_methods[$_POST['delivery_type']])) {
		$_POST['delivery_type'] = 'EMAIL';
	}

	// Convert exact datetime format to Y-M-D H:M
	if ($_POST['condition_send_time_exact'] != '') {
		list ($this_date, $this_time) = explode(" ", $_POST['condition_send_time_exact']);
		$_POST['condition_send_time_exact'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
	}

	// Check email address
	$_POST['email_sender'] = label_decode($_POST['email_sender']);
	if (!isEmail($_POST['email_sender'])) $_POST['email_sender'] = "";

	// Check if reminders are set
	if (isset($_POST['reminder_type']) && $_POST['reminder_type'] != '') {
		// Convert exact datetime format to Y-M-D H:M
		if ($_POST['reminder_exact_time'] != '') {
			list ($this_date, $this_time) = explode(" ", $_POST['reminder_exact_time']);
			$_POST['reminder_exact_time'] = trim(DateTimeRC::format_ts_to_ymd($this_date) . " $this_time:00");
		}
	} else {
		// No reminders
		$_POST['reminder_num'] = '0';
		$_POST['reminder_type'] = $_POST['reminder_timelag_days'] = $_POST['reminder_timelag_hours'] = '';
		$_POST['reminder_timelag_minutes'] = $_POST['reminder_nextday_type'] = $_POST['reminder_nexttime'] = $_POST['reminder_exact_time'] = '';
	}

	$_POST['reeval_before_send'] = ($_POST['reeval_before_send'] == '1') ? '1' : '0';
	$_POST['max_recurrence'] = $_POST['max_recurrence'] ?? "";
	$_POST['num_recurrence'] = $_POST['num_recurrence'] ?? "0";
	$_POST['units_recurrence'] = $_POST['units_recurrence'] ?? "DAYS";
    if (!isinteger($_POST['max_recurrence'])|| $_POST['num_recurrence'] < 0) $_POST['max_recurrence'] = "";
    if (!is_numeric($_POST['num_recurrence']) || $_POST['num_recurrence'] < 0) $_POST['num_recurrence'] = "0";
    if (!in_array($_POST['units_recurrence'], ["DAYS", "MINUTES", "HOURS"])) $_POST['units_recurrence'] = "DAYS";

	// Insert into table (or update table if already exists)
	$sql = "insert into redcap_surveys_scheduler
			(survey_id, event_id, max_recurrence, num_recurrence, units_recurrence, email_subject, email_content, email_sender, condition_send_time_exact,
			condition_surveycomplete_survey_id, condition_surveycomplete_event_id, condition_logic,
			condition_send_time_option, condition_send_next_day_type, condition_send_next_time,
			condition_send_time_lag_days, condition_send_time_lag_hours, condition_send_time_lag_minutes, condition_send_time_lag_field, condition_send_time_lag_field_after, condition_andor, active,
			reminder_num, reminder_type, reminder_timelag_days, reminder_timelag_hours,
			reminder_timelag_minutes, reminder_nextday_type, reminder_nexttime, reminder_exact_time, delivery_type, reeval_before_send, email_sender_display)
			values
			({$_GET['survey_id']}, {$_GET['event_id']}, ".checkNull($_POST['max_recurrence']).", ".checkNull($_POST['num_recurrence']).", ".checkNull($_POST['units_recurrence']).", 
			".checkNull($_POST['email_subject']).", ".checkNull($_POST['email_content']).", ".checkNull($_POST['email_sender']).", ".checkNull($_POST['condition_send_time_exact']).",
			".checkNull($_POST['condition_surveycomplete_survey_id']).", ".checkNull($_POST['condition_surveycomplete_event_id']).", ".checkNull($_POST['condition_logic']).",
			".checkNull($_POST['condition_send_time_option']).", ".checkNull($_POST['condition_send_next_day_type']).", ".checkNull($_POST['condition_send_next_time']).",
			".checkNull($_POST['condition_send_time_lag_days']).", ".checkNull($_POST['condition_send_time_lag_hours']).", ".checkNull($_POST['condition_send_time_lag_minutes']).",
			".checkNull($_POST['condition_send_time_lag_field']).",".checkNull($_POST['condition_send_time_lag_field_after']).",".checkNull($_POST['condition_andor']).", ".checkNull($_POST['active']).",
			".checkNull($_POST['reminder_num']).", ".checkNull($_POST['reminder_type']).", ".checkNull($_POST['reminder_timelag_days']).",
			".checkNull($_POST['reminder_timelag_hours']).", ".checkNull($_POST['reminder_timelag_minutes']).",
			".checkNull($_POST['reminder_nextday_type']).", ".checkNull($_POST['reminder_nexttime']).", ".checkNull($_POST['reminder_exact_time']).",
			".checkNull($_POST['delivery_type']).", {$_POST['reeval_before_send']}, ".checkNull($_POST['email_sender_display']).")
			on duplicate key update
			email_subject = ".checkNull($_POST['email_subject']).", email_content = ".checkNull($_POST['email_content']).", email_sender = ".checkNull($_POST['email_sender']).",
			condition_send_time_exact = ".checkNull($_POST['condition_send_time_exact']).", condition_surveycomplete_survey_id = ".checkNull($_POST['condition_surveycomplete_survey_id']).",
			condition_surveycomplete_event_id = ".checkNull($_POST['condition_surveycomplete_event_id']).", condition_logic = ".checkNull($_POST['condition_logic']).",
			condition_send_time_option = ".checkNull($_POST['condition_send_time_option']).", condition_send_next_day_type = ".checkNull($_POST['condition_send_next_day_type']).",
			condition_send_next_time = ".checkNull($_POST['condition_send_next_time']).", condition_send_time_lag_days = ".checkNull($_POST['condition_send_time_lag_days']).",
			condition_send_time_lag_hours = ".checkNull($_POST['condition_send_time_lag_hours']).", condition_send_time_lag_minutes = ".checkNull($_POST['condition_send_time_lag_minutes']).",
			condition_andor = ".checkNull($_POST['condition_andor']).", active = ".checkNull($_POST['active']).",
			reminder_num = ".checkNull($_POST['reminder_num']).", reminder_type = ".checkNull($_POST['reminder_type']).", reminder_timelag_days = ".checkNull($_POST['reminder_timelag_days']).", reminder_timelag_hours = ".checkNull($_POST['reminder_timelag_hours']).",
			reminder_timelag_minutes = ".checkNull($_POST['reminder_timelag_minutes']).", condition_send_time_lag_field = ".checkNull($_POST['condition_send_time_lag_field']).", 
			condition_send_time_lag_field_after = ".checkNull($_POST['condition_send_time_lag_field_after']).", 
			reminder_nextday_type = ".checkNull($_POST['reminder_nextday_type']).",
			reminder_nexttime = ".checkNull($_POST['reminder_nexttime']).", reminder_exact_time = ".checkNull($_POST['reminder_exact_time']).",
			delivery_type = ".checkNull($_POST['delivery_type']).", reeval_before_send = {$_POST['reeval_before_send']}, email_sender_display = ".checkNull($_POST['email_sender_display']).",
			max_recurrence = ".checkNull($_POST['max_recurrence']).",
			num_recurrence = ".checkNull($_POST['num_recurrence']).",
			units_recurrence = ".checkNull($_POST['units_recurrence'])."
			";
	if (db_query($sql))
	{
		// Set successful response
		$response = "1";

		// Logging
		$existingSchedule = (db_affected_rows() != 1);
		$logDescrip = ($existingSchedule) ? "Edit settings for automated survey invitations" : "Add settings for automated survey invitations";
		Logging::logEvent($sql,"redcap_surveys_scheduler","MANAGE",$project_id,"survey_id = {$_GET['survey_id']}\nevent_id = {$_GET['event_id']}",$logDescrip);

		// Now set output that will be returned and displayed in a dialog as confirmation
		$popupTitle = $lang['survey_408'];
		$popupContent = RCView::div(array('style'=>'color:green;'),
							RCView::img(array('src'=>'tick.png')) .
							$lang['survey_409'] .
							" <b>\"" . $Proj->surveys[$_GET['survey_id']]['title'] . "\" " .
							(!$longitudinal ? '' : ' - ' . $Proj->eventInfo[$_GET['event_id']]['name_ext']) . "</b>" .
							$lang['period'].
							// If any values in Step 2 changed (assuming an existing ASI), then tell user to re-eval the ASI
							(!($existingASIvaluesChanged && Records::getRecordCount(PROJECT_ID) > 0) ? "" :
								RCView::div(array('class'=>'yellow mt-3'),
									RCView::b('<i class="fas fa-exclamation-triangle"></i> '.$lang['global_03'].$lang['colon'])." ".$lang['asi_060']
								)
							)
						);
	}
}


// Send back JSON response
header('Content-Type: application/json');
print json_encode_rc(array('response'=>$response, 'popupContent'=>$popupContent, 'popupTitle'=>$popupTitle));
