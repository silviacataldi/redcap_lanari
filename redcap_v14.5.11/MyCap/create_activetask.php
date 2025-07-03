<?php


require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

use Vanderbilt\REDCap\Classes\MyCap;
use Vanderbilt\REDCap\Classes\ProjectDesigner;

$return_status = $msg = $form_name = $note = '';
/**
 * PROCESS SUBMITTED CHANGES
 */
if ($_SERVER['REQUEST_METHOD'] == "POST")
{
    if (isset($_POST['action']) && $_POST['action'] == 'validateActiveTask') {
        $extendedConfigData = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'extendedConfig_') !== false) {
                $key_parts = explode('_', $key);
                list ($key_part, $eventId) = explode("-", $key_parts[1]);
                $extendedConfigData[$eventId][$key_part] = $value;
            }
        }

        $events = array();
        $taskObj = MyCap\ActiveTask::getActiveTaskObj($_POST['question_format']);
        $Proj = new Project();
        $formatted_errors = [];
        foreach ($_POST['tsevent'] as $eventId) {
            $active = (isset($_POST["tsactive-$eventId"]) && $_POST["tsactive-$eventId"] == 'on') ? '1' : '0';
            if ($active == 1) {
                $errors = $taskObj->validateExtendedConfigParams($extendedConfigData[$eventId]);
                if (count($errors) > 0) {
                    $events[] = $Proj->eventInfo[$eventId]['name_ext'];
                    foreach ($errors as $error) {
                        $formatted_errors[] = $error;
                    }
                }
            }
        }
        $events = array_unique($events);
        if ($Proj->longitudinal && count($events) > 0) {
            $note = ' <span style="font-weight: normal;">(Please note, below errors exist for one or more selected events from list: ';
            $note .= implode(", ", $events);
            $note .= ')</span>';
        }
        if (count($formatted_errors) > 0) {
            $formatted_errors = array_unique($formatted_errors);
            foreach ($formatted_errors as $error) {
                $msg .= '<li>'.$error.'</li>';
            }
        }
        $return_status = 'success';

    } else {
        if (!isset($_POST['selected_active_task']) || !isset($_POST['new_form_label'])) exit("0");

        $task_format = $_POST['selected_active_task'];
        $new_form_label = $_POST['new_form_label'];

        $taskObj = MyCap\ActiveTask::getActiveTaskObj($task_format);

        list($created, $form_name) = MyCap\ActiveTask::createREDCapForm($new_form_label);
        $fieldsArr = $taskObj->getFormFields();

        $task_id = ""; // Not defined for some reason

        // Log the event
        Logging::logEvent("", "redcap_mycap_tasks", "MANAGE", $task_id, "task_id = $task_id", "Set up MyCap Active Task\n(Format: ".$task_format.")");
        if ($created) {
            global $Proj, $status;
            if ($status > 0) {
                $Proj->loadMetadataTemp();
            } else {
                $Proj->loadMetadata();
            }
            $projectDesigner = new ProjectDesigner($Proj);
            foreach ($fieldsArr as $field) {
                // Check if $field['field_name'] is unique or not. If no, generate new unique field name
                $field['field_name'] = MyCap\ActiveTask::getNewFieldName($field['field_name']);
                $projectDesigner->createField($form_name, $field);
            }

            $taskObj->buildExtendedConfig();

            $extendedConfigAsString = MyCap\ActiveTask::extendedConfigAsString($taskObj);
            $return = MyCap\ActiveTask::insertDefaultTaskSetting($form_name, $new_form_label, $task_format, $extendedConfigAsString);
            if ($return == true) {
                $return_status = "success";
            }
            if (!$Proj->isRepeatingForm($Proj->firstEventId, $form_name)) {
                // Make this form as repeatable with default eventId as project is classic
                $sql = "INSERT INTO redcap_events_repeat (event_id, form_name) 
			    VALUES ({$Proj->firstEventId}, '".db_escape($form_name)."')";
                db_query($sql);
            }
            // Add 7 new annotations required for MyCap
            $missingAnnotations = MyCap\Task::getMissingAnnotationList($form_name);
            if (count($missingAnnotations) > 0) {
                MyCap\Task::fixMissingAnnotationsIssues($missingAnnotations, $form_name);
            }
        } else {
            $msg = "";
        }
    }
}

// Return message and status
echo json_encode(array(
    'status' => $return_status,
    'message' => $msg,
    'instrument_name' => $form_name,
    'note' => $note
));
