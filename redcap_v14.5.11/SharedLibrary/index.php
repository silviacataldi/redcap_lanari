<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Instructions for downloading/uploading instruments
if ($_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_GET['page']))
{
	// Flag to avoid duplicate loading is reset here for the user to load a form
	$_SESSION['import_id'] = '';
	// Redirect to Online Designer
	redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id");

}



// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";


// Check for cURL first
if (!function_exists('curl_init'))
{
	//cURL is not loaded
	curlNotLoadedMsg();
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;

}

if (isset($_POST['library_id']) && !is_numeric($_POST['library_id'])) exit('ERROR!');
if (isset($_POST['library_id'])) $_POST['library_id'] = (int)$_POST['library_id'];

// Check if any notices need to be displayed regarding Draft Mode (exclude if sharing a form while in Production)
if ($status < 1 || ($status > 0 && !isset($_GET['page']) && !isset($_POST['form_submitted'])))
{
	include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";
}



## SHARE/UPLOAD FORM TO LIBRARY
if (isset($_POST['form_submitted']))
{
	$error = false;
	$project = $_POST['project'];
	$form = $_POST['form'];
	if (!isset($Proj->forms[$form])) exit;

    print  "<div class='round' style='border:1px solid #99B5B7;background-color:#E8ECF0;max-width:700px;margin:20px 0;'>";
	print  "<h4 style='padding:5px 10px;margin:0;border-bottom:1px solid #ccc;color:#222;'>
				<img src='".APP_PATH_IMAGES."blog_pencil.png'>
				{$lang['shared_library_02']}
			</h4>";
	print  "<div style='padding:5px 10px 5px 30px;width:600'>";

	try {
		if ($_POST['submit'] == 'Remove') {
			$params = array(
				'institution'=>$_POST['institution'],
				'user_key'=>$_POST['user_key'],
				'user_fname'=>$_POST['user_fname'],
				'user_lname'=>$_POST['user_lname'],
				'user_email'=>$_POST['user_email'],
				'contact_fname'=>$_POST['contact_fname'],
				'contact_lname'=>$_POST['contact_lname'],
				'contact_email'=>$_POST['contact_email'],
				'remove_reason'=>$_POST['remove_reason'],
				'replace_option'=>'remove',
				'library_id'=>$_POST['library_id']
			);
		} else {
			$fields = SharedLibrary::getCrfFormData($form);
			$libxml = new SharedLibraryXml();
			$xmlString = $libxml->createXML('crf',$fields);
			// print_array($fields);
			// print htmlspecialchars($xmlString, ENT_QUOTES);
			// include 'ProjectGeneral/footer.php';
			// exit;
			$xmlFileName = dirname(APP_PATH_DOCROOT) . DS . "temp" . DS
						 . date('YmdHis') . "_library_" . substr(sha1(rand()), 0, 6) . ".xml";
			$xmlFileHandler = fopen($xmlFileName, 'w');
			fwrite($xmlFileHandler, $xmlString);
			fclose($xmlFileHandler);


			//exit(htmlspecialchars($xmlString, ENT_QUOTES));

			$params = array(
				'xmlFile'=>(function_exists('curl_file_create') ? curl_file_create($xmlFileName) : "@$xmlFileName"),
				'title'=>$_POST['title'],
				'description'=>$_POST['description'],
				'form_menu_description'=>$_POST['form_menu_description'],
				'keywords'=>$_POST['keywords'],
				'acknowledgement'=>$_POST['acknowledgement'],
				'institution'=>$_POST['institution'],
				'termsofuse'=>$_POST['termsofuse'],
				'servertype'=>$_POST['servertype'],
				'version'=>$_POST['version'],
				'user_key'=>$_POST['user_key'],
				'user_fname'=>$_POST['user_fname'],
				'user_lname'=>$_POST['user_lname'],
				'user_email'=>$_POST['user_email'],
				'contact_fname'=>$_POST['contact_fname'],
				'contact_lname'=>$_POST['contact_lname'],
				'contact_email'=>$_POST['contact_email'],
				'share_permission'=>$_POST['share_permission'],
				'public_access'=>$_POST['public_access'],
				//'associations'=>serialize($_POST['associations']),
				'replace_option'=>$_POST['replace_option'],
				'library_id'=>$_POST['library_id'],
				'server_name'=>SERVER_NAME,
				'language'=>$_POST['language']
			);
		}

		//echo '<br>Initializing curl';
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		curl_setopt($curl, CURLOPT_URL, SHARED_LIB_UPLOAD_URL);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
		curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		//print "<br>Sending to... ".SHARED_LIB_UPLOAD_URL;
		$response = curl_exec($curl);

		//print "<br>Post: <pre>";print_r($_POST);print "</pre>";
		//echo '<br>Response: '.$response.'<br>';

		if(preg_match('/^error:/',$response) > 0) {
			print "<p>".$response."</p>";
		    $error = true;
		}else if(preg_match('/^success:/',$response) > 0) {
			$start = strpos($response,";id[")+4;
			$end = strpos($response,"]",$start);
			$upload_id = substr($response,$start,$end-$start);
            $upload_id = (int)$upload_id;
			$start = strpos($response,";ts[")+4;
			$end = strpos($response,"]",$start);
			$upload_ts = substr($response,$start,$end-$start);

			if($_POST['submit'] == 'Remove') {
				//remove the mapping for this form, since it was removed from the library
				$removeSql = "delete from redcap_library_map where type = 2 and library_id = ".$_POST['library_id'];
				db_query($removeSql);
			}

			if ($_POST['replace_option'] == 'new') {
				//mark the form as uploaded to the library
				$checkSql = "select * from redcap_library_map where project_id = $project_id and form_name = '$form' and type = 2 and library_id = $upload_id";
				$checkQuery = db_query($checkSql);
				if(db_num_rows($checkQuery) > 0) {
					$shareSql = "update redcap_library_map " .
							"set upload_timestamp = '$upload_ts' " .
							"where project_id = $project_id and form_name = '$form' and type = 2 and library_id = $upload_id";
				}else {
					$shareSql = "insert into redcap_library_map" .
							"(project_id, form_name, type, library_id, upload_timestamp) " .
							"values($project_id,'$form',2,$upload_id,'$upload_ts')";
				}
				db_query($shareSql);
			}

		}
		curl_close($curl);
		unlink($xmlFileName);




		## Upload any field attachments
		if ($_POST['submit'] != 'Remove')
		{
			// Get list of all doc_id's for all project attachments
			$sqlImages = "select e.doc_id from redcap_edocs_metadata e, redcap_metadata m
						  where m.project_id = $project_id and m.form_name = '$form' and m.project_id = e.project_id
						  and m.edoc_id = e.doc_id and e.delete_date is null";
			$resultImages = db_query($sqlImages);
			$imageList = "";
			$delim = "";
			while ($rowImages = db_fetch_assoc($resultImages))
			{
				$imageList = $imageList . $delim . $rowImages['doc_id'];
				$delim = ",";
			}
			// If has any attachments, upload them now
			if (!empty($imageList))
			{
				// error_log('launching attachment uploader');
				//need to get unique id from response
				$params = array('library_id'=>$upload_id, 'imageList'=>$imageList);
				// print_array($params);
				// exit;
				$imgCurl = curl_init();
				curl_setopt($imgCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
				curl_setopt($imgCurl, CURLOPT_VERBOSE, 0);
				curl_setopt($imgCurl, CURLOPT_URL, APP_PATH_WEBROOT_FULL . "redcap_v{$redcap_version}/SharedLibrary/image_loader.php");
				curl_setopt($imgCurl, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($imgCurl, CURLOPT_POST, true);
				curl_setopt($imgCurl, CURLOPT_TIMEOUT, 1000);
				curl_setopt($imgCurl, CURLOPT_POSTFIELDS, $params);
				curl_setopt($imgCurl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
				curl_setopt($imgCurl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
				$response = curl_exec($imgCurl);
				// error_log($response);
				curl_close($imgCurl);
				//
				// error_log('finished launching attachment uploader');
			}
		}

	} catch(Exception $e) {
	   echo '<p>'.$e->getMessage().'<p>';
	}

	$verb = $lang['shared_library_03'];
	$note_lag_time = "<span style='font-weight:normal;'>{$lang['shared_library_61']}</span>";
	if ($_POST['submit'] == "Remove") {
		$verb = $lang['shared_library_04'];
		$note_lag_time = "";
	} elseif ($_POST['replace_option'] == "replace") {
		$verb = $lang['shared_library_05'];
		$note_lag_time = "";
	}

	// Give user notification of success/failure to uploade to library
	if ($error) {
	    print  "<p style='font-weight:bold;color:red;'>
					<img src='" . APP_PATH_IMAGES . "cross.png'>
					{$lang['shared_library_06']} $verb {$lang['shared_library_07']}{$lang['period']}
				</p>";
	}else {
	    print  "<p style='font-weight:bold;color:green;'>
					<img src='" . APP_PATH_IMAGES . "tick.png'>
					{$lang['shared_library_08']} $verb {$lang['shared_library_07']}{$lang['period']}
					$note_lag_time
				</p>";
	}

	// Give user link back to Design page
	// renderPrevPageLink("Design/online_designer.php");

	print "</div>";

}






## FORM TO FILL OUT BEFORE UPLOADIGN TO LIBRARY
else if(isset($_GET['page']) && isset($Proj->forms[$_GET['page']]))
{
	$user_key = md5($institution.$userid);
	/*
	// Get list of institutions user is listed under in library
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_VERBOSE, 0);
	curl_setopt($curl, CURLOPT_URL, SHARED_LIB_DOWNLOAD_URL.'?attr=institutions');
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_POST, false);
	curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
	$response = curl_exec($curl);
	$institutions = explode('<br>',$response);
	// Get list of instruments user has uploaded to library
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_VERBOSE, 0);
	curl_setopt($curl, CURLOPT_URL, SHARED_LIB_DOWNLOAD_URL.'?attr=instruments&id='.$user_key);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_POST, false);
	curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
	$response = curl_exec($curl);
	$otherInstruments = explode('<br>',$response);
	*/

	//determine if the form was downloaded or uploaded previously
	$shareSql = "select type, library_id from redcap_library_map where project_id = $project_id and form_name = '" . $_GET['page'] . "'";
	$shareResult = db_query($shareSql);
	$prevUpload = false;
	$prevDownload = false;
	$library_id = -1;
	while ($share = db_fetch_array($shareResult)) {
		if ($share['type'] == 1) {
			$prevDownload = true;
		} elseif ($share['type'] == 2) {
			$prevUpload = true;
			$library_id = $share['library_id'];
		}
	}


	// Page header
	renderPageTitle("<div>
						<img src='".APP_PATH_IMAGES."blogs_arrow.png'>
						{$lang['shared_library_09']}
					 </div>");
	?>

	<style type='text/css'>
	.labelrc, .data {
		background:#F0F0F0 url('<?php print APP_PATH_IMAGES ?>label-bg.gif') repeat-x scroll 0 0;
		border:1px solid #CCCCCC;
		font-size:12px;
		font-weight:bold;
		
		padding:5px 10px;
	}
	.labelrc { width: 250px; }
	.form_border { width: 100%;	}
	</style>

	<p>
		<?php echo $lang['shared_library_95'] ?>
	</p>

	<?php
	// Final reminders about what RSL is and isn't
	print 	RCView::div(array('class'=>'yellow', 'style'=>'margin-top:25px;'),
				RCView::div(array('style'=>'font-weight:bold;margin-bottom:10px;'),
					RCView::img(array('src'=>'stopsign.gif')) .
					$lang['shared_library_75']
				) .
				RCView::div(array('style'=>''), $lang['shared_library_76']) .
				RCView::div(array('style'=>'color:#C00000;margin:15px 0 10px 25px;'),
					RCView::div(array('style'=>'margin-bottom:6px;text-indent:-10px;'), ' &bull; ' . $lang['shared_library_94']) .
					RCView::div(array('style'=>'margin-bottom:6px;text-indent:-10px;'), ' &bull; ' . $lang['shared_library_79']) .
					RCView::div(array('style'=>'margin-bottom:6px;text-indent:-10px;'),
						' &bull; ' . $lang['shared_library_78'] . ' ' .
						RCView::a(array('href'=>'javascript:;', 'onclick'=>"simpleDialog(null,null,'librarySuggestDlg',700);"), $lang['shared_library_82']) . ' ' .
						$lang['shared_library_83']
					) .
					RCView::div(array('style'=>'text-indent:-10px;'), ' &bull; ' . $lang['shared_library_80'])
				) .
				RCView::div(array('style'=>'margin:15px 0 5px;'),
					$lang['shared_library_81'] . ' ' .
					RCView::a(array('href'=>'mailto:redcap@vumc.org&subject=REDCap Shared Library question'), 'redcap@vumc.org') .
					$lang['period']
				)
			);
	?>

    <!-- Dialog -->
    <div id="librarySuggestDlg" class="simpleDialog" title="REDCap Shared Library - Recommendation for Inclusion of Validated Instrument">
        <p>We are happy that you have a recommendation for the REDCap Shared Library. The library continues to grow based on the suggestions that you provide. Before an instrument is added, it goes through a review process by the REDCap Library Oversight Committee. The following criteria are used to prioritize additions to the library.</p>
        <ul>
            <li>terms of use (public domain? free to researchers? copyright issues?)</li>
            <li>demand for instrument (highly used, related domains)</li>
            <li>size of instrument (higher priority given to smaller instruments)</li>
            <li>evidence of validation (instruments will be rejected without evidence of validation)</li>
        </ul>
        <p>You will need to provide the following information in order to start the review process.</p>
        <ul>
            <li>instrument name (REQUIRED)</li>
            <li>evidence of validation (document or web link) (REQUIRED)</li>
            <li>copy of the actual instrument (PDF or Doc) (REQUIRED)</li>
            <li>your contact information (REQUIRED)</li>
        </ul>
        <p>In addition to these required pieces of information, please provide the following information if at all possible.</p>
        <ul>
            <li>version number (if applicable)</li>
            <li>link to informational website (if available)</li>
            <li>journal or other citation for the instrument</li>
            <li>copy of related journal article (if available)</li>
            <li>copy of the data dictionary or zip file (if available)</li>
            <li>terms of use</li>
            <li>author's name</li>
            <li>author's email address (if available)</li>
            <li>publisher's contact information</li>
            <li>special instructions or notes for REDLOC</li>
        </ul>
        <p>If you have questions about any of these requirements, please contact <a href="mailto:redcap@vumc.org"><u>redcap@vumc.org</u></a>.
            Otherwise, please continue to the <a href="https://redcap.vumc.org/surveys/?s=9MHM3RDYN7" target="_blank"><u>submission survey</u></a> to add your instrument to the queue
            if you have the information needed to start the process.</p>
    </div>

	<!-- Button to bring up the license agreement -->
	<div id="openLicAgreementBtn" class="darkgreen" style="margin-top:30px;">
		<?php echo $lang['shared_library_70'] ?>
		<div style='text-align:center;padding:5px;margin-top:10px;margin-bottom:5px;'>
			<button class="btn btn-sm btn-defaultrc" onclick="openLicAgreement()"><?php echo $lang['shared_library_71'] ?></button>
		</div>
	</div>



	<?php

	// Get the form's menu name to pre-fill Title field
	$sql = "select form_menu_description from redcap_metadata where project_id = $project_id and form_name = '{$_GET['page']}'
			and form_menu_description is not null order by field_order limit 1";
	$form_menu = RCView::escape(db_result(db_query($sql), 0));

	// Give note to user if previously uploaded or downloaded this form
	if ($prevUpload || $prevDownload)
	{
		$updown_text = ($prevUpload ? $lang['shared_library_11'] : $lang['shared_library_12']);
		$replacecopy_text = ($prevUpload ? $lang['shared_library_13'] : $lang['shared_library_14']);
		print  "<div id='replace_remove_box' class='yellow' style='display:none;'>
					<b>{$lang['global_02']}: {$lang['shared_library_15']} $updown_text {$lang['shared_library_07']}</b>.
					{$lang['shared_library_16']} $replacecopy_text {$lang['shared_library_17']}</u></b>.
					{$lang['shared_library_18']} ";
		if ($prevUpload)
		{
			// Display the REMOVE button if previously uploaded it
			print  "<form method='post' action='" . PAGE_FULL . "?pid=$project_id'>
						{$lang['shared_library_19']}<br/><br/>
						{$lang['shared_library_20']}<br/>
						<input type='submit' id='remove_btn' name='submit' value='Remove' style='font-size: 11px; vertical-align: middle;' onclick=\"
							if ( $('#remove_reason').val().length < 1 || $('#remove_reason_div').css('display') == 'none') {
								$('#remove_reason_div').show();
								alert('".js_escape($lang['shared_library_72'])."');
								return false;
							} else {
								return confirm('".js_escape($lang['shared_library_21'])."\\n\\n".js_escape($lang['shared_library_22'])."');
							}
						\">
						{$lang['shared_library_23']}
						<input type='hidden' name='library_id' value='$library_id'/>
						<input type='hidden' name='form_submitted' value='true'/>
						<input type='hidden' name='institution' value='".str_replace('\'', '&quot;', $institution)."'/>
						<input type='hidden' name='user_key' value='$user_key'/>
						<div id='remove_reason_div' class='blue' style='display:none;margin:10px 0;'>
							<div>
								<b>{$lang['shared_library_73']}</b><br>{$lang['shared_library_74']}
							</div>
							<textarea id='remove_reason' name='remove_reason' style='font-size:12px;width:500px;height:100px;'></textarea><br>
							<input type='button' value='Remove' style='font-size: 11px; vertical-align: middle;' onclick=\" $('#remove_btn').click();\">
						</div>
					</form>";
		}
		print "</div>";
	}

	?>
	<br/><br/>
	<form name="shareForm" onsubmit="return validateLibUploadForm();" method="post" action="<?php print PAGE_FULL . "?pid=$project_id" ?>">
	<table id="instr_upload_form" class="form_border" style="display:none;max-width:700px;width:680px;">
		<tr>
			<td class="header" colspan="2"><?php echo $lang['shared_library_24'] ?> "<font class="notranslate" color="#800000"><?php print $form_menu ?></font>"</td>
		</tr>
		<tr>
			<td class="labelrc">
				<?php echo $lang['shared_library_25'] ?>
				&nbsp;<span style="font-size:11px;color:red;font-weight:normal;">* <?php echo $lang['api_docs_063'] ?></span>
				<div style="margin-top:3px;font-size:11px;color:#666;font-weight:normal;"><?php echo $lang['shared_library_64'] ?></div>
			</td>
			<td class="data notranslate">
				<input class="x-form-text x-form-field" type="text" style="width:301px" name="title" value="<?php print $form_menu ?>"/>
				<input type="hidden" name="form_menu_description" value="<?php print $form_menu ?>"/>
			</td>
		</tr>
		<tr>
			<td class="labelrc">
				<?php echo $lang['global_20'] ?>
				&nbsp;<span style="font-size:11px;color:red;font-weight:normal;">* <?php echo $lang['api_docs_063'] ?></span>
				<div style="margin-top:3px;font-size:11px;color:#666;font-weight:normal;"><?php echo $lang['shared_library_63'] ?></div>

			</td>
			<td class="data"><textarea class="x-form-textarea x-form-field" style="height:100px;width:95%;font-size:12px;" name="description"></textarea></td>
		</tr>
		<tr>
			<td class="labelrc">
				<?php echo $lang['shared_library_27'] ?>
				<div style="margin-top:3px;font-size:11px;color:#666;font-weight:normal;"><?php echo $lang['shared_library_65'] ?></div>
			</td>
			<td class="data"><input class="x-form-text x-form-field" type="text" style="width:95%" name="keywords"/></td>
		</tr>
		<tr>
			<td class="labelrc">
				<?php echo $lang['shared_library_28'] ?>
				<div style="margin-top:3px;font-size:11px;color:#666;font-weight:normal;"><?php echo $lang['shared_library_67'] ?></div>
			</td>
			<td class="data"><textarea class="x-form-textarea x-form-field" style="height:100px;width:95%;font-size:12px;" name="acknowledgement"></textarea></td>
		</tr>
		<tr>
			<td class="labelrc">
				<?php echo $lang['shared_library_29'] ?>
				<div style="margin-top:3px;font-size:11px;color:#666;font-weight:normal;"><?php echo $lang['shared_library_66'] ?></div>
			</td>
			<td class="data"><textarea class="x-form-textarea x-form-field" style="height:100px;width:95%;font-size:12px;" name="termsofuse"></textarea></td>
		</tr>
		<tr>
			<td class="labelrc">
				<?php echo $lang['shared_library_87'] ?>
			</td>
			<td class="data">
				<?php echo SharedLibraryXml::renderLanguageDropdown('en', false, '"max-width:95%;') ?>
			</td>
		</tr>
		<tr>
			<td class="header" colspan="2"><?php echo $lang['shared_library_30'] ?></td>
		</tr>
		<tr>
			<td class="labelrc"><?php echo $lang['global_41'] ?> &nbsp;<span style="font-size:11px;color:red;font-weight:normal;">* <?php echo $lang['api_docs_063'] ?></span></td>
			<td class="data"><input class="x-form-text x-form-field" type="text" style="width:301px" name="contact_fname" value="<?php print RCView::escape($user_firstname)?>"/></td>
		</tr>
		<tr>
			<td class="labelrc"><?php echo $lang['global_42'] ?> &nbsp;<span style="font-size:11px;color:red;font-weight:normal;">* <?php echo $lang['api_docs_063'] ?></span></td>
			<td class="data"><input class="x-form-text x-form-field" type="text" style="width:301px" name="contact_lname" value="<?php print RCView::escape($user_lastname)?>"/></td>
		</tr>
		<tr>
			<td class="labelrc"><?php echo $lang['global_33'] ?> &nbsp;<span style="font-size:11px;color:red;font-weight:normal;">* <?php echo $lang['api_docs_063'] ?></span></td>
			<td class="data"><input class="x-form-text x-form-field" type="text" style="width:301px" name="contact_email" value="<?php print RCView::escape($user_email)?>" onblur="redcap_validate(this,'','','hard','email')"/></td>
		</tr>
		<tr>
			<td class="header" colspan="2"><?php echo $lang['shared_library_34'] ?></td>
		</tr>
		<tr>
			<td class="labelrc"><?php echo $lang['shared_library_35'] ?></td>
			<td class="data">
				<input type="radio" name="share_permission" value="consortium" checked="yes">
				<?php echo $lang['shared_library_36'] ?><br>
				<input type="radio" name="share_permission" value="institution">
				<?php echo $lang['shared_library_38'] ?> <?php print $institution ?><br>
			</td>
		</tr>
		<!--
		<tr>
			<td class="labelrc">
				<?php echo $lang['shared_library_39'] ?><br>
				<?php echo $lang['shared_library_40'] ?><br>
				<span style='font-weight:normal;font-size:11px;'>
					<?php echo $lang['global_02'] ?>: <?php echo $lang['shared_library_41'] ?>
				</span>
			</td>
			<td class="data">
				<input type="radio" name="public_access" value="view" checked="yes"> <?php echo $lang['shared_library_42'] ?><br>
				<input type="radio" name="public_access" value="none"> <?php echo $lang['shared_library_43'] ?><br>
			</td>
		</tr>
		-->
		<tr>
			<td class="labelrc"></td>
			<td class="data">
				<div class="mt-2"><input type="submit" name="submit" style="font-size: 15px;" value="<?php echo RCView::escape($lang['data_entry_211']) ?>"/></div>
				<div class="mt-4 mb-2"><input type="button" value="<?php echo RCView::escape($lang['data_entry_207']) ?>" onclick="window.location.href=app_path_webroot+page+'?pid='+pid"/></div>
			</td>
		</tr>
	</table>

	<input type="hidden" name="public_access" value="view">
	<input type="hidden" name="form_submitted" value="true"/>
	<input type="hidden" name="project" value="<?php print $_GET['pnid']?>"/>
	<input type="hidden" name="form" value="<?php print $_GET['page']?>"/>
	<input type="hidden" name="servertype" value="Form"/>
	<input type="hidden" name="version" value="<?php print $redcap_version?>"/>
	<input type="hidden" name="institution" value="<?php print str_replace("\"", "&quot;", $institution) ?>"/>
	<input type="hidden" name="user_key" value="<?php print $user_key?>"/>
	<input type="hidden" name="user_lname" value="<?php print RCView::escape($user_lastname)?>"/>
	<input type="hidden" name="user_fname" value="<?php print RCView::escape($user_firstname)?>"/>
	<input type="hidden" name="user_email" value="<?php print RCView::escape($user_email)?>"/>

	<?php
	if ($prevUpload) {
		//not currently allowing duplicate copies of a form on the library, once an instrument
		//is uploaded, it can only be removed or replaced.
		print "<input type='hidden' name='library_id' value='$library_id'/>";
		print "<input type='hidden' name='replace_option' value='replace'/>";
	}else {
		print "<input type='hidden' name='replace_option' value='new'/>";
	}
	?>
	</form>

	<br><br><br>

	<!-- Div and javascript to display license agreement -->
	<div id="licAgreement" style="display:none;"><?php include APP_PATH_DOCROOT . 'SharedLibrary/upload_license_agreement.php'; ?></div>
	<div id="licAgreementConfirm" class="simpleDialog wrap" title="<?=RCView::tt_js2('survey_369')?>">
        <p class="mt-0 mb-3 fs14"><?=RCView::tt('shared_library_99')?></p>
        <div class="ml-3 mb-2 fs14"><input type="checkbox" id="licAgr1"><label style="display:inline;margin-left:3px;cursor:pointer;" for="licAgr1"><?=RCView::tt('shared_library_100',"")?></label></div>
        <div class="ml-3 mb-2 fs14"><input type="checkbox" id="licAgr2"><label style="display:inline;margin-left:3px;cursor:pointer;" for="licAgr2"><?=RCView::tt('shared_library_101',"")?></label></div>
        <div class="ml-3 mb-2 fs14"><input type="checkbox" id="licAgr3"><label style="display:inline;margin-left:3px;cursor:pointer;" for="licAgr3"><?=RCView::tt('shared_library_102',"")?></label></div>
    </div>
	<script type="text/javascript">
    $(function(){
      $('#licAgreementConfirm input[type=checkbox]').click(function(){
        var allChecked = ($('#licAgreementConfirm input[type=checkbox]:checked').length == 3);
        $('#licAgreementConfirm').parent().find('.ui-dialog-buttonpane button:eq(1)').button(allChecked ? 'enable' : 'disable');
      });
    });
	function validateLibUploadForm() {
		document.shareForm.description.value = trim(document.shareForm.description.value);
		document.shareForm.title.value = trim(document.shareForm.title.value);
		document.shareForm.contact_fname.value = trim(document.shareForm.contact_fname.value);
		document.shareForm.contact_lname.value = trim(document.shareForm.contact_lname.value);
		document.shareForm.contact_email.value = trim(document.shareForm.contact_email.value);
		if (document.shareForm.description.value.length < 1 ||
			document.shareForm.title.value.length < 1 ||
			document.shareForm.contact_fname.length < 1 ||
			document.shareForm.contact_lname.length < 1 ||
			document.shareForm.contact_email.length < 1) {
			simpleDialog('<?=RCView::tt_js('shared_library_96')?>');
			return false;
		}
        // Confirmation dialog
        simpleDialog(null,null,'licAgreementConfirm',800,null,'<?=RCView::tt_js('shared_library_103')?>',function(){
            // Submit form
            showProgress(1);
            $('form[name=shareForm]').removeAttr('onsubmit');
            $('form[name=shareForm] :input[name=submit]').trigger('click');
        },'<?=RCView::tt_js('shared_library_104')?>');
        // Disable Yes button by default
        $('#licAgreementConfirm').parent().find('.ui-dialog-buttonpane button:eq(1)').button('disable');
        return false;
	}
	function openLicAgreement() {
		$('#licAgreement').dialog({ bgiframe: true, modal: true, width: 700, height: 600, title: 'Shared Content Agreement', open: function(){fitDialog(this);}, buttons: {
			'<?=RCView::tt_js('shared_library_97')?>': function() {
				$(this).dialog('close');
			},
			'<?=RCView::tt_js('shared_library_98')?>': function() {
				// Show the form
				$('#instr_upload_form, #replace_remove_box').show();
				$('#openLicAgreementBtn').hide();
				$(this).dialog('destroy');
			}
		} });
	}
	</script>
	<?php
}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
