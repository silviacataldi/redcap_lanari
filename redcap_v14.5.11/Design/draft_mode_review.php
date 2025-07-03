<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// First, in case someone held down the Enter button on the last page to send them here, which may send multiple requests,
// let's make sure that their changes weren't just approved automatically just a second ago.
if ($auto_prod_changes > 0)
{
	// Get the last action made by the current user and see if it was an auto-approval request change
	$oneMinAgo = (date('YmdHis')-100);
	$sql = "select description from ".Logging::getLogEventTable($project_id)." where ts > $oneMinAgo and project_id = $project_id
			and user = '".db_escape($userid)."' and event = 'MANAGE' order by log_event_id desc limit 1";
	$q = db_query($sql);
	if (db_num_rows($q)) {
		if (db_result($q, 0) == 'Approve production project modifications (automatic)') {
			// Auto-approval just occurred, so don't try it again. Redirect back to Online Designer. Nothing to do here.
			redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id&msg=autochangessaved");
		}
	}
}


// Quick check: Make sure that NO entry exists in prod revisions table where ts_approved is NULL for this project
$sql = "select count(1) from redcap_metadata_prod_revisions where project_id = $project_id and ts_approved is null";
$already_done = db_result(db_query($sql), 0);


// Make sure it also has draft mode = 2, otherwise user can't ever submit for review
if ($draft_mode == 0 || ($already_done && $draft_mode != 2))
{
	db_query("delete from redcap_metadata_prod_revisions where project_id = $project_id and ts_approved is null");
}
// Process it
elseif (!$already_done)
{
	//Get user's ui_id from user info table
	$sql = "select ui_id from redcap_user_information where username = '$userid'";
	$ui_id = db_result(db_query($sql), 0);

	//Add new entry to metadata production revisions table to log this requested metadata change
	$q = db_query("insert into redcap_metadata_prod_revisions (project_id, ui_id_requester, ts_req_approval) values ($project_id, $ui_id, '".NOW."')");

	if ($q)
	{
		//Now set draft_mode to "2" and send email to REDCap Administrator to approve these changes
		$sql = "update redcap_projects set draft_mode = 2 where project_id = $project_id";
		$q = db_query($sql);

		## AUTO PRODUCTION CHANGES CHECK
		// If auto production changes have been enabled, then check to see what changes have been made in order to
		// determine if changes can be made now automatically.
		if ($auto_prod_changes > 0)
		{
			// Get list of metadata changes, count of records, and other items
			list ($num_records, $fields_added, $field_deleted, $field_with_data_deleted, $count_new, $count_existing) = Design::renderCountFieldsAddDel2();
			list ($num_metadata_changes, $num_fields_changed, $record_id_field_changed, $num_critical_issues, $metadataDiffTable, $num_econsent_form_issues) = Design::getMetadataDiff($num_records);
			$newFieldsIdentifierKeywordMatch = ($auto_prod_changes_check_identifiers == '1') ? IdentifierCheck::getNewFieldsMatchingKeywords($project_id, $status) : array();
			// See if auto changes can be made
			if (
				defined("AUTOMATE_ALL")
				// If the ONLY changes are that new fields were added
				|| ($auto_prod_changes == '2' && $num_fields_changed == 0 && $field_deleted == 0 && $num_critical_issues == 0 && empty($newFieldsIdentifierKeywordMatch))
				// If the ONLY changes are that new fields were added OR if there is no data
				|| ($auto_prod_changes == '3' && ($num_records == 0 || ($num_fields_changed == 0 && $field_deleted == 0 && $num_critical_issues == 0)) && empty($newFieldsIdentifierKeywordMatch))
				// OR if there are no critical issues AND no fields deleted (regardless of whether or not project has data)
				|| ($auto_prod_changes == '4' && $field_with_data_deleted == 0 && $num_critical_issues == 0 && empty($newFieldsIdentifierKeywordMatch))
				// OR if there are (no critical issues AND no fields deleted) OR if there is no data
				|| ($auto_prod_changes == '1' && ($num_records == 0 || ($field_with_data_deleted == 0 && $num_critical_issues == 0)) && empty($newFieldsIdentifierKeywordMatch))
			) {
				## Auto changes can be done, so redirect to approve script in order to approve it automatically
				// Set secret unique token so that users cannot simply bypass to the next script
				redirect(APP_PATH_WEBROOT . "Design/draft_mode_approve.php?pid=$project_id&auto_change_token=".sha1($__SALT__)."&now=".urlencode(NOW));
			}
		}

		// Logging
		if ($q) {
			Logging::logEvent($sql,"redcap_projects","MANAGE",$project_id,"project_id = $project_id","Request approval for production project modifications");
		}
		
		// create todo in redcap_todo_list
		$db = new RedCapDB();
		$userInfo = $db->getUserInfoByUsername($userid);
		$action_url = APP_PATH_WEBROOT_FULL.'redcap_v'.$redcap_version.'/Design/project_modifications.php?pid='.$project_id;
		ToDoList::insertAction($userInfo->ui_id, $project_contact_email, "draft changes", $action_url, $project_id);

		//Email the Approval Person (but not if current user is a super user, because they can approve the changes themselves - cuts down on email confusion)
		if (!$super_user)
		{
			//Get user info for email
			$q = db_query("select u.user_firstname, u.user_lastname, u.user_email from redcap_metadata_prod_revisions r, redcap_projects p,
							  redcap_user_information u where p.project_id = r.project_id and p.project_id = $project_id and r.ts_approved is null
							  and u.ui_id = r.ui_id_requester");
			$srow = db_fetch_array($q);
			//Set up email and send
			$email = new Message();
			$emailContents = '
				<html>
				<body style="font-family:arial,helvetica;">
				'.$lang['global_21'].'<br /><br />
				'.$lang['draft_mode_21'].' "'.$app_title.'".
				'.$lang['draft_mode_22'].'<br /><br />
				<a href="'.APP_PATH_WEBROOT_FULL.'redcap_v'.$redcap_version.'/Design/project_modifications.php?pid='.$project_id.'">'.$lang['draft_mode_28'].' "'.$app_title.'"</a><br /><br />
				'.$lang['draft_mode_24'].' '.$srow['user_firstname'].' '.$srow['user_lastname'].' (<a href="mailto:'.$srow['user_email'].'">'.$srow['user_email'].'</a>).
				</body>
				</html>';
			$email->setTo($project_contact_email);
			$email->setFrom($srow['user_email']);
			$email->setFromName($srow['user_firstname'].' '.$srow['user_lastname']);
			$email->setSubject('[REDCap] ' . $lang['draft_mode_29'] . ' (PID '.$project_id.')');
			$email->setBody($emailContents);
			If ($send_emails_admin_tasks) {
				if (!$email->send()) {
					print "<div style='width:600px;font-size:13px;'><b><u>{$lang['draft_mode_06']} $project_contact_email:</u></b><br><br>";
					exit($emailContents);
				}
			}
		}

	}

}

redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=" . $project_id);
