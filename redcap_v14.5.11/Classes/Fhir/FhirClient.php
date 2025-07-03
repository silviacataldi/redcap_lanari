<?php
namespace Vanderbilt\REDCap\Classes\Fhir;

use Exception;
use GuzzleHttp\Exception\ClientException;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Traits\SubjectTrait;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\FhirRequest;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirToken;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Bundle;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4\ResearchStudy;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpointFactory;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointFactoryInterface;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroupInterface;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\Traits\CanRemoveExtraSlashesFromUrl;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\ResearchStudy as ResearchStudyResource;

class FhirClient
{
  use CanRemoveExtraSlashesFromUrl;
  use SubjectTrait;

  /** tags for notifications */
  
  const NOTIFICATION_PATIENT_IDENTIFIED = __CLASS__.':NOTIFICATION_PATIENT_IDENTIFIED';
  const NOTIFICATION_ENTRIES_RECEIVED = __CLASS__.':NOTIFICATION_ENTRIES_RECEIVED';
  const NOTIFICATION_ENTRY_RECEIVED = __CLASS__.':NOTIFICATION_ENTRY_RECEIVED';
  const NOTIFICATION_ERROR = __CLASS__.':NOTIFICATION_ERROR';
  const NOTIFICATION_RESOURCE_RECEIVED = __CLASS__.':NOTIFICATION_RESOURCE_RECEIVED';
  const NOTIFICATION_RESOURCE_ERROR = __CLASS__.':NOTIFICATION_RESOURCE_ERROR';
  const NOTIFICATION_NO_TOKENS = __CLASS__.':NOTIFICATION_NO_TOKENS';

  /**
   * current project
   *
   * @var int
   */
  private $project_id;

  /**
   * current user
   *
   * @var int
   */
  private $user_id;

  /**
   * BASE FHIR URL
   * @var string
   */
  private $base_url;

  /**
   * REDCap settings
   * @var array
   */
  private $system_configs;

  /**
   * @var FhirVersionManager
   */
  private $fhirVersionManager;

  /**
   * @var AbstractEndpointFactory
   */
  private $endpointFactory;


  /**
   * list of errors occourred in the process
   * of fetching and processing FHIR resources
   *
   * @var array
   */
  private $errors = [];

  /**
   * Fhir Token Manager
   *
   * @var FhirTokenManager
   */
  private $fhirTokenManager;

  /**
   * temporary placeholder for
   * the MRN being fetched using fetchData
   *
   * @var string
   */
  private $mrn = null;

  /**
   *
   * @var FhirSystem
   */
  private $fhirSystem;

  /**
   * create a FHIR client
   *
   * @param int $project_id
   * @param FhirTokenManager $fhirTokenManager
   */
  public function __construct($project_id, $fhirTokenManager)
  {
    $this->project_id = $project_id;
    $this->fhirTokenManager = $fhirTokenManager;
    $this->system_configs = \System::getConfigVals();
    $this->fhirSystem = $fhirSystem = $fhirTokenManager->getFhirSystem();
    $this->fhirVersionManager = FhirVersionManager::getInstance($fhirSystem);
  }

  public function getBaseUrl()
  {
    return $this->fhirVersionManager->getBaseUrl();
  }

  /**
   * get the MRN that was set the last time
   * fetchData was called
   *
   * @return string
   */
  public function getMrn()
  {
    return $this->mrn;
  }

  public function setMrn($mrn)
  {
    $this->mrn = $mrn;
  }

  public function getUserId() {
    return $this->fhirTokenManager->getUserId();
  }
  
  public function getEhrID() {
    return $this->fhirTokenManager->getFhirSystem()->getEhrID();
  }

  public function getProjectID() {
    return $this->project_id;
  }

  public function getFhirRequest($relative_url, $method='GET', $options=[])
  {
    $base_url = $this->fhirVersionManager->getBaseUrl();
    $URL = $this->removeExtraSlashesFromUrl(sprintf("%s/%s", $base_url, $relative_url));
    $fhir_request = new FhirRequest($URL, $method);
    $fhir_request->setOptions($options);
    return $fhir_request;
  }

  public function getFhirVersionCode() {
    return $this->fhirVersionManager->getFhirCode();
  }

