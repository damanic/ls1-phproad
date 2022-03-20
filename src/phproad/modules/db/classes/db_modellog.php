<?php

/**
 * Adds logging functionality to model classes.
 * Only fields defined with the method define_column are considered.
 */
class Db_ModelLog extends Phpr_Extension
{
    const typeCreate = 'create';
    const typeUpdate = 'update';
    const typeDelete = 'delete';
    const typeCustom = 'custom';

    protected $_model;
    protected $_loadedData = array();
    protected $_autoLogging = true;
    protected $_disabled = false;
    protected $_maxRecords = null;

    public function __construct($model)
    {
        $this->_model = $model;

        if (isset($model->model_log_auto)) {
            $this->_autoLogging = $model->model_log_auto;
        }

        if (isset($model->model_log_max_records)) {
            $this->_maxRecords = $model->model_log_max_records;
        }

        if ($this->_autoLogging) {
            $this->_model->addEvent('onAfterCreate', $this, 'modelLogOnModelCreated');
            $this->_model->addEvent('onAfterUpdate', $this, 'modelLogOnModelUpdated');
            $this->_model->addEvent('onAfterDelete', $this, 'modelLogOnModelDeleted');
        }

        $this->_model->addEvent('onAfterLoad', $this, 'modelLogOnModelLoaded');
    }

