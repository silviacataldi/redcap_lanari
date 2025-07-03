<?php


# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;
$primaryKey = $Proj->table_pk;
$edoc_upload_max = $Proj->project['edoc_upload_max'];

$project_id = $post['projectid'];
$record = $post['record'];
$fieldName = $post['field'];
$eventName = $post['event'];
$eventId = "";

# check to see if a file was uploaded
if (count($_FILES) == 0) RestUtility::sendResponse(400, "No valid file was uploaded");

# make sure there were no errors associated with the uploaded file
if ($_FILES['file']['error'] != 0) RestUtility::sendResponse(400, "There was a problem with the uploaded file");

// Prevent data writes for projects in inactive or archived status
if ($Proj->project['status'] > 1) {
	if ($Proj->project['status'] == '2') {
		$statusLabel = "Analysis/Cleanup";
	} else {
		$statusLabel = "[unknown]";
	}
	die(RestUtility::sendResponse(403, "The file cannot be uploaded because the project is in $statusLabel status."));
}

# get file information
$fileData = $_FILES['file'];

# if the project is longitudinal, check the event that was passed in and get the id associated with it
if ($longitudinal)
{
	if ($eventName != "") {
		$event = Event::getEventIdByKey($project_id, array($eventName));

		if (count($event) > 0 && $event[0] != "") {
			$eventId = $event[0];
		}
		else {
			RestUtility::sendResponse(400, "invalid event");
		}
	}
	else {
		RestUtility::sendResponse(400, "invalid event");
	}
}
else
{
	$eventId = $Proj->firstEventId;
}

// If project has repeating forms/events, then use the repeat_instance
$form_name = $Proj->metadata[$fieldName]['form_name'];
$instance = (isset($post['repeat_instance']) && is_numeric($post['repeat_instance']) && $post['repeat_instance'] > 0) ? $post['repeat_instance'] : 1;
$isRepeatingForm = $Proj->isRepeatingForm($eventId, $form_name);
if (!$isRepeatingForm && !($Proj->longitudinal && $Proj->isRepeatingEvent($eventId))) {
	$instance = 1;
}

## LOCKING CHECK
// Is whole record locked?
$locking = new Locking();
$wholeRecordIsLocked = $locking->isWholeRecordLocked($project_id, $record, $Proj->eventInfo[$eventId]['arm_num']);
if ($wholeRecordIsLocked) {
	RestUtility::sendResponse(400, "The uploaded file could not be saved because record \"".RCView::escape($record)."\" is locked.");
}
// Is this record/event/form/instance locked?
$locking->findLocked($Proj, $record, array($fieldName), $eventId);
$formIsLocked = isset($locking->locked[$record][$eventId][$instance][$fieldName]);
if ($formIsLocked) {
	RestUtility::sendResponse(400, "The uploaded file could not be saved because the field's instrument is locked for record \"".RCView::escape($record)."\".");
}

$docName = str_replace("'", "", html_entity_decode(stripslashes($fileData['name']), ENT_QUOTES));
$docSize = $fileData['size'];

# Check if file is larger than max file upload limit
if (($docSize/1024/1024) > maxUploadSizeEdoc() || $fileData['error'] != UPLOAD_ERR_OK) {
	RestUtility::sendResponse(400, "The uploaded file exceeded the maximum file size limit of ".maxUploadSizeEdoc()." MB for File Upload fields.");
}

# Upload the file and return the doc_id from the edocs table
$docId = Files::uploadFile($fileData);

// If not an allowed file extension, then prevent uploading the file and return "0" to denote error
if ($docId == 0 && !Files::fileTypeAllowed($docName)) {
    RestUtility::sendResponse(400, $lang['docs_1136']);
}

