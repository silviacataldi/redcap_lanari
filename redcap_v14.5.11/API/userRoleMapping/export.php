<?php
global $format, $returnFormat;

// Check for required privileges
if ($post['user_rights'] == '0') die(RestUtility::sendResponse(400, $lang['api_229'], $returnFormat));

# get all the records to be exported
$result = Project::getUserRoleRecords();

# structure the output data accordingly
switch($format)
{
	case 'json':
		$content = json_encode($result);
		break;
	case 'xml':
		$content = xml($result);
		break;
	case 'csv':
		$content = (!empty($result)) ? arrayToCsv($result) : 'username,unique_role_name';
		break;
}

/************************** log the event **************************/



# Logging
Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export user role assignments (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function xml($dataset)
{
	$output = '<?xml version="1.0" encoding="UTF-8" ?>';
	$output .= "\n<items>\n";

	foreach ($dataset as $row)
	{
		$line = '';
		foreach ($row as $item => $value)
		{
			if ($value != "")
				$line .= "<$item><![CDATA[" . html_entity_decode($value, ENT_QUOTES) . "]]></$item>";
			else
				$line .= "<$item></$item>";
		}

		$output .= "<item>$line</item>\n";
	}
	$output .= "</items>\n";

	return $output;
}
