<?php

use Vanderbilt\REDCap\Classes\BreakTheGlass\BreakTheGlassTypes;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystemManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirCustomMetadataService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\AutoAdjudication\AutoAdjudicator;

class CdisController extends BaseController
{


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * route, get a list of revisions
     *
     * @return string json response
     */
    public function test()
    {
        $response = array('test' => 123);
        $this->printJSON($response, $status_code=200);
    }

    /**
     * get the logs
     *
     * @return void
     */
    public function getCdpAutoAdjudicationLogs()
    {
        try {
            $project_id = $_GET['pid'];
            $start = intval($_GET['_start']) ?: 0;
            $limit = intval($_GET['_limit']) ?: 0;

            $logs = AutoAdjudicator::getLogsForProject($project_id, $start, $limit);
            $this->printJSON($logs, $status_code=200);
        } catch (\Exception $e) {
            //throw $th;
            $error = new JsonError(
                $title = 'error ritrieving logs',
                $detail = sprintf("There was a problem retrieving the logs for project ID %s", $project_id),
                $status = 400,
                $source = PAGE // get the current page
            );
            $this->printJSON($error, $status_code=400);
        }
    }

    /**
     * display a page with auto-adjudication logs
     *
     * @return void
     */
    public function showLogsPage()
    {
        global $lang, $project_id;
        extract($GLOBALS);
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        $blade = Renderer::getBlade();
        try {
            $logs = AutoAdjudicator::getLogsForProject($project_id);
            $blade->share('logs', $logs);
        } catch (\Exception $e) {
            $blade->share('error', $e->getMessage());
        }
        $view_variables = array();
        print $blade->run('cdp.auto-adjudication.logs', $view_variables);
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    }

    public function getSettings() {
        global $lang;
        $fhirSystemManager = new FhirSystemManager();
        $customMetadataService = new FhirCustomMetadataService();
        $response = [
            'lang' => $lang,
            'redcapConfig' => $fhirSystemManager->getSharedSettings(),
            'breakTheGlassUserTypes' => BreakTheGlassTypes::userTypes(),
            'fhirSystems' => $fhirSystemManager->getFhirSystems(),
            'redirectURL' => $fhirSystemManager->getRedirectURL(),
            'customMappingsData' => [
                'list' => $customMetadataService->getData(),
                'validCategories' => $customMetadataService->getValidCategories(),
            ]
        ];
        $this->printJSON($response);
    }

    /**
     * save settings that are common to each EHR system
     *
     * @return void
     */
    public function saveSettings() {
        $data = $this->getPhpInput();
        $settings = $data['settings'] ?? [];
        $fhirSystemManager = new FhirSystemManager();
        $totalUpdated = $fhirSystemManager->saveSharedSettings($settings);
        try {
            if(empty($errors)) {
                $this->printJSON([
                    'status' => 'success',
                    "message" => "Settings saved successfully"
                ]);
            }else {
                $this->printJSON([
                    'status' => 'error',
                    'message' => 'Invalid input provided',
                    'errors' => $errors
                ], 400);
            }
        } catch (\Throwable $th) {
            $this->printJSON([
                'status' => 'error',
                'message' => 'Failed to save settings',
                'errors' => [$th->getMessage()],
            ],$th->getCode() );
        }

    }

    public function upsertFhirSettings() {
        try {
            $data = $this->getPhpInput();
            $settings = $data['settings'] ?? null;
            $fhirSystemManager = new FhirSystemManager();
            $result = $fhirSystemManager->upsertFhirSystem($settings);
            $this->printJSON([
                'status' => 'success',
                "message" => "Order updated successfully"
            ]);
        } catch (\Throwable $th) {
            $this->printJSON([
                'status' => 'error',
                'message' => $th->getMessage(),
            ],$th->getCode() );
        }
    }

