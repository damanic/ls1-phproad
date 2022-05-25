<?php

namespace Shop;

use Phpr;
use Db\Helper as DbHelper;

/**
 * Class ProductProperty_Condition
 * Matches against products extra/custom properties
 */
class ProductProperty_Condition extends ModelAttributesConditionBase
{
    protected $model_class = 'shop_product';

    public function get_grouping_title()
    {
        return 'Product PROP';
    }

    public function get_title($host_obj)
    {
        return "Product PROP condition";
    }

    public function get_value_control_type($host_obj)
    {
        /*
        $attribute = $host_obj->subcondition;
        $operator = $host_obj->operator;

        $product = $this->get_model_obj();
        $definitions = $product->get_column_definitions();
        */
        return parent::get_value_control_type($host_obj);
    }

    public function get_custom_text_value($parameters_host)
    {
        $attribute = $parameters_host->subcondition;

        return false;
    }

    public function get_operator_options($host_obj = null)
    {
        $options = [
            'is' => 'is',
            'equals_or_greater' => 'equals_or_greater',
            'equals_or_less' => 'equals_or_less',
            'greater' => 'greater',
            'less' => 'less'
        ];
        return $options;
    }

    public function get_condition_type()
    {
        return RuleConditionBase::type_any;
    }

    public function get_value_dropdown_options($host_obj, $controller)
    {
        $attribute = $host_obj->subcondition;

        return parent::get_value_dropdown_options($host_obj, $controller);
    }

    public function prepare_reference_list_info($host_obj)
    {
        if (!is_null($this->reference_info)) {
            return $this->reference_info;
        }

        $attribute = $host_obj->subcondition;

        // rbanh add
        $this->reference_info = array();
        $this->reference_info['reference_model'] = new ProductProperty();
        $this->reference_info['columns'] = array('name');

        return $this->reference_info = (object)$this->reference_info;
    }

    /**
     * Checks whether the condition is TRUE for specified parameters
     * @param array $params Specifies a list of parameters as an associative array.
     * For example: array('product'=>object, 'shipping_method'=>object)
     * @param mixed $host_obj An object to load the action parameters from
     */
    public function is_true(&$params, $host_obj)
    {
        // This is for CART CONDITION
        if (array_key_exists('cart_items', $params)) {
            $property = $host_obj->subcondition;
            $property_name = preg_replace('/^prop_/', '', $property);
            $property_name = preg_replace('/^attr_/', '', $property); //deprecated

            foreach ($params['cart_items'] as $item) {
                $operator = $host_obj->operator;
                $condition_value = $host_obj->value;
                $val = $item->product->get_property_value($property_name);

                if ($operator == 'is') {
                    if ($val == $condition_value) {
                        return true;
                    }
                }

                if ($operator == 'equals_or_greater') {
                    if ($val >= $condition_value) {
                        return true;
                    }
                }

                if ($operator == 'equals_or_less') {
                    if ($val <= $condition_value) {
                        return true;
                    }
                }

                if ($operator == 'greater') {
                    if ($val > $condition_value) {
                        return true;
                    }
                }

                if ($operator == 'less') {
                    if ($val < $condition_value) {
                        return true;
                    }
                }
            }

            return false;
        } elseif (array_key_exists('product', $params)) {
            // This is for PRODUCT FILTER
            $product = $params['product'];
            $property = $host_obj->subcondition;
            $property_name = preg_replace('/^prop_/', '', $property);
            $property_name = preg_replace('/^attr_/', '', $property); //deprecated

            $operator = $host_obj->operator;
            $condition_value = $host_obj->value;
            $val = $product->get_property_value($property_name);

            if ($operator == 'is') {
                if ($val == $condition_value) {
                    return true;
                }
            }

            if ($operator == 'equals_or_greater') {
                if ($val >= $condition_value) {
                    return true;
                }
            }

            if ($operator == 'equals_or_less') {
                if ($val <= $condition_value) {
                    return true;
                }
            }

            if ($operator == 'greater') {
                if ($val > $condition_value) {
                    return true;
                }
            }

            if ($operator == 'less') {
                if ($val < $condition_value) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    protected function get_condition_text_prefix($parameters_host, $attributes)
    {
        if ($parameters_host->subcondition != 'product') {
            return 'Product.' . $attributes[$parameters_host->subcondition];
        }

        return 'Product';
    }

    protected function list_model_attributes()
    {
        $disableAttr = Phpr::$config->get('DISABLE_ATTR_CONDITIONS', false);
        $disableProp = Phpr::$config->get('DISABLE_PROP_CONDITIONS', false);
        if ($disableAttr || $disableProp) {
            return array();
        }

        if ($this->model_attributes) {
            return $this->model_attributes;
        }

        $query = "
				select 
	    			*
	    		from shop_product_properties
				";

        $re = DbHelper::queryArray($query);

        $properties = array();
        foreach ($re as $r) {
            $properties['prop_' . $r['name']] = 'Product PROP: ' . $r['name'];
        }

        asort($properties);

        return $this->model_attributes = $properties;
    }
}
