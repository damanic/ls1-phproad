<?php namespace Db;

use SimpleXMLElement;

use Phpr\Extension;
use Phpr\Xml;

/**
 * Dynamic Column Extension
 *
 * Adds a special field type dynamic_data that stores flexible data.
 *
 * Usage:
 *
 * public $implement = 'Db\Model_Dynamic';
 *
 */

class ModelDynamic extends Extension
{

    protected $model;
    protected $field_name = "config_data";
    
    public $added_dynamic_fields = array();
    public $added_dynamic_columns = array();

    public function __construct($model)
    {
        parent::__construct();
        
        $this->model = $model;

        if (isset($model->dynamic_model_field)) {
            $this->field_name = $model->dynamic_model_field;
        }

        $this->model->add_event('db:on_after_load', $this, 'loadDynamicData');
        $this->model->add_event('db:on_before_update', $this, 'setDynamicData');
        $this->model->add_event('db:on_before_create', $this, 'setDynamicData');
    }

    public function addDynamicField($code, $title, $side = 'full', $type = db_text)
    {
        $this->defineDynamicColumn($code, $title, $type);
        return $this->addDynamicFormField($code, $side);
    }

    public function defineDynamicColumn($code, $title, $type = db_text)
    {
        return $this->added_dynamic_columns[$code] = $this->model->define_custom_column($code, $title, $type);
    }

    public function addDynamicFormField($code, $side = 'full')
    {
        return $this->added_dynamic_fields[$code] = $this->model->add_form_field($code, $side)
            ->options_method('get_added_field_options')
            ->option_state_method('get_added_field_option_state');
    }

    public function setDynamicField($field)
    {
        return $this->added_dynamic_columns[$field];
    }

    public function setDynamicData()
    {
        $document = new SimpleXMLElement('<data></data>');
        foreach ($this->added_dynamic_columns as $field_id => $value) {
            $value = serialize($this->model->{$field_id});
            $field_element = $document->addChild('field');
            Xml::createNode($document, $field_element, 'id', $field_id);
            Xml::createNode($document, $field_element, 'value', $value, true);
        }

        $dynamic_field = $this->field_name;
        $this->model->{$dynamic_field} = $document->asXML();
    }

    public function loadDynamicData()
    {
        $dynamic_field = $this->field_name;

        if (!strlen($this->model->{$dynamic_field})) {
            return;
        }

        $object = new SimpleXMLElement($this->model->{$dynamic_field});
        foreach ($object->children() as $child) {
            $field_id = (string)$child->id;
            try {
                if (!strlen($field_id)) {
                    continue;
                }
                
                $this->model->$field_id = unserialize($child->value);
                $this->model->fetched[$field_id] = unserialize($child->value);
            } catch (Exception $ex) {
                $this->model->$field_id = "NaN";
                $this->model->fetched[$field_id] = "NaN";
                traceLog(sprintf('Db\ModelDynamic was unable to parse %s in %s', $field_id, get_class($this->model)));
            }
        }
    }

    /* @deprecated */
    public function defineConfigColumn($code, $title, $type = db_text)
    {
        return $this->defineDynamicColumn($code, $title, $type);
    }
    public function addConfigField($code, $side = 'full')
    {
        return $this->addDynamicField($code, $side);
    }
    public function setConfigField($field)
    {
        return $this->setDynamicField($field);
    }
}
