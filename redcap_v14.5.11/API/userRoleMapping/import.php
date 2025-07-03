<?php
global $format, $returnFormat, $post;

// Check for required privileges
if ($post['user_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_171'], $returnFormat));

# put all the records to be imported
$content = putItems();

# Logging
Logging::logEvent("", "redcap_user_rights", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Import user role assignments (API$playground)");

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function putItems()
{
    global $post, $format, $lang;
    $count = 0;
    $errors = array();
    $data = removeBOMfromUTF8($post['data']);

    switch($format)
    {
        case 'json':
            // Decode JSON into array
            $data = json_decode($data, true);
            if ($data == '') return $lang['data_import_tool_200'];
            break;
        case 'xml':
            // Decode XML into array
            $data = Records::xmlDecode(html_entity_decode($data, ENT_QUOTES));
            if ($data == '' || !isset($data['items']['item'])) return $lang['data_import_tool_200'];
            $data = (isset($data['items']['item'][0])) ? $data['items']['item'] : array($data['items']['item']);
            break;
        case 'csv':
            // Decode CSV into array
            $data = str_replace(array('&#10;', '&#13;', '&#13;&#10;'), array("\n", "\r", "\r\n"), $data);
            $data = csvToArray($data);
            break;
    }

    // Begin transaction
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");

    list ($count, $errors) = UserRights::uploadUserRoleMappings(PROJECT_ID, $data);

    if (!empty($errors)) {
        // ERROR: Roll back all changes made and return the error message
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
        die(RestUtility::sendResponse(400, implode("\n", $errors)));
    }

    db_query("COMMIT");
    db_query("SET AUTOCOMMIT=1");

    return $count;
}
