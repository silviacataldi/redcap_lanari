<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Increase memory limit in case needed for intensive processing
System::increaseMemory(2048);

// Get opening XML tags
$xml = ODM::getOdmOpeningTag($app_title);
// MetadataVersion section
$xml .= ODM::getOdmMetadata($Proj, false, false, $_GET['xml_metadata_options']);
// End XML string
$xml .= ODM::getOdmClosingTag();

// Log this download
$descr = "Download REDCap project XML file (metadata only)";
Logging::logEvent("","redcap_projects","MANAGE",$project_id,"",$descr);

// Return XML string
$today_hm = date("Y-m-d_Hi");
$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20);
header('Pragma: anytextexeptno-cache', true);
header("Content-Type: application/xml");
header("Content-Disposition: attachment; filename=\"{$projTitleShort}_{$today_hm}.REDCap.xml\"");
print $xml;