  /**
   * use the study ID to find its FHIR id
   *
   * @param string $studyIdentifier
   * @param FhirClient $fhirClient
   * @return string|null
   */
  public function getStudyFhirId($studyIdentifier)
  {
      $endpoint = new ResearchStudy($this->getBaseUrl());
      $searchRequest = $endpoint->getSearchRequest(['identifier'=>$studyIdentifier]);
      $response = $this->sendRequest($searchRequest);
      $bundle = $response->resource;
      if(!($bundle instanceof Bundle)) return;
      $generator = $bundle->makeEntriesGenerator();
      $entry = $generator->current(); // get the first
      if(!($entry instanceof ResearchStudyResource)) return;
      $studyFhirId = $entry->getFhirID();
      if(!$studyFhirId) return;
      return $studyFhirId;
  }

  /**
   * get a patient ID using an MRN.
   * the patient ID could be
   * - the MRN itself if no patient_string_identifier is set in REDCap settings
   * - retrieved from the database in redcap_ehr_access_tokens if previously cached
   * - retrieved remotely using the patient.search FHIR endpoint
   *
   * @param string $mrn
   * @return string
   */
  public function getPatientID($mrn)
  {
    $searchPatientByMrn = function($mrn, $patient_string_identifier) {
      $identifier = "{$patient_string_identifier}|{$mrn}";
      $params = ['identifier' => $identifier];
      
      $endpointFactory = $this->getEndpointFactory();
      if(!$endpointFactory) return;
      $endpoint = $endpointFactory->makeEndpoint(FhirCategory::DEMOGRAPHICS);
      $request = $endpoint->getSearchRequest($params);
      $response = $this->sendRequest($request);
      $resource = $response->resource;
      if(!($resource instanceof Bundle)) return;
      $generator = $resource->makeEntriesGenerator();
      $patient_resource = $generator->current();
      if(!($patient_resource instanceof Patient)) return;
      $fhir_id = $patient_resource->getFhirID();
      $this->notify(self::NOTIFICATION_PATIENT_IDENTIFIED, compact('mrn', 'fhir_id'));
      return $fhir_id;
    };
    $searchPatientOnDatabase = function($mrn) {
      $query_string = "SELECT patient FROM redcap_ehr_access_tokens WHERE mrn=? AND ehr_id = ?";
      $ehrID = $this->getEhrID();
      $result = db_query($query_string, [$mrn, $ehrID]);
      if($row = db_fetch_assoc($result)) return $row['patient'];
      return false;
    };

    $fhirSystem = $this->fhirTokenManager->getFhirSystem();
    if(!$fhirSystem) {
      $noFhirSystem = new \Exception("No FHIR system can be detected", 400);
      $this->addError($noFhirSystem);
      return false;
    }
    $patient_string_identifier = $fhirSystem->getPatientIdentifierString();

    if(empty($patient_string_identifier)) return $mrn; // use the MRN
    else if($patient_id = $searchPatientOnDatabase($mrn)) return $patient_id; // check on database
    else if($patient_id = $searchPatientByMrn($mrn, $patient_string_identifier))return $patient_id; // try remote search
    $patient_not_found = new \Exception("No FHIR ID could be found for the MRN {$mrn}", 404);
    $this->addError($patient_not_found);
    return false;
  }

  /**
   * extract resources from Bundles and load extra data
   *
   * @param Bundle $bundle
   * @param AbstractResource[] $resources
   * @return AbstractResource[] entries
   */
  public function unzipBundleAndLoadMore($bundle, $resources=[], $previousRequest=null)
  {
    if($bundle instanceof Bundle) {
      // process also all entries from bundle
      $generator = $bundle->makeEntriesGenerator();
      while($entry=$generator->current()) {
        $generator->next();
        $this->notify(self::NOTIFICATION_ENTRY_RECEIVED, $entry);
        $resources[] = $entry;
      }
      // check for paginated results
      $next_request = $bundle->getNextRequest();
      if($next_request && $next_request!=$previousRequest) {
        $response = $this->sendRequest($next_request);
        $new_bundle = $response->resource;
        if($new_bundle) $resources = $this->unzipBundleAndLoadMore($new_bundle, $resources, $next_request);
      }
    }
    return $resources;
  }

