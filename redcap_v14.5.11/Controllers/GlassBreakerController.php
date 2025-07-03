<?php

use Vanderbilt\REDCap\Classes\BreakTheGlass\BreakTheGlassTypes;
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;

class GlassBreakerController extends BaseController
{

    /**
     * GlassBreaker instance
     *
     * @var GlassBreaker
     */
    private $glass_breaker;
    
    private $userid;
    
    private $project_id;

    public function __construct()
    {
        $this->userid = $userID = defined('UI_ID') ? UI_ID : null;
        $this->project_id = defined('PROJECT_ID') ? PROJECT_ID : intval(@$_REQUEST['pid']);
        $fhirSystem = FhirSystem::fromProjectId($this->project_id);
        $this->glass_breaker = new GlassBreaker($fhirSystem, $userID);
    }

    /**
     * route, get a list of reasons and legal messages from the epic endpoint
     *
     * @return string json response
     */
    public function getSettings()
    {
        try {
            $response = $this->glass_breaker->getSettings();
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * route, get a list of reasons and legal messages from the epic endpoint
     *
     * @return string json response
     */
    public function initialize()
    {
        try {
            $response = $this->glass_breaker->initialize();
            $this->printJSON($response, $status_code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * route, authorize a break the glass request
     * can only be access via the authentication
     * proxy 'protectedAccept'
     *
     * @return string json response
     */
    public function accept()
    {
        try {
            $this->checkCredentials(); // a REDCap password must be provided
            $request = json_decode(file_get_contents("php://input"), true);
            $mrns = @$request['mrns'];
            $params = $request;
            $response = $this->glass_breaker->accept($this->project_id, $mrns, $params);
            $this->printJSON($response, $code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }


    public function removeMrn()
    {
        try {
            $request = json_decode(file_get_contents("php://input"), true);
            $mrn = @$request['mrn'];
            $this->glass_breaker->removeProtectedPatient($this->project_id, $mrn);
            $response=[
                'message' => 'removed',
                'succes' => true,
            ];
            $this->printJSON($response, $code=200);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }

    /**
     * authentication proxy
     *
     * allow to perform an accept only if the user provides his REDCap password
     * 
     * @return void
     */
    private function checkCredentials()
    {
        // temporarily adjust the POST superglobal and load the DSN
        $setDSN = function($username, $password) {
            $temp = [
                'username' => @$_POST['username'],
                'password' => @$_POST['password'],
            ]; // save reference for restoring the original values
            $_POST['username'] = $username;
            $_POST['password'] = $password;
            Authentication::setDSNs();
            // cleanup
            $_POST['username'] = @$temp['username'];
            $_POST['password'] = @$temp['password'];
            // if null, just delete from the array
            if(is_null($_POST['username'])) unset($_POST['username']);
            if(is_null($_POST['password'])) unset($_POST['password']);
        };

        $request = json_decode(file_get_contents("php://input"), true);
        $password = @$request['password'];
        $userInfo = User::getUserInfoByUiid($this->userid);
		$username = is_array($userInfo) ? @$userInfo['username'] : '';
        $setDSN($username, $password);

        $authenticated = checkUserPassword($username, $password, $authSessionName = "break_the_glass_user_check");
        if(!$authenticated) throw new Exception("wrong REDCap password", 403);
        return true;
    }

    /**
     * Get a list of MRNs that are marked as 'protected'.
     * Such MRNs are protected by the Epic "break the glass"
     * feature and require an "accept" action from the user to
     * be performed.
     *
     * @return void
     */
    public function getProtectedMrnList()
    {
        try {
            $list = $this->glass_breaker->getProtectedMrnList($this->project_id, $this->userid);
            /*  
                //remove comment for DEBUG!
                $list = [
                "adasdas",
                "201688",
                "201833",
                "206925",
            ]; */
            $this->printJSON($list);
        } catch (\Exception $e) {
            $response = [
                'message' => $e->getMessage(),
                'code' => $code = $e->getCode(),
                'success' => $code < 300, // success or not?
            ];
            $this->printJSON($response, $code);
        }
    }



    /* public function clearProtectedMrnList()
    {
        $request_method = $_SERVER['REQUEST_METHOD'];
        if($request_method!=='DELETE') $this->printJSON('method not allowed', 405);
        $cache = new Cache($this->project_id);
        return $cache->empty();
    } */



}