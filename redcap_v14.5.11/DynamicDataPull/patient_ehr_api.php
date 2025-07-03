<?php
namespace Vanderbilt\REDCap\DynamicDataPull;

use Exception;
use Vanderbilt\REDCap\Classes\SessionData;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirPatientPortal;

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

function flash($key, $value) {
    return SessionData::getInstance()->flash($key, $value);
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $portal = new FhirPatientPortal();
        $user_id = defined('UI_ID') ? UI_ID : null;
        $action = $_POST['action'] ?? null;
        switch ($action) {
            case 'remove-project':
                $project_id = $_POST['pid'] ?? null;
                $success = $portal->removeEhrProject($project_id, $user_id);
                if($success) flash('alert-success', 'project was removed');
                else throw new Exception("project was NOT removed", 1);
                break;
            
            case 'add-project':
                $project_id = $_POST['pid'] ?? null;
                $success = $portal->addEhrProject($project_id, $user_id);
                if($success) flash('alert-success', 'project was added');
                else throw new Exception("project was NOT added", 1);
                break;
            
            case 'create-patient-record':
                $project_id = $_POST['pid'] ?? null;
                $mrn = $_POST['mrn'] ?? null;
                $record = $_POST['record'] ?? null;
                $html = $portal->createPatientRecord($project_id, $mrn, $record);
                flash('alert-success', $html);
                break;
            
            default:
                break;
        }
    } catch (\Throwable $th) {
        flash('alert-danger', $th->getMessage());
    } finally {
        $ehrPage = APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . '/ehr.php';
        $previous_page = $_SERVER['HTTP_REFERER'];
        $parsed_url = parse_url($previous_page);
        $path = $parsed_url['path'] ?? '';
        // make sure the previous page is ehr.php
        if (preg_match('/\/ehr\.php$/', $path)!==1) {
            $previous_page = $ehrPage;
        }
        redirect($previous_page);
    }
}

echo 'Nothing to see here';