  /**
   * get a valid token
   * throw exception when no tokens are available
   *
   * @return FhirToken
   * @throws Exception
   */
  public function getToken() {
    $sendMessage = function() {
      $cronMessage = "Issue: no project user has the ability to pull EHR data currently.".PHP_EOL; 
      $cronMessage .= "The REDCap CRON job will not be able to automatically pull data from the EHR for project ID {$this->project_id} until at least one person in the project establishes a connection to the EHR (via Standalone Launch or EHR launch).";
      $this->fhirTokenManager->sendAlert($cronMessage);
    };

    $token = $this->fhirTokenManager->getToken();
    if($token) return $token;
    // send an alert if we are in a CRON job
    $message = "Unauthorized. Please get a valid access token via Standalone Launch or EHR launch.";
    if(defined('CRON')) {
      $sendMessage();
    }
    throw new Exception($message, 401);
  }

  /**
   * get entries for a category of mapping fields.
   * if a bundle is paginated load all pages.
   *
   * @param string $mrn
   * @param string $category FHIR categories like Demographics, Laboratory, Medications...
   * @param FhirMappingGroupInterface $group 
   * @return FhirClientResponse
   */
  public function getEntries($patientID, $category, $group)
  {
    $makeRequest = function($patientID, $category, $group) {
      $endpointFactory = $this->getEndpointFactory();
      $endpoint = $endpointFactory->makeEndpoint($category);
      $endpointVisitor = new RedcapEndpointVisitor($this, $patientID, $group);
      $params = $endpoint->accept($endpointVisitor);
      $request = $endpoint->getSearchRequest($params);
      return $request;
    };

    $request = $makeRequest($patientID, $category, $group);

    $response = $this->sendRequest($request);
    $bundle = $response->resource;
    if(!$bundle) return $response;
    $entries = $this->unzipBundleAndLoadMore($bundle);
    $response->entries = $entries;
    return $response;
  }

  /**
   * helper function to fetch data
   * for REDCap CDIS enabled projects (CDP, CDM).
   * The provided FhirMetadataSource will help grouping
   * the data returned from the EHR system.
   * The FhirMetadataSource will also filter the results: if
   * the 'email' filed is not allowed due to project/system settings
   * it will not be returned.
   *
   * @param string $mrn
   * @param FhirMappingGroup $mappingGroup
   * @return void
   */
  public function fetchData($mrn, $mappingGroup)
  {
    $patientID = $this->getPatientID($mrn);
    if(!$patientID) return false;
    $this->mrn = $mrn; // temporarily set a reference to the current $mrn
    $this->fhirTokenManager->setPatientId($patientID);

    $category = $mappingGroup->getCategory();
    $response = $this->getEntries($patientID, $category, $mappingGroup);
    $response->fhir_id = $patientID;
    $groupedData[$category] = $response->entries;
    $notificationData = [
      'category' => $category,
      'mappingGroup' => $mappingGroup,
      'entries' => $response->entries,
    ];
    $this->notify(self::NOTIFICATION_ENTRIES_RECEIVED, $notificationData);
  }

  /**
   * return a list of exceptions
   *
   * @return Exception[]
   */
  public function getErrors()
  {
    return $this->errors;
  }

  /**
   * wheter the client contains errors
   *
   * @return boolean
   */
  public function hasErrors() {
    return count($this->errors)>0;
  }

  /**
   * add an error
   *
   * @param Exception $exception
   * @return SerializableException
   */
  public function addError($exception)
  {
    /**
     * intercept error codes and set custom messages
     */
    // $lang['data_entry_400']
    // $lang['data_entry_401']
    $makeReadableError = function(Exception $e) {
      switch ($code = $e->getCode()) {
        case 400:
          $explanation = 'Wrong format: one or more parameters are incorrect or missing.';
          break;
        case 401:
          $explanation = 'You are unauthorised. Please make sure to have a valid access token.';
          break;
        case 403:
          $explanation = 'Access is forbidden: either you do not have the right privileges or the data is protected.';
          break;
        case 404:
          $explanation = 'Nothing was found. Please check your parameters.';
          break;
        default:
          $explanation = '';
          break;
      }
      $message = $e->getMessage();
      if($explanation) {
        $explanation .= "\n$message";
        return new SerializableException($explanation, $code, $e); // return a readable explanation and iclude the original one as previous
      }
      return new SerializableException($message, $code);
    };
    $error = $makeReadableError($exception);
    $this->errors[] = $error;
    $this->notify(self::NOTIFICATION_ERROR, $error);
    return $error;
  }