# Update tables if file was successfully uploaded
if ($docId != 0)
{
	# check to make sure the record exists
	$sql = "SELECT 1
			FROM ".\Records::getDataTable($project_id)."
			WHERE project_id = $project_id
				AND record = '".db_escape($record)."'
				LIMIT 1";
	$result = db_query($sql);
	if (db_num_rows($result) == 0) {
		RestUtility::sendResponse(400, "The record '$record' does not exist. It must exist to upload a file");
	}

	# determine if the field exists in the metadata table and if of type 'file'
	$sql = "SELECT 1
			FROM redcap_metadata
			WHERE project_id = $project_id
				AND field_name = '".db_escape($fieldName)."'
				AND element_type = 'file'";
	$metadataResult = db_query($sql);
	if (db_num_rows($metadataResult) == 0) {
		RestUtility::sendResponse(400, "The field '".RCView::escape($fieldName)."' does not exist or is not a 'file' field");
	}

	// If this 'file' field is a Signature field type, then prevent uploading it because signatures
	// can only be created in the web interface.
	if ($Proj->metadata[$fieldName]['element_validation_type'] == 'signature' && $post['mobile_app'] != '1') {
		RestUtility::sendResponse(400, "The field '$fieldName' is a signature field, which cannot be imported using the API but can only be created using the web interface. However, it can be downloaded or deleted using the API.");
	}

	# Now see if field has had a previous value. If so, update; if not, insert.
	$sql = "SELECT value
		FROM ".\Records::getDataTable($project_id)."
		WHERE project_id = $project_id
			AND record = '".db_escape($record)."'
			AND event_id = $eventId
			AND field_name = '".db_escape($fieldName)."'";
	$sql .= $instance > 1 ? " AND instance = '".db_escape($instance)."'" : " AND instance is NULL";
	$result = db_query($sql);

	if (db_num_rows($result) > 0) // row exists
	{
		# Set the file as "deleted" in redcap_edocs_metadata table, but don't really delete the file or the table entry (unless the File Version History is enabled for the project)
		if (!Files::fileUploadVersionHistoryEnabledProject(PROJECT_ID)) {
			$id = db_result($result, 0, 0);
            if (isinteger($id)) {
                $sql = "UPDATE redcap_edocs_metadata SET delete_date = '" . NOW . "' WHERE doc_id = $id";
                db_query($sql);
            }
		}

		$sql = "UPDATE ".\Records::getDataTable($project_id)."
				SET value = '$docId'
				WHERE project_id = $project_id
					AND record = '".db_escape($record)."'
					AND event_id = $eventId
					AND field_name = '".db_escape($fieldName)."'";
		$sql .= $instance > 1 ? " AND instance = '".db_escape($instance)."'" : " AND instance is NULL";
		db_query($sql);
	}
	else // row did not exist
	{
		// If this is a longitudinal project and this file is being added to an event without data,
		// then add a row for the record ID field too (so it doesn't get orphaned).
		if ($Proj->longitudinal) {
			$sql = "SELECT 1
					FROM ".\Records::getDataTable($project_id)."
					WHERE project_id = $project_id
						AND record = '".db_escape($record)."'
						AND event_id = $eventId";
			$sql .= $instance > 1 ? " AND instance = '".db_escape($instance)."'" : " AND instance is NULL";
			$sql .= " LIMIT 1";
			$result = db_query($sql);
			if (db_num_rows($result) == 0) {
				$sql = "INSERT INTO ".\Records::getDataTable($project_id)." (project_id, event_id, record, field_name, value, instance)
						VALUES ($project_id, $eventId, '".db_escape($record)."', '{$Proj->table_pk}', '".db_escape($record)."', ".($instance > 1 ? "'".db_escape($instance)."'" : "null").")";
				db_query($sql);
			}
		}
		// Add edoc_id to data table
		$sql = "INSERT INTO ".\Records::getDataTable($project_id)." (project_id, event_id, record, field_name, value, instance)
				VALUES ($project_id, $eventId, '".db_escape($record)."', '$fieldName', '$docId', ".($instance > 1 ? "'".db_escape($instance)."'" : "null").")";
		db_query($sql);
	}

	// If the File Upload field's instrument is a repeating instrument, then make sure there is a value stored for the form status field
	if ($isRepeatingForm)
	{
		$sql = "select 1 from ".\Records::getDataTable($project_id)." where project_id = $project_id and event_id = $eventId and record = '".db_escape($record)."' 
				and instance ".($instance > 1 ? "= '".db_escape($instance)."'" : "is null")." and field_name = '".db_escape($form_name."_complete")."'";
		$formCompleteValueExists = db_num_rows(db_query($sql));
		if (!$formCompleteValueExists)
		{
			$sql = "INSERT INTO ".\Records::getDataTable($project_id)." (project_id, event_id, record, field_name, value, instance)
                    VALUES ($project_id, $eventId, '".db_escape($record)."', '".db_escape($form_name."_complete")."', '0', ".($instance > 1 ? "'".db_escape($instance)."'" : "null").")";
			db_query($sql);
		}
	}

	# Log file upload
	$_GET['event_id'] = $eventId; // Set event_id for logging purposes only
	Logging::logEvent($sql,"redcap_data","doc_upload",$record,"$fieldName = '$docId'","Upload document (API$playground)",
		"", "", "", true, null, $instance);
}
else {
	RestUtility::sendResponse(400, "A problem occurred while trying to save the uploaded file");
}

# Send the response to the requester
RestUtility::sendResponse(200);