    /**
     * Logs field values for newly created model record
     *
     * If the model supports auto footprints the ID and NAME of the
     * user creating the record will be logged
     *
     * @return void
     */
    public function modelLogOnModelCreated()
    {
        if ($this->_disabled) {
            return;
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $record = new DOMElement('record');
        $dom->appendChild($record);

        $newModel = $this->getReloadedModel();
        $newValues = $newModel ? $this->getDisplayValues($newModel) : null;

        foreach ($newValues as $dbName => $value) {
            if (!strlen($value)) {
                continue;
            }

            $displayName = $dbName;
            $type = db_text;
            $this->getFieldNameAndType($dbName, $type, $displayName);

            $fieldNode = new DOMElement('field');
            $record->appendChild($fieldNode);
            $fieldNode->setAttribute('name', $dbName);
            $fieldNode->setAttribute('displayName', $displayName);
            $fieldNode->setAttribute('type', $type);

            $new = new DOMElement('new', $value);
            $fieldNode->appendChild($new);
        }

        $user = null;
        if ($this->_model->auto_footprints) { //use footprints if available
            $user = (object)array(
                'id' => $this->_model->created_user_id,
                'name' => $this->_model->created_user_name,
            );
        }
        $this->createLogRecord(self::typeCreate, $dom->saveXML(), $user);
    }

    /**
     * Logs any field values that have changed when a model record is updated
     *
     * User footprints on the record are not logged because they are not
     * always progressed on this event (eg. backend processes do not update footprints).
     *
     * @param bool $log_active_user If set to false, lookup of active user will not be performed
     *
     * @return void
     */
    public function modelLogOnModelUpdated($log_active_user = true)
    {
        if ($this->_disabled) {
            return;
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $record = new DOMElement('record');
        $dom->appendChild($record);

        $newModel = $this->getReloadedModel();
        $newValues = $newModel ? $this->getDisplayValues($newModel) : null;
        $fieldsAdded = 0;

        foreach ($this->getLoadedData() as $dbName => $originalValue) {
            $updatedValue = null;
            if (array_key_exists($dbName, $newValues)) {
                $updatedValue = $newValues[$dbName];
            }

            $displayName = $dbName;
            $type = db_text;
            $this->getFieldNameAndType($dbName, $type, $displayName);

            $compareOld = $originalValue;
            $compareNew = $updatedValue;

            //
            // Some comparisons require simplification
            //
            if ($type == 'float') {
                //Do not consider trailing zeros
                $compareOld = rtrim($originalValue, "0");
                $compareNew = rtrim($updatedValue, "0");
            }

            if (strcmp($compareOld, $compareNew) != 0) {
                $fieldNode = new DOMElement('field');
                $record->appendChild($fieldNode);
                $fieldNode->setAttribute('name', $dbName);
                $fieldNode->setAttribute('displayName', $displayName);
                $fieldNode->setAttribute('type', $type);

                $old = new DOMElement('old', $originalValue);
                $fieldNode->appendChild($old);

                $new = new DOMElement('new', $updatedValue);
                $fieldNode->appendChild($new);
                $fieldsAdded++;
            }
        }

        if ($fieldsAdded) {
            $user = null;
            if ($log_active_user) {
                $user = Phpr::$security->getUser();
            }
            $this->createLogRecord(self::typeUpdate, $dom->saveXML(), $user);
        }
    }

    /**
     * Logs the ID of the deleted models record
     *
     * This will log the active user if one is found.
     *
     * @param bool $log_active_user If set to false, lookup of active user will not be performed
     *
     * @return Db_ActiveRecord The model deleted
     */
    public function modelLogOnModelDeleted($log_active_user = true)
    {
        if ($this->_disabled) {
            return;
        }

        $user = null;

        if ($log_active_user) {
            $user = Phpr::$security->getUser();
        }

        $this->createLogRecord(self::typeDelete, null, $user);
        return $this->_model;
    }

    /**
     * Copies the models values when a model is loaded so that
     * they can be compared against created/updated values to
     * identify changed fields.
     *
     * @return Db_ActiveRecord The model under consideration
     */
    public function modelLogOnModelLoaded()
    {
        $this->_loadedData = $this->getDisplayValues();
        return $this->_model;
    }

    /**
     * Create custom model logs for other events
     *
     * @param string $name   Name of the custom event
     * @param array  $params A simple array of key=>values to store in the log
     * @param null   $user   Optional , a user to associate the log with
     *
     * @return mixed
     */
    public function modelLogCustom($name, $params = array(), $user = null)
    {
        $fieldsAdded = 0;
        $params['message'] = $name;

        $dom = new DOMDocument('1.0', 'utf-8');
        $record = new DOMElement('record');
        $dom->appendChild($record);

        foreach ($params as $param_key => $param_value) {
            $fieldNode = new DOMElement($param_key, $param_value);
            $record->appendChild($fieldNode);
            $fieldsAdded++;
        }

        if ($fieldsAdded) {
            $this->createLogRecord(self::typeCustom, $dom->saveXML(), $user);
        }

        return $this->_model;
    }

    /**
     * Use this to disable all logging on this model
     *
     * @return mixed
     */
    public function modelLogDisable()
    {
        $this->_disabled = true;
        return $this->_model;
    }

    public function modelLogCleanup($number_to_keep = null)
    {
        $this->deleteOldRecords($number_to_keep);
        return $this->_model;
    }

    public function modelLogFetchAll()
    {
        return $this->modelLogFind()->find_all();
    }

    public function modelLogFind()
    {
        $primary_key = $this->_model->primary_key;
        $records = Db_ModelLogRecord::create();
        $records->where('master_object_class=?', get_class($this->_model));
        $records->where('master_object_id=?', $this->_model->$primary_key);
        return $records;
    }


    // Internals
    //

    private function createLogRecord($type, $content, $user = null)
    {
        $primaryKey = $this->_model->primary_key;
        $record = new Db_ModelLogRecord();
        $record->master_object_class = get_class($this->_model);
        $record->master_object_id = $this->_model->$primaryKey;
        $record->param_data = $content;
        $record->type = $type;
        if ($user) {
            $record->user_id = $user->id;
            $record->user_name = $user->name;
        }
        $record->save();
    }

    private function deleteOldRecords($number_to_keep = null)
    {
        if (!$number_to_keep && ($this->_maxRecords === null || $this->_maxRecords = 0)) {
            return;
        }

        if (!$number_to_keep) {
            $number_to_keep = $this->_maxRecords;
        }

        $primary_key = $this->_model->primary_key;
        $where = 'master_object_class=:class AND master_object_id=:id';
        $bind = array('class' => get_class($this->_model), 'id' => $this->_model->$primary_key);

        $count = Db_Helper::scalar('select count(*) from db_model_logs where ' . $where, $bind);
        $offset = $count - $number_to_keep;

        if ($offset <= 0) {
            return;
        }

        Db_Helper::query(
            'delete from db_model_logs where ' . $where . ' order by record_datetime limit ' . $offset,
            $bind
        );
    }

    private function getDisplayValues($model = null)
    {
        $model = $model ? $model : $this->_model;
        $skipFields = array_merge(
            $model->auto_create_timestamps,
            $model->auto_update_timestamps,
            array('created_user_id', 'updated_user_id')
        );

        $result = array();
        $fields = $model->get_column_definitions();
        foreach ($fields as $dbName => $definition) {
            if (!$definition->log) {
                if ($definition->isCalculated || $definition->isCustom || in_array(
                    $dbName,
                    $skipFields
                ) || $definition->noLog
                ) {
                    continue;
                }
            }

            $result[$dbName] = $model->displayField($dbName);
        }

        return $result;
    }

    private function getLoadedData()
    {
        return $this->_loadedData ? $this->_loadedData : $this->getDisplayValues();
    }

    private function getFieldNameAndType($dbName, &$type, &$displayName)
    {
        $fields = $this->_model->get_column_definitions();

        if (array_key_exists($dbName, $fields)) {
            $displayName = $fields[$dbName]->displayName;
            $type = $fields[$dbName]->type;

            if ($fields[$dbName]->isReference) {
                if ($fields[$dbName]->referenceType == 'has_many' || $fields[$dbName]->referenceType == 'has_and_belongs_to_many') {
                    $type = 'list';
                } elseif ($type == db_text || $type == db_varchar) {
                    $type = null;
                }
            }
        }
    }

    private function getReloadedModel()
    {
        $modelClass = get_class($this->_model);
        $newModel = new $modelClass();
        $newModel->simpleCaching = false;
        $primaryKey = $newModel->primary_key;
        return $newModel->find($this->_model->$primaryKey);
    }
}
