<?php

/**
 * DB Model log record class
 */

class Db_ModelLogRecord extends Db_ActiveRecord
{
	public $table_name = "db_model_logs";

	public $custom_columns = array('message' => db_varchar);

	public $model_log_create_name = 'Created Record';
	public $model_log_update_name = 'Updated Record';
	public $model_log_delete_name = 'Deleted Record';
	public $model_log_custom_name = 'Custom Event';

	protected $param_data_array = null;

	public static function create() { return new self(); }

	public function before_validation_on_create($deferred_session_key = null)
	{
		$this->record_datetime = Phpr_DateTime::now();
	}

	public function define_columns($context = null)
	{
		$this->define_column('id', 'ID');
		$this->define_column('record_datetime', 'Date and Time')->order('desc')->dateFormat('%x %X');
		$this->define_column('message', 'Message');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('message');
	}

	public function eval_message()
	{
		switch ($this->type)
		{
			case Model_Log::type_create: return $this->model_log_create_name; break;
			case Model_Log::type_update: return $this->model_log_update_name; break;
			case Model_Log::type_delete: return $this->model_log_delete_name; break;
			case Model_Log::type_custom: return $this->get_param_data_field('message', $this->model_log_custom_name); break;
		}
		return "";
	}

	public function is_custom()
	{
		return ($this->type == Model_Log::type_custom);
	}


	/**
	 * Returns associative array of changed field data, using model field name as key.
	 * @return array
	 */
	public function get_fields_array()
	{
		$changed_fields_array = array();
		$field_data_array = $this->get_param_data_field('field');

		if ($field_data_array) {
				$changed_fields = isset($field_data_array['@attributes']) ? array($field_data_array) : $field_data_array;
				foreach($changed_fields as $field){
					$changed_fields_array[$field['@attributes']['name']] = $field;
				}
		}

		return $changed_fields_array;
	}


	/**
	 * Returns logged parameter data stored in XML to array
	 * @return mixed|null
	 */
	public function get_param_data_as_array(){
		$this->param_data_array = $this->param_data_array ? $this->param_data_array : Phpr_Xml::to_array($this->param_data);
		return $this->param_data_array;
	}

	/**
	 * Returns a specific data field stored in XML to array
	 * @return mixed|null
	 */
	public function get_param_data_field($field, $default = null)
	{
		$data_fields = $this->get_param_data_as_array();

		if (!array_key_exists($field, $data_fields))
			return $default;

		return $data_fields[$field];
	}
}