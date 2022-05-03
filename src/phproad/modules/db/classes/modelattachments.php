<?php namespace Db;

use Phpr\Extension;
use FileSystem\Upload;
/**
 * Adds method 'add_attachments_field' to ActiveRecord class.
 * Use this class to add file attachments fields to your models.
 */
class ModelAttachments extends Extension
{
    protected $_model;

    public function __construct($model)
    {
        $this->_model = $model;
    }

    /**
     * Adds a file attachments column to the model
     *
     * @param  string $columnName  Specifies a column name. You may use any unique sql-compatible name here.
     * @param  string $displayName Specifies a name to display in lists and forms
     * @param  bool   $showInLists Determines whether the field should be visible in record lists
     * @return FormFieldDefinition
     */
    public function add_attachments_field($columnName, $displayName, $showInLists = false)
    {
        $this->_model->add_relation(
            'has_many', $columnName, array(
            'class_name' => 'Db\File',
            'foreign_key' => 'master_object_id',
            'conditions' => "master_object_class='" . get_class($this->_model) . "'",
            'order' => 'id',
            'delete' => true
            )
        );

        $column = $this->_model->define_multi_relation_column($columnName, $columnName, $displayName, '@name');
        if (!$showInLists) {
            $column->invisible();
        }

        $column->validation();

        return $this->_model->add_form_field($columnName)->renderAs(frm_file_attachments);
    }

    /**
     * Saves and attaches a postback file to a model
     * @param string $field Field name for the file relationship
     * @param array $file_info The file postback array. Eg: $_FILE[$field]
     * @param bool $delete Determines whether any existing attachments should be deleted first
     * @param string $session_key Defined session key for deferred bindings
     * @return Db\File
     */
    public function save_attachment_from_post($field='files', $file_info, $delete = false, $session_key = null)
    {
        if ($session_key === null)
            $session_key = post('session_key');

        if (!array_key_exists('error', $file_info) || $file_info['error'] == UPLOAD_ERR_NO_FILE)
            return;

        Upload::validate_uploaded_file($file_info);

        $this->_model->init_columns();

        if ($delete)
        {
            $files = $this->_model->get_all_deferred($field, $session_key);
            foreach ($files as $existing_file)
            {
                $this->_model->{$field}->delete($existing_file, $session_key);
            }
        }

        $file = File::create();
        $file->is_public = true;

        $file->from_post($file_info);
        $file->master_object_class = get_class($this->_model);
        $file->master_object_id = $this->_model->id;
        $file->field = $field;
        $file->save(null, $session_key);

        $this->_model->{$field}->add($file, $session_key);
        return $file;
    }
}
