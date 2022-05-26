<?php
namespace Shop;

use Db\DataFilter;

class CustomerBillingCountryFilter extends DataFilter
{
    public $model_class_name = 'Shop\Country';
    public $model_filters = 'enabled_in_backend=1';
    public $list_columns = array('name');

    public function applyToModel($model, $keys, $context = null)
    {

        if (is_a($model, 'Shop\Customer')) {
            $model->where('shop_customers.billing_country_id IN (?)', array($keys));
        } elseif ($model->belongs_to) {
            foreach ($model->belongs_to as $field => $belongs_to_info) {
                $class_name = $belongs_to_info['class_name'] ?? null;
                if (get_class_id($class_name) == get_class_id('Shop\Customer')) {
                    $foreign_key = $belongs_to_info['foreign_key'];
                    if ($foreign_key) {
                        $model->where($field.'_calculated_join.billing_country_id IN (?)', array($keys));
                    }
                }
            }
        }
    }

    public function asString($keys, $context = null)
    {
        return 'and shop_customers.billing_country_id in '.$this->keysToStr($keys);
    }
}