  /**
   *
   * @return FhirVersionManager
   */
  public function getFhirVersionManager()
  {
    return $this->fhirVersionManager;
  }

  /**
   *
   * @return FhirTokenManager
   */
  public function getFhirTokenManager()
  {
    return $this->fhirTokenManager;
  }

  /**
   * Make a fetch request and get a FhirClientResponse.
   * The response will contain a FHIR resource.
   *
   * @param FhirRequest $request
   * @return FhirClientResponse
   */
  public function sendRequest($request)
  {
    // convert a Guzzle response into a resource
    $convertResponseToResource = function($response) {
      $payload = json_decode($response, true);
      $resourceFactory = $this->fhirVersionManager->getResourceFactory($this);
      $resource = $resourceFactory->make($payload);
      return $resource;
    };

    $decorateException = function(Exception $exception,FhirRequest $request) {
      $url = $request->getURL();
      $options = $request->getUserOptions();
      $modifiedMessage = $exception->getMessage();
      $modifiedMessage .= "\nURL: $url";
      $modifiedMessage .= "\nOPTIONS: ". json_encode($options, JSON_PRETTY_PRINT);
      return new Exception($modifiedMessage, $exception->getCode(), $exception); // Re-throw the modified exception
    };

    try {
      // instantiate the response
      $clientResponse = new FhirClientResponse($this->fhirSystem, $request, $this->project_id, $this->getUserId());
      $clientResponse->setMrn($this->mrn);

      $token = $this->getToken();
      $clientResponse->setToken($token);
      $accessToken = $token->getAccessToken();
      
      // trigger error for testing
      // throw new Exception("Error Processing Request asdads ad", 401);
      // PERFORM TESTS with payload here
      /* $debug = false;
      
      if($debug===true) {
        $path = realpath(dirname(APP_PATH_DOCROOT).'/unit-testing/tests/BreakTheGlass/payload.json');
        $response = file_get_contents($path);  
      }else {
        $response = $request->send($accessToken);
      } */

      $response = $request->send($accessToken);
      
      $resource = $convertResponseToResource($response);
      $clientResponse->setResource($resource); // add the resource to the response
    } catch (\Exception $e) {
      if($e instanceof ClientException) {
        if($e->hasResponse() && ($response = $e->getResponse())) {
          // get the response if available
          $responseBody = $response->getBody();
          if($responseBody) {
            $resource = $convertResponseToResource($responseBody);
            $clientResponse->setResource($resource); // add the resource to the response
          }
        }
      }
      $decoratedException = $decorateException($e, $request);
      $error = $this->addError($decoratedException);
      $clientResponse->setError($error); // add the error
      $this->notify(self::NOTIFICATION_RESOURCE_ERROR, $clientResponse);
    }finally {
      // notify the response to subscibers (could be available also if an exception was thrown)
      $this->notify(self::NOTIFICATION_RESOURCE_RECEIVED, $clientResponse);
      return $clientResponse;
    }
  }

  /**
  * Undocumented function
  *
  * @param AbstractEndpoint $endpoint
  * @param string $identifier
  * @param string $interaction
  * @param array $options
  * @return FhirRequest
  */
  public function makeRequest($endpoint, $identifier, $interaction, $options=[]) {
    $endpointFactory = $this->getFhirVersionManager()->getEndpointFactory();
		$request = $endpointFactory->make($endpoint, $identifier, $interaction, $options);
    return $request;
  }

  /**
   * get an endpoint factory based on the current FHIR version
   *
   * @return EndpointFactoryInterface
   */
  public function getEndpointFactory()
  {
    if(!$this->endpointFactory) {
      $factory = $this->fhirVersionManager->getEndpointFactory();
      if(!$factory) {
        $error = new \Exception(sprintf("No endpoint factory available for the FHIR version '%s'.", $this->fhirVersionManager->getVersion()), 400);
        $this->addError($error);
        return false;
      }
      $this->endpointFactory = $factory;
    }
    return $this->endpointFactory;
  }

  /**
   * @return FhirSystem
   */
  public function getFhirSystem() { return $this->fhirTokenManager->getFhirSystem(); }

}