    public function deleteFhirSystem() {
        try {
            $ehr_id = $_GET['ehr_id'] ?? null;
            $fhirSystemManager = new FhirSystemManager();
            $fhirSystemManager->deleteFhirSystem($ehr_id);
            $this->printJSON([
                'status' => 'success',
                "message" => "FHIR System deleted"
            ]);
        } catch (\Throwable $th) {
            $this->printJSON([
                'status' => 'error',
                'message' => $th->getMessage(),
            ], $th->getCode());
        }
    }

    public function updateFhirSystemsOrder() {
        try {
            $data = $this->getPhpInput();
            $order = $data['order'] ?? null;
            $fhirSystemManager = new FhirSystemManager();
            $totalUpdated = $fhirSystemManager->updateFhirSystemsOrder($order, $errors);
            if(empty($errors)) {
                $this->printJSON([
                    'status' => 'success',
                    "message" => "Order updated successfully"
                ]);
            }else {
                $this->printJSON([
                    'status' => 'error',
                    'message' => 'Invalid input provided',
                    'errors' => $errors
                ], 400);
            }
        } catch (\Throwable $th) {
            $this->printJSON([
                'status' => 'error',
                'message' => 'Failed to update order',
                'errors' => [$th->getMessage()],
            ],$th->getCode() );
        }
    }

    public function removeCustomMapping() {
        $data = json_decode(file_get_contents('php://input'), true);
        $customMetadataService = new FhirCustomMetadataService();
        $removed = $customMetadataService->removeCustomMapping();
        $response = [];
        $code = 200;
        if($removed) {
            $response['message'] = "The custom mapping file was removed successfully";
            $response['success'] = true;
            
        } else {
            $response['message'] = "The custom mapping file was NOT removed";
            $response['success'] = false;
            $code = 400;
        }
        $this->printJSON($response, $code);
    }
    
    public function saveCustomMapping() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Unauthorized", 401);
            $customMetadataService = new FhirCustomMetadataService();
            $data = json_decode(file_get_contents('php://input'), true);
            $entries = $data['customMapping'] ?? [];
            $valid = $customMetadataService->validateCustomMapping($entries);
            if(!$valid) throw new Exception("Uploaded file is not valid", 400);
            $edocID = $customMetadataService->uploadCustomMapping($entries);
            $response = [
                'message' => "The custom mapping file was uploaded successfully (ID: $edocID)",
                'success' => true,
            ];
            return $this->printJSON($response);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'success' => false,
            ];
            $this->printJSON($response, $th->getCode());
        }
    }
    
    public function uploadCustomMapping() {
        /* try {
            if (($_SERVER['REQUEST_METHOD'] !== 'POST') || !(ACCESS_SYSTEM_CONFIG)) throw new Exception("Unauthorized", 401);
            if(isset($_FILES["file"]) && is_uploaded_file($_FILES["file"]["tmp_name"]) && $_FILES["file"]["error"] == UPLOAD_ERR_OK) {
                $fileData = $_FILES["file"];
                $customMetadataService = new FhirCustomMetadataService();
                $valid = $customMetadataService->validateCustomMapping($fileData["tmp_name"]);
                if(!$valid) throw new Exception("Uploaded file is not valid", 400);
                
                $edocID = $customMetadataService->uploadCustomMapping($fileData);
                $response = [
                    'message' => "The custom mapping file was uploaded successfully (ID: $edocID)",
                    'success' => true,
                ];
                return $this->printJSON($response);
            }
            throw new Exception("Wrong format", 400);
        } catch (\Throwable $th) {
            $response = [
                'message' => $th->getMessage(),
                'success' => false,
            ];
            $this->printJSON($response, $th->getCode());
        } */
    }

    public function downloadCustomMapping() {
        $customMetadataService = new FhirCustomMetadataService();
        $customMetadataService->downloadCurrentCustomMapping();
        exit;
    }

    public function downloadCustomMappingTemplate() {
        $customMetadataService = new FhirCustomMetadataService();
        $customMetadataService->downloadTemplate();
        exit;
    }

}