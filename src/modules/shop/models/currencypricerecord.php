<?php
namespace Shop;

use Db\ActiveRecord;
use Db\Helper as DbHelper;
use Phpr\SystemException;

class CurrencyPriceRecord extends ActiveRecord
{

    public $table_name = 'shop_currency_price_records';

    public function __construct($values = null, $options = array())
    {
        parent::__construct($values, $options);
    }

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
    }

    public function apply_related(ActiveRecord $model)
    {
        $this->where('master_object_id = ?', $model->id);
        $this->where('master_object_class = ?', get_class_id($model));
    }

    public function apply_field($field)
    {
        $this->where('master_field_name = ?', $field);
    }

    protected function validate_currency_field(ActiveRecord $model, $field)
    {
        $column_definitions = $model->get_column_definitions();

        if (!array_key_exists($field, $column_definitions)) {
            throw new SystemException(
                'Cannot execute method "getPrice" for field '.$field.', it is not defined in the given model.'
            );
        }

        $columnDefinition = $column_definitions[$field];
        $is_price_field = isset($column_definitions[$field]->currency) ? $column_definitions[$field]->currency : false;
        if (!$is_price_field) {
            throw new SystemException(
                'Cannot execute method "getPrice" for field '.$field.', it is not defined as a currency price field.'
            );
        }
    }

    public static function find_record($model, $field, $currency_code)
    {
        $params = array(
            'currency_code' => $currency_code
        );
        $records = self::find_records($model, $field, $params);
        if ($records) {
            if (is_a($records, 'Shop\CurrencyPriceRecord')) {
                return $record = $records;
            } else {
                return $records->first();
            }
        }
        return null;
    }

    public static function find_records($model, $field, $options = array())
    {
        $default_options = array(
            'currency_code' => null,
            'currency_id' => null,
            'validate_currency_field' => true
        );
        $options = array_merge($default_options, $options);

        $records = self::create();

        if ($options['validate_currency_field']) {
            $records->validate_currency_field($model, $field);
        }

        $records->apply_related($model);
        $records->apply_field($field);

        if ($options['currency_code']) {
            $records->join(
                'shop_currency_settings',
                'shop_currency_price_records.currency_id=shop_currency_settings.id'
            );
            $records->where(
                'shop_currency_settings.code = :code || shop_currency_settings.iso_4217_code = :code',
                ['code'=> $options['currency_code']]
            );
        }

        if ($options['currency_id']) {
            $records->where('shop_currency_price_records.currency_id = ?', $options['currency_id']);
        }

        return $records->find_all();
    }

    public static function assign_deferred_bindings($model, $session_key)
    {
        $bind = array(
            'moid' => $model->id,
            'dsk' => $session_key,
            'moc' => get_class_id($model)
        );
        $sql = "UPDATE shop_currency_price_records 
				SET master_object_id = :moid,
				deferred_session_key = NULL
				WHERE deferred_session_key = :dsk
				AND master_object_class = :moc";
        DbHelper::query($sql, $bind);
    }
}
