<?php
namespace Shop;

class RuleCompoundConditionBase extends RuleConditionBase
{
    public function get_join_text($parameters_host)
    {
        return null;
    }
        
    /**
     * Returns a list of condition types (RuleConditionBase::type_ constants),
     * which can be added to this compound condition
     */
    public function get_allowed_subtypes()
    {
        return array();
    }
        
    /**
     * Returns a condition title for displaying in the condition settings form
     */
    public function get_title($host_obj)
    {
        return "Compound condition";
    }
        
    protected function add_classes_subconditions($classes, &$list)
    {
        foreach ($classes as $condition_class) {
            $obj = new $condition_class();
            $sub_conditions = $obj->list_subconditions();
            if ($sub_conditions) {
                $group_name = $obj->get_grouping_title();
                foreach ($sub_conditions as $name => $subcondition) {
                    if (!$group_name) {
                        $list[$name] = $condition_class.':'.$subcondition;
                    } else {
                        if (!array_key_exists($group_name, $list)) {
                            $list[$group_name] = array();
                        }
                                
                        $list[$group_name][$name] = $condition_class.':'.$subcondition;
                    }
                }
            } else {
                $list[$obj->get_name()] = $condition_class;
            }
        }
    }
        
    public function get_child_options($rule_type, $parent_ids)
    {
        $result = array('Compound condition'=> get_class_id('Shop\RuleIfCondition'));

        if ($rule_type == PriceRuleCondition::type_catalog) {
            $classes = self::find_conditions_by_type(RuleConditionBase::type_product);
            $this->add_classes_subconditions($classes, $result);
        } elseif ($rule_type == PriceRuleCondition::type_cart) {
            $root_type_condition = true;
            $container_obj = null;
            foreach ($parent_ids as $parent_id) {
                $parent_obj = PriceRuleCondition::create()->find($parent_id);
                if ($parent_obj) {
                    $parentClassId = get_class_id($parent_obj->class_name);
                    if ($parentClassId != get_class_id('Shop\RuleIfCondition')) {
                        $container_obj = $parent_obj;
                        $root_type_condition = false;
                        break;
                    }
                }
            }

            if ($root_type_condition) {
                $allowed_types = array(RuleConditionBase::type_cart_root, RuleConditionBase::type_cart_attribute);
            } else {
                $container_condition = $container_obj->get_condition_obj();
                $allowed_types = $container_condition->get_allowed_subtypes();
            }
                
            foreach ($allowed_types as $type) {
                $classes = self::find_conditions_by_type($type);
                $this->add_classes_subconditions($classes, $result);
            }
        } elseif ($rule_type == PriceRuleCondition::type_cart_products) {
            $allowed_types = array(RuleConditionBase::type_cart_product_attribute, RuleConditionBase::type_product);
            foreach ($allowed_types as $type) {
                $classes = self::find_conditions_by_type($type);
                $this->add_classes_subconditions($classes, $result);
            }
        }
            
        return $result;
    }
}
