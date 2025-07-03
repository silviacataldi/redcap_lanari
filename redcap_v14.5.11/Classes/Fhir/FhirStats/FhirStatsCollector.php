<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirStats;

use Project;
use DynamicDataPull;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;

class FhirStatsCollector
{
    /**
     *
     * @var integer
     */
    private $project_id;
    /**
     *
     * @var Project
     */
    private $project;
    /**
     * adjudication per project types
     */
    const REDCAP_TOOL_TYPE_CDM = 'CDM'; // Clinical Data Mart
    const REDCAP_TOOL_TYPE_CDP = 'CDP'; // Clinical Data Pull
    const REDCAP_TOOL_TYPE_CDP_INSTANT = 'CDP_I'; // Clinical Data Pull performed with "instant adjudication"

    const COUNTS_TABLE_NAME = 'redcap_ehr_import_counts';

    /**
     * map the fields category in FHIR external fields to a FHIR endpoint
     *
     * @var array
     */
    private static $category_to_resource_mapping = [
        FhirCategory::DEMOGRAPHICS => EndpointIdentifier::PATIENT,
        FhirCategory::VITAL_SIGNS => EndpointIdentifier::OBSERVATION_VITALS,
        FhirCategory::LABORATORY => EndpointIdentifier::OBSERVATION_LABS,
        FhirCategory::ALLERGY_INTOLERANCE => EndpointIdentifier::ALLERGY_INTOLERANCE,
        FhirCategory::MEDICATIONS => EndpointIdentifier::MEDICATION,
        FhirCategory::CONDITION => EndpointIdentifier::CONDITION,
        FhirCategory::ENCOUNTER => EndpointIdentifier::ENCOUNTER,
        FhirCategory::IMMUNIZATION => EndpointIdentifier::IMMUNIZATION,
        FhirCategory::CORE_CHARACTERISTICS => EndpointIdentifier::OBSERVATION_CORE_CHARACTERSITICS,
        FhirCategory::SOCIAL_HISTORY => EndpointIdentifier::OBSERVATION_SOCIAL_HISTORY,
        FhirCategory::ADVERSE_EVENT => EndpointIdentifier::ADVERSE_EVENT,
        FhirCategory::SMART_DATA => EndpointIdentifier::OBSERVATION_SMART_DATA,
        FhirCategory::PROCEDURE => EndpointIdentifier::PROCEDURE,
    ];

    /**
     * get the list of tracked resource types
     *
     * @return array
     */
    public static function getResourceIdentifiers() { return array_unique(static::$category_to_resource_mapping); }

    /**
     * store all entries during CDIS operations
     * before storing them to the database
     * entries could have different types and could
     * be adjudicated or not
     *
     * @var array
     */
    private $entries = [];

    /**
     * stats could be for CDM or CDP
     *
     * @var string
     */
    private $type;

    /**
     * create a stats collector
     *
     * @param integer $project_id
     * @param string $type type of the project (CDM or CDP)
     */
    public function __construct($project_id, $type)
    {
        // check if the provided type is valid
        $valid_types = [self::REDCAP_TOOL_TYPE_CDM, self::REDCAP_TOOL_TYPE_CDP, self::REDCAP_TOOL_TYPE_CDP_INSTANT];
        if(!in_array($type, $valid_types)) throw new \Exception("Invalid type", 1);
        $this->type = $type;
        $this->project_id = $project_id;
        $this->project = new \Project($project_id);
    }

    /**
     * react to notifications (from the FHIR client)
     *
     * @param SplSubject $subject
     * @param string $event
     * @param mixed $data
     * @return void
     */
    public function update($subject, $event = null, $data = null)
    {
        switch ($event) {
            case DynamicDataPull::NOTIFICATION_DATA_COLLECTED_FOR_SAVING:
                // increment the FHIR statistic counter for the adjudicated data
                $fhirCategory = DynamicDataPull::getMappedFhirResourceFromFieldName($data['project_id'], $data['redcap_field'], $data['redcap_event']);
                if($fhirCategory) $this->addEntry($data['record_id'], $fhirCategory, $data['increment']);
                break;
            case DynamicDataPull::NOTIFICATION_DATA_SAVED_FOR_ALL_EVENTS:
                $this->logEntries(); // persist FHIR statistics
                break;
            case DynamicDataPull::NOTIFICATION_DATA_SAVED:
                // record has been saved in a specific event
                break;
            default:
                # code...
                break;
        }
    }

    public function getProjectId()
    {
        return $this->project_id;
    }

    public function log($record_id, $stats=[])
    {
        $implodeAndQuote = function($array) {
            return "`" . implode("`,`", $array) . "`";
        };
        $timestamp = date('Y-m-d H:i:s', time());
        $insertData = [
            'ts' => checkNull($timestamp),
            'type' => checkNull($this->type),
            'project_id' => $this->project_id,
            'record' => checkNull($record_id),
        ];
        $noData = true;
        foreach ($stats as $category => $count) {
            $key = self::$category_to_resource_mapping[$category] ?? null; // get the endpoint identifier if exists
            if(!$key) continue; // skip if no match
            $insertData["counts_{$key}"] = intval($count);
            $noData = false; // track if any data needs to be saved
        }
        if($noData) return;

        $columns = $implodeAndQuote(array_keys($insertData));
        $values = implode(',', $insertData);
        $query_string = sprintf(
            'INSERT INTO `%s`
            (%s) VALUES (%s)',
            self::COUNTS_TABLE_NAME,
            $columns, $values
        );
        $result = db_query($query_string);
        return $result!==false;
    }

    /**
     * get the list of entries
     *
     * @return array
     */
    public function getEntries()
    {
        return $this->entries;
    }
    
    /**
     * log all stored entries
     *
     * @return void
     */
    public function logEntries()
    {
        $entries = $this->getEntries();
        foreach ($entries as $record_id => $stats) {
            $this->log($record_id, $stats);
        }
    }

    /**
     * increment the counter of the FHIR resource for a specific record
     *
     * @param integer $record_id
     * @param string $fhir_resource_type
     * @return void
     */
    public function addEntry($record_id, $fhir_resource_type, $count=1)
    {
        $fhirCategories = array_keys(self::$category_to_resource_mapping);
        if(!in_array($fhir_resource_type, $fhirCategories)) return;
        if(!array_key_exists($record_id, $this->entries) || !is_array($this->entries[$record_id]) ) {
            $this->entries[$record_id] = [];
        }
        if(!array_key_exists($fhir_resource_type, $this->entries[$record_id])) $this->entries[$record_id][$fhir_resource_type] = 0;
        $this->entries[$record_id][$fhir_resource_type] += intval($count);
    }
}