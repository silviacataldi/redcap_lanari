<?php
use Vanderbilt\REDCap\Classes\MyCap\MyCap;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
// Initialize vars
$popupContent = $popupTitle = "";

## RENDER DIALOG CONTENT FOR SETTING UP EVENT DISPLAY FORMAT
if (isset($_POST['action']) && $_POST['action'] == "view")
{
    // Response
    $popupTitle = "<i class=\"fas fa-user-tag\"></i> ".RCView::tt('mycap_mobile_app_853');
    $popupContent = MyCap::displayCustomSetupTable();
}


## SAVE CONDITIONS SETTINGS
elseif (isset($_POST['action']) && $_POST['action'] == "save")
{
    $event_display_format = $_POST['event_display_format'];
    // Update event display format for project
    $sql = "UPDATE redcap_mycap_projects SET
                    event_display_format = '".db_escape($event_display_format)."'
                WHERE project_id = ".PROJECT_ID;
    if (db_query($sql))
    {
        // Log the event
        Logging::logEvent($sql, "redcap_mycap_projects", "MANAGE", PROJECT_ID, "project_id = ".PROJECT_ID, "Edit Event display format (App-side)");
        $msg = 'updated';
    }
    // Response
    $popupTitle = RCView::tt('design_243');
    $popupContent = RCView::img(array('src'=>'tick.png')) . RCView::span(array('style'=>"color:green;"), RCView::tt('mycap_mobile_app_854'));

    print json_encode_rc(array('content'=>$popupContent, 'title'=>$popupTitle));
    exit;
}

// Send back JSON response
print json_encode_rc(array('content'=>$popupContent, 'title'=>$popupTitle));