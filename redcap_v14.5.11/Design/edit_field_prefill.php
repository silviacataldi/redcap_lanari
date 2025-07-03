<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

// To remove certain characters from html strings to use in javascript
$orig = array(	"\x0d\x0d", "< =");
$repl = array(		"\n\n", "<=" );
$repl_label = array("\n\n", "<=" );

// Put all info for this field into array $ques
$sql = "select * from $metadata_table where project_id = $project_id and field_name = '".db_escape($_GET['field_name'])."' limit 1";
$q = db_query($sql);
if (!db_num_rows($q)) exit($isAjax ? "alert(woops);" : "ERROR!");
$ques = db_fetch_assoc($q);
foreach ($ques as &$val) {
	if ($val === null) $val = "";
}

// Make sure advcheckbox fields are not "required" (since "unchecked" is technically a real value)
if ($ques['element_type'] == "advcheckbox") {
	$ques['field_req'] = "0";
}

// Add "_ymd" to end of legacy date validation names so that they correspond with values from validation table
if ($ques['element_type'] == "text" && ($ques['element_validation_type'] == "date" || $ques['element_validation_type'] == "datetime" || $ques['element_validation_type'] == "datetime_seconds")) {
	$ques['element_validation_type'] .= "_ymd";
}
// Make sure min and max are in YMD format when coming from the db table. If not, reformat here.
if ($ques['element_type'] == "text" && substr($ques['element_validation_type'], 0, 4) == "date")
{
	if (substr_count($ques['element_validation_min'], "/") == 2) {
		$ques['element_validation_min'] = DateTimeRC::date_mdy2ymd(str_replace("/", "-", $ques['element_validation_min']));
	}
	if (substr_count($ques['element_validation_max'], "/") == 2) {
		$ques['element_validation_max'] = DateTimeRC::date_mdy2ymd(str_replace("/", "-", $ques['element_validation_max']));
	}
}
// If a text field with any kind of date validation with min/max range set, reformat min/max data value from YMD to native format when displaying
$isMDY = (substr($ques['element_validation_type'], -4) == "_mdy");
$isDMY = (substr($ques['element_validation_type'], -4) == "_dmy");
if ($ques['element_type'] == "text" && ($isMDY || $isDMY))
{
	if ($ques['element_validation_min'] != "" && $ques['element_validation_min'] != "now" && $ques['element_validation_min'] != "today" && !(substr($ques['element_validation_min'], 0, 1) == "[" && substr($ques['element_validation_min'], -1) == "]")) {
		if ($isMDY) {
			$ques['element_validation_min'] = DateTimeRC::datetimeConvert($ques['element_validation_min'], 'ymd', 'mdy');
		} else {
			$ques['element_validation_min'] = DateTimeRC::datetimeConvert($ques['element_validation_min'], 'ymd', 'dmy');
		}
	}
	if ($ques['element_validation_max'] != "" && $ques['element_validation_max'] != "now" && $ques['element_validation_max'] != "today" && !(substr($ques['element_validation_max'], 0, 1) == "[" && substr($ques['element_validation_max'], -1) == "]")) {
		if ($isMDY) {
			$ques['element_validation_max'] = DateTimeRC::datetimeConvert($ques['element_validation_max'], 'ymd', 'mdy');
		} else {
			$ques['element_validation_max'] = DateTimeRC::datetimeConvert($ques['element_validation_max'], 'ymd', 'dmy');
		}
	}
}

// SQL Field: Do extra server-side check to ensure that only super users can add/edit "sql" field types
if ($ques['element_type'] == 'sql' && !$super_user)
{
	// Send back JS error msg
	exit("alert('".js_escape($lang['design_272'])."');");
}



// Get the matrix/grid name of this field's adjacent fields (in order to populate Matrix group drop-down)
$quesAdj = array();
$sql = "select distinct grid_name from $metadata_table where project_id = $project_id and
		field_order >= ".($ques['field_order']-1)." and field_order <= ".($ques['field_order']+1)."
		and grid_name is not null and grid_name != '' and field_name != '{$_GET['field_name']}' order by field_order";
$q = db_query($sql);
while ($row = db_fetch_assoc($q)) {
	$quesAdj[] = $row['grid_name'];
}


// Basic fields used to populate the Add/Edit Field form
$fields = array('field_name', 'element_type', 'element_label', 'field_req', 'element_enum', 'element_note', 'element_validation_type',
				'element_validation_min', 'element_validation_max', 'field_phi', 'element_preceding_header',
				'edoc_id', 'edoc_display_img', 'custom_alignment', 'question_num', 'grid_name', 'misc', 'video_url', 'video_display_inline');

// Array of field names that we need to loop through to build JSON array
foreach ($fields as $field) {
	// Remove all formatting
	$this_string = label_decode($ques[$field]);
	// Replace characters and line breaks for labels and section headers
	if ($field == 'element_label' || $field == 'element_preceding_header' || $field == 'misc') {
		$this_string = replaceUrlOldRedcapVersion(str_replace($orig, $repl_label, $this_string));
	} else {
		$this_string = str_replace($orig, $repl, $this_string);
	}
	if ($field == 'element_enum') {
		$this_string = str_replace('\n', "\n", $this_string);
		$this_string = str_replace(" \n ", "\n", $this_string);
	}
	// Set value for this field attribute
	$ques[$field] = $this_string;
	// If an MC field, add coded enum values
	if ($field == 'element_enum') {
		$ques['existing_enum'] = implode("|", array_keys(parseEnum($this_string)));
	}
}
// If an image/file attachment field, add filename here
if ($ques['element_type'] == 'descriptive' && is_numeric($ques['edoc_id']))
{
	// Get filename and output it
	$q = db_query("select doc_name from redcap_edocs_metadata where project_id = $project_id and delete_date is null and doc_id = " . $ques['edoc_id']);
	$ques['attach_download_link'] = db_result($q, 0);
	$ques['edoc_id_hash'] = Files::docIdHash($ques['edoc_id']);
}
// If a slider field, set labels separately
if ($ques['element_type'] == 'slider')
{
	$slider_labels = Form::parseSliderLabels($ques['element_enum']);
	// Output each label
	$ques['slider_label_left'] = decode_filter_tags($slider_labels['left']);
	$ques['slider_label_middle'] = decode_filter_tags($slider_labels['middle']);
	$ques['slider_label_right'] = decode_filter_tags($slider_labels['right']);
}
// Add adjacent matrix/grid names
$ques['adjacent_grid_names'] = implode(",", $quesAdj);
// Output JSON
header("Content-Type: application/json");
print json_encode_rc($ques);