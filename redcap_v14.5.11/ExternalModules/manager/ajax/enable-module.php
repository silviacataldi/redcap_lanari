<?php
namespace ExternalModules;

use Exception;

require_once __DIR__ . '/../../redcap_connect.php';

$prefix = ExternalModules::getPrefixFromPost();

// Can the current user enable/disable modules?
if (!ExternalModules::userCanEnableDisableModule($prefix)) return;

$return_data['message'] = "success";

$pid = ExternalModules::getProjectId();
if (isset($pid)) {
	ExternalModules::enableForProject($prefix, $_POST['version'], $pid);
	if (isset($_GET['request_id'])) {
		if (!ExternalModules::finalizeModuleActivationRequest($prefix, $_POST['version'], $pid, (int)$_GET['request_id'])) {
			$return_data['error_message'] .= ExternalModules::tt("em_manage_90") . "<br/>";
		}
	}
}
else {
    $config = ExternalModules::getConfig($prefix, $_POST['version']); // No need to translate config here.
    $module_name = strip_tags($config["name"]); // No HTML (escaped or otherwise) in error messages.
    $return_data['error_message'] = "";
    if(empty($config['description'])){
        //= The module named '{0}' is missing a description. Fill in the config.json to ENABLE it.
        $return_data['error_message'] .= ExternalModules::tt("em_errors_82", $module_name) . "<br/>";
    }

    if(empty($config['authors'])){
        //= The module named '{0}' is missing its authors. Fill in the config.json to ENABLE it.
        $return_data['error_message'] .= ExternalModules::tt("em_errors_83", $module_name) . "<br/>";
    }else{
        $missingEmail = true;
        foreach ($config['authors'] as $author){
            if(!empty( $author['email'])){
                $missingEmail = false;
                break;
            }
        }

        if($missingEmail){
            //= The module named '{0}' needs at least one email inside the authors portion of the configuration. Please fill an email for at least one author in the config.json.
            $return_data['error_message'] .= ExternalModules::tt("em_errors_84", $module_name) . "<br/>";
        }

        foreach ($config['authors'] as $author) {
            if (empty($author['institution'])) {
                //= The module named '{0}' is missing an institution for at least one of it's authors in the config.json file.
                $return_data['error_message'] .= ExternalModules::tt("em_errors_85", $module_name) . "<br/>";
                break;
            }
        }
    }

    if(empty($return_data['error_message'])) {
		$exception = ExternalModules::enableAndCatchExceptions($prefix, $_POST['version']);
		if($exception){
            //= Exception while enabling module: {0}
			$return_data['error_message'] = ExternalModules::tt("em_errors_86", $exception->getMessage());
			$return_data['stack_trace'] = htmlentities($exception->getTraceAsString(), ENT_QUOTES);
		}
    }
}

// Log this event
$logText = "Enable external module \"{$prefix}_{$_POST['version']}\" for " . (!empty($pid) ? "project" : "system");
\REDCap::logEvent($logText, "", "", null, null, $pid);

echo json_encode($return_data);
