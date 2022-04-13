<?php namespace Db;

use Phpr\Xml;
use Phpr\DateTime as PhprDateTime;

/**
 * DB Model log record class
 */

class ModelLogRecord extends ActiveRecord
{
    public $table_name = "db_model_logs";

    public $custom_columns = array('message' => db_varchar);

    public $model_log_create_name = 'Created Record';
    public $model_log_update_name = 'Updated Record';
    public $model_log_delete_name = 'Deleted Record';
    public $model_log_custom_name = 'Custom Event';

    protected $param_data_array = null;

    public static function create()
    {
        return new self();
    }

    public function before_validation_on_create($deferred_session_key = null)
    {
        $this->record_datetime = PhprDateTime::now();
    }

    public function define_columns($context = null)
    {
        $this->define_column('id', 'ID');
        $this->define_column('record_datetime', 'Date and Time')->order('desc')->dateFormat('%x %X');
        $this->define_column('message', 'Message');
        $this->define_column('param_data', 'Param Data')->invisible();
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('message');
    }

    public function eval_message()
    {
        switch ($this->type) {
            case ModelLog::typeCreate:
                return $this->model_log_create_name;
            case ModelLog::typeUpdate:
                return $this->model_log_update_name;
            case ModelLog::typeDelete:
                return $this->model_log_delete_name;
            case ModelLog::typeCustom:
                return $this->getDataValue('message', $this->model_log_custom_name);
        }
        return "";
    }

    public function is_custom()
    {
        return ($this->type == ModelLog::typeCustom);
    }


    /**
     * Returns associative array of field data, using the field name attribute as key.
     *
     * @return array
     */
    public function get_fields_array()
    {
        $changed_fields_array = array();
        $field_data_array = $this->getDataValue('field');


        if ($field_data_array) {
            $changed_fields = isset($field_data_array['@attributes']) ? array($field_data_array) : $field_data_array;
            foreach ($changed_fields as $field) {
                $changed_fields_array[$field['@attributes']['name']] = $field;
            }
        }

        return $changed_fields_array;
    }

    /**
     * Returns logged parameter data stored in XML to array
     *
     * @return mixed|null
     */
    public function dataAsArray()
    {
        if ($this->param_data_array !== null) {
            return $this->param_data_array;
        }

        $result = array();
        if (strlen($this->param_data)) {
            try {
                $result = Xml::toPlainArray($this->param_data, true);
            } catch (\Exception $ex) {
                // Do nothing
            }
        }

        return $this->param_data_array = $result;
    }

    /**
     * Returns logged parameter data stored in XML to array
     *
     * @return mixed|null
     */
    public function getDataValue($field, $default = null)
    {
        $fields = $this->dataAsArray();

        if (!array_key_exists($field, $fields)) {
            return $default;
        }

        return $fields[$field];
    }


    /**
     * @deprecated
     */
    public function get_param_data_as_array()
    {
        return $this->dataAsArray();
    }

    /**
     * @deprecated
     */
    public function get_param_data_field($field, $default = null)
    {
        return $this->getDataValue($field, $default);
    }
}
