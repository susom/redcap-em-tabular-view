<?php

namespace Stanford\TabularView;

ini_set("memory_limit", "-1");
ini_set('max_execution_time', 0);
set_time_limit(0);

use REDCap;

define("REPEAT_INSTANCES", "repeat_instances");
define("DATE_FORMAT", "m/d/y H:i:s");

/**
 * Class TabularView
 * @package Stanford\TabularView
 * @property int $projectId
 * @property array $instruments
 * @property \Project $project
 * @property int $eventId
 * @property array $record
 * @property array $fields
 * @property string $mrnField
 * @property array $instances
 * @property array $dataDictionary
 */
class TabularView extends \ExternalModules\AbstractExternalModule
{

    private $projectId;

    private $instruments;

    private $project;

    private $eventId;

    private $record;

    private $fields;

    private $mrnField;

    private $instances;

    private $dataDictionary = array();
    public function __construct()
    {
        try {
            parent::__construct();

            if (isset($_GET['pid']) || isset($_POST['pid'])) {

                if (isset($_GET['pid'])) {
                    $projectId = filter_var($_GET['pid'], FILTER_SANITIZE_NUMBER_INT);
                } elseif (isset($_POST['pid'])) {
                    $projectId = filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT);
                }
                $this->setProjectId($projectId);

                $this->setProject(new \Project($this->getProjectId()));

                $this->getProject()->setRepeatingFormsEvents();

                $this->setEventId($this->getFirstEventId());

                $this->setInstruments();

                $this->setMrnField();

                $this->setInstances();

                $this->setFields();

                $this->setDataDictionary(REDCap::getDataDictionary($this->getProjectId(), 'array'));
            }
        } catch (\Exception $exception) {
            echo $exception->getMessage();
        } catch (\LogicException $exception) {
            echo $exception->getMessage();
        }
    }

    /**
     * @return array
     */
    public function getDataDictionary()
    {
        return $this->dataDictionary;
    }

    /**
     * @param array $dataDictionary
     */
    public function setDataDictionary($dataDictionary)
    {
        $this->dataDictionary = $dataDictionary;
    }

    /**
     * @return array
     */
    public function getDataDictionaryProp($prop)
    {
        return $this->dataDictionary[$prop];
    }
    /**
     * @return int
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * @param int $projectId
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }

    /**
     * @return array
     */
    public function getInstruments()
    {
        return $this->instruments;
    }

    /**
     * @param array $instruments
     */
    public function setInstruments()
    {
        $this->instruments = \REDCap::getInstrumentNames();
    }

    /**
     * @return \Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param \Project $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }

    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->eventId;
    }

    /**
     * @param int $eventId
     */
    public function setEventId($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * @return array
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * @param array $record
     */
    public function setRecord($record)
    {
        $this->record = $record;
    }


    public function isRepeatingForm($key)
    {
        return $this->getProject()->isRepeatingForm($this->getEventId(), $key);
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     */
    public function setFields()
    {
        $results = array();
        foreach ($this->getInstances() as $instance) {
            if ($instance['instrument-label'] != "" && $instance['date-field'] != "") {
                $temp = array_merge(array($instance['date-field']), $this->extractField($instance['instrument-label']));
            } elseif ($instance['date-field'] != "" && $instance['instrument-label'] == "") {
                $temp = array($instance['date-field']);
            } elseif ($instance['instrument-label'] != "" && $instance['date-field'] == "") {
                $temp = $this->extractField($instance['instrument-label']);
            }
            /**
             * remove any duplication
             */
            $temp = array_unique($temp);
            if (empty($results)) {
                $results = $temp;
            } else {
                $results = array_merge($results, $temp);
            }
        }
        $this->fields = $results;
    }

    /**
     * @return string
     */
    public function getMrnField()
    {
        return $this->mrnField;
    }

    /**
     */
    public function setMrnField()
    {
        $this->mrnField = $this->getProjectSetting("mrn_field");;
    }

    /**
     * @return array
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     * @param array $instances
     */
    public function setInstances()
    {
        $this->instances = $this->getSubSettings('instance', $this->getProjectId());;
    }


    public function searchRecordViaMRN($term)
    {

        $params = array(
            'return_format' => 'array',
            'fields' => array_merge(array($this->getMrnField() => $this->getMrnField()), $this->getFields()),
        );
        $records = REDCap::getData($params);
        /**
         * search for specified MRN
         */
        foreach ($records as $id => $record) {
            if ($record[$this->getEventId()][$this->getMrnField()] == $term) {
                $record['id'] = $id;
                $this->setRecord($this->processRecord($record));
                break;
            }
        }
    }

    private function getValueLabel($value, $prop)
    {
        $group = $prop['select_choices_or_calculations'];
        $choices = explode('|', $group);
        $result = '';
        foreach ($choices as $choice) {
            $components = explode(",", $choice);
            if ($prop['field_type'] == 'checkbox') {
                foreach ($value as $k => $v) {
                    //make sure the option selected is same as in the loop
                    if ($k != $components[0]) {
                        continue;
                    }
                    //checkbox is checked
                    if ($v == "1") {
                        $result .= ' ' . end($components) . ' => Yes,';
                    } else {
                        $result .= ' ' . end($components) . ' => No,';
                    }
                }
                $result = ltrim($result, ",");
            } else {
                if ($value == $components[0]) {
                    $result = end($components);
                }
            }
        }
        return $result;
    }

    /**
     * @param $record
     * @return array
     */
    private function processRecord($record)
    {
        $result = array();
        foreach ($record[REPEAT_INSTANCES][$this->getEventId()] as $instrument => $array) {
            $identifiers = $this->searchInstances($instrument);
            foreach ($array as $instanceId => $instance) {
                $dateIdentifier = $identifiers['date-field'];
                $summeryFields = $this->extractField($identifiers['instrument-label']);
                $summery = str_replace(array("[", "]"), "", $identifiers['instrument-label']);
                $url = $this->getRecordURL($record['id'], $instrument, $instanceId);
                foreach ($summeryFields as $field) {
                    if (isset($instance[$field])) {
                        $prop = $this->getDataDictionaryProp($field);
                        //if dropdown or checkbox get the label instead of numeric value.
                        if ($prop['field_type'] == 'checkbox' || $prop['field_type'] == 'dropdown') {
                            $instance[$field] = $this->getValueLabel($instance[$field], $prop);
                        }
                        $summery = str_replace($field, $instance[$field], $summery);
                    }
                }
                if (!is_null($dateIdentifier)) {
                    $dateValue = strtotime($instance[$dateIdentifier]);
                    $temp = array(
                        'id' => $record['id'],
                        'url' => $url,
                        'date' => date("m/d/Y H:i:s", $dateValue),
                        "summery" => $summery,
                        'instrument' => $instrument
                    );
                    $result[$dateValue] = $temp;
                } else {
                    $temp = array(
                        'id' => $record['id'],
                        'url' => $url,
                        'date' => null,
                        "summery" => $summery,
                        'instrument' => $instrument
                    );
                    $result[] = $temp;
                }
            }
        }
        krsort($result);
        return $result;
    }

    /**
     * @param string $instrument
     * @return array
     */
    private function searchInstances($instrument)
    {
        foreach ($this->getInstances() as $instance) {
            if ($instance['instrument'] == $instrument) {
                return $instance;
            }
        }
    }

    private function extractField($text)
    {
        $result = array();
        preg_match_all("/\[(.*?)\]/", $text, $matches);
        foreach ($matches[1] as $match) {
            $text = str_replace(array("[", "]"), "", $match);
            $result[] = $text;
        }
        return $result;
    }

    private function getRecordURL($id, $instrument, $instance = null)
    {
        $projectId = $this->getProjectId();
        $eventId = $this->getEventId();
        if (!is_null($instance)) {
            return APP_PATH_WEBROOT . "DataEntry/index.php?pid=$projectId&id=$id&event_id=$eventId&page=$instrument&instance=$instance";
        } else {
            return APP_PATH_WEBROOT . "DataEntry/index.php?pid=$projectId&id=$id&event_id=$eventId&page=$instrument";
        }

    }
}