<?php

namespace Shop;

use Db\ActiveRecord;
use Db\Helper as DbHelper;
use Phpr\ApplicationException;

/**
 * Represents a country.
 * @documentable
 * @property int $id Specifies the country record identifier.
 * @property string $name Specifies the country name.
 * @property string $code Specifies a two symbol country code (US).
 * @property string $code_3 Specifies a three symbol country code (USA).
 * @property string $code_iso_numeric Specifies a three digit country code (840).
 * @property \Db\DataCollection $states A list of the country states.
 * Each object in the collection is an instance of {@link CountryState} class.
 * @property bool $enabled Indicates whether the country is enabled on the front-end.
 * @property bool $enabled_in_backend Indicates whether the country is enabled on the Administration Area.
 * @see CountryState
 * @package shop.models
 * @author LSAPP - MJMAN
 */
class Country extends ActiveRecord
{
    public $table_name = 'shop_countries';

    public $enabled = 1;
    public $enabled_in_backend = 1;

    protected static $simple_object_list = null;
    protected static $simple_name_list = null;
    protected static $id_cache = array();

    public $has_many = array(
        'states' => array(
            'class_name' => 'Shop\CountryState',
            'foreign_key' => 'country_id',
            'conditions' => '(shop_states.disabled IS NULL)',
            'order' => 'shop_states.name',
            'delete' => true
        )
    );

    public $belongs_to = array(
        'shipping_zone' => array(
            'class_name' => 'Shop\ShippingZone',
            'foreign_key' => 'shipping_zone_id',
            'conditions' => '(shop_shipping_zones.params_id IS NOT NULL)'
        ),
    );

    /**
     * Creates an object of the class.
     * You can use this method with <em>find_by_code()</em> method to load a country by its code:
     * <pre>$usa = Country::create()->find_by_code('US');</pre>
     * @documentable
     * @param bool $no_column_info Determines whether
     *  {@link \Db\ColumnDefinition column objects} should be loaded into the memory.
     * @return Country Returns the country object.
     */
    public static function create($no_column_info = false)
    {
        if (!$no_column_info) {
            return new self();
        } else {
            return new self(null, array('no_column_init' => true, 'no_validation' => true));
        }
    }

    public function define_columns($context = null)
    {
        $this->define_column('name', 'Name')
            ->order('asc')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('code', '2-digit ISO country code')
            ->validation()->fn('trim')
            ->required()
            ->maxLength(2, '2-digit ISO country code must contain exactly 2 letters.')
            ->regexp('/^[a-z]{2}$/i', 'Country code must contain 2 Latin letters')
            ->fn('mb_strtoupper');

        $this->define_column('code_3', '3-digit ISO country code')
            ->validation()
            ->fn('trim')
            ->required()
            ->maxLength(3, '3-digit ISO country code must contain exactly 3 letters.')
            ->regexp('/^[a-z]{3}$/i', 'Country code must contain 3 Latin letters')
            ->fn('mb_strtoupper');

        $this->define_column('code_iso_numeric', 'Numeric ISO country code')
            ->validation()
            ->fn('trim')
            ->required()
            ->maxLength(3, 'Numeric ISO country code must contain exactly 3 digits.')
            ->regexp('/^[0-9]{3}$/i', 'Country code must contain 3 digits')
            ->fn('mb_strtoupper');

        $this->define_column('enabled', 'Enabled')
            ->validation();

        $this->define_column('enabled_in_backend', 'Enabled in the Administration Area')
            ->listTitle('Enabled in AA')
            ->validation();

        $this->define_column('currency_code', 'Currency')
            ->defaultInvisible()
            ->validation()
            ->fn('trim')
            ->maxLength(3, 'ISO currency code must contain exactly 3 letters.')
            ->fn('mb_strtoupper');

        $front_end = ActiveRecord::$execution_context == 'front-end';
        if (!$front_end) {
            $this->define_multi_relation_column('states', 'states', 'States', "@name")->invisible();
        }
        $this->define_relation_column(
            'shipping_zone',
            'shipping_zone',
            'Shipping Zone ',
            db_varchar,
            '@name'
        )
            ->listTitle('Shipping Zone')
            ->defaultInvisible();
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('name')
            ->tab('Country');

        $field = $this->add_form_field('code');
        $field->tab('Country');
        if ($context != 'preview') {
            $field->comment(
                'Specify 2-letter country code. You can find country names and codes here: 
                      <a href="https://en.wikipedia.org/wiki/ISO_3166-1" target="_blank">
                          https://en.wikipedia.org/wiki/ISO_3166-1
                      </a>',
                'above',
                true
            );
        } else {
            $field->comment(
                '2-letter country code. You can find country names and codes here: 
                https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2',
                'above'
            );
        }

        $field = $this->add_form_field('code_3', 'left')->tab('Country');
        if ($context != 'preview') {
            $field->comment('Specify 3-letter country code, for example USA.', 'above', true);
        } else {
            $field->comment('3-letter country code.', 'above');
        }

        $field = $this->add_form_field('code_iso_numeric', 'right')->tab('Country');
        if ($context != 'preview') {
            $field->comment('Specify 3-digit numeric country code, for example 840 for USA.', 'above', true);
        } else {
            $field->comment('3-digit numeric country code.', 'above');
        }

        $this->add_form_field('enabled')
            ->tab('Country')
            ->comment('Disabled countries are not shown on the front-end store.', 'above');

        $field = $this->add_form_field('enabled_in_backend');
        $field->tab('Country');
        $field->comment('Use this checkbox if you want the country to be enabled in the Administration Area.', 'above');
        if ($this->enabled) {
            $field->disabled();
        }

        $this->add_form_field('states')->tab('States');

        $this->add_form_field('shipping_zone')
            ->tab('Shipping Zone')
            ->comment("You can manage shipping zones from the 'Settings -> Shipping Settings' page.", 'above');

        $this->add_form_field('currency_code')
            ->renderAs(frm_dropdown)
            ->tab('Currency')
            ->comment("Assign a currency code for this country.", 'above');
    }

    public function get_shipping_zone_options($key_value = -1)
    {
        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            $obj = ShippingZone::create()->find($key_value);
            return $obj ? $obj->name : null;
        }

        $options = array(null => '<please select>');
        $zones = ShippingZone::create()->where('(shop_shipping_zones.params_id IS NOT NULL)')->find_all();
        $zones_array = $zones->as_array('name', 'id');
        foreach ($zones_array as $id => $name) {
            $options[$id] = $name;
        }
        return $options;
    }

    public function get_currency_code_options($key_value = -1)
    {
        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            $obj = CurrencySettings::create()->find_by_code($key_value);
            return $obj ? $obj->name : null;
        }

        $options = array(null => '<optional select>');
        $currencySettings = new CurrencySettings();
        $currencies = $currencySettings->find_all();
        $currencies_array = $currencies->as_array('name', 'code');
        foreach ($currencies_array as $id => $name) {
            $options[$id] = $name;
        }
        return $options;
    }

    public function before_delete($id = null)
    {
        $bind = array('id' => $this->id);
        $in_use = DbHelper::scalar(
            'select count(*) from shop_customers where shipping_country_id=:id or billing_country_id=:id',
            $bind
        );

        if ($in_use) {
            throw new ApplicationException("Cannot delete country because it is in use.");
        }

        $in_use = DbHelper::scalar(
            'select count(*) from shop_orders where shipping_country_id=:id or billing_country_id=:id',
            $bind
        );

        if ($in_use) {
            throw new ApplicationException("Cannot delete country because it is in use.");
        }
    }

    public static function get_list($country_id = null)
    {
        $obj = new self(null, array('no_column_init' => true, 'no_validation' => true));
        $obj->order('name')->where('enabled = 1');
        if (strlen($country_id)) {
            $obj->orWhere('id=?', $country_id);
        }

        return $obj->find_all();
    }

    /**
     * Lists states assigned to this country as a data array. Disabled states are excluded.
     * This fetches a data array without using model relations
     * which is useful to reduce relation/model loads and required memory usage.
     *
     * @param mixed $include_state_id A CountryState ID can be provided to force a state selection
     *  to be included even when the record is disabled
     *
     * @return mixed Array of data objects if found, otherwise NULL
     */
    public function list_states($include_state_id = null)
    {
        $result = null;
        $state_conditional = $include_state_id ? "OR id = :state_id" : null;
        if ($this->id) {
            $sql = 'SELECT * FROM shop_states 
                    WHERE country_id=:country_id 
                    AND (disabled IS NULL ' . $state_conditional . ') 
                    ORDER BY `name`';

            $states = DbHelper::objectArray($sql, [
                'country_id' => $this->id,
                'state_id' => $include_state_id
            ]);

            if (count($states)) {
                $result = $states;
            }
        }

        return $result;
    }

    /**
     * Returns a list of state relations as an array mapping state ID to state NAME
     *
     * @param mixed $include_state_id A CountryState ID can be provided to guarantee an assigned State record
     *  is included even if that record has since been disabled
     *
     * @return array|string[]
     */
    public function get_state_options($include_state_id = null)
    {
        $result = array(null => '<no states available>');
        $states = $this->list_states($include_state_id);

        if (is_array($states) && count($states)) {
            $result = array();
            foreach ($states as $state) {
                $result[$state->id] = $state->name;
            }
        }
        return $result;
    }

    public function update_enabled_status($enabled, $enabled_in_backend)
    {
        if ($this->enabled != $enabled || $this->enabled_in_backend != $enabled_in_backend) {
            $this->enabled = $enabled;
            $this->enabled_in_backend = $enabled_in_backend;

            $this->save();
        }
    }

    public static function get_object_list($default = -1)
    {
        if (self::$simple_object_list && !$default) {
            return self::$simple_object_list;
        }

        $records = DbHelper::objectArray(
            'select * from shop_countries where enabled_in_backend=1 or id=:id order by name',
            ['id' => $default]
        );
        $result = array();
        foreach ($records as $country) {
            $result[$country->id] = $country;
        }

        if (!$default) {
            return self::$simple_object_list = $result;
        } else {
            return $result;
        }
    }

    public static function get_name_list()
    {
        if (self::$simple_name_list) {
            return self::$simple_name_list;
        }

        $countries = self::get_object_list();
        $result = array();
        foreach ($countries as $id => $country) {
            $result[$id] = $country->name;
        }

        return self::$simple_name_list = $result;
    }

    /**
     * Loads a country by its identifier.
     * This method uses internal memory cache.
     * It is preferable to use this method for loading country objects.
     * @documentable
     * @param int $id Specifies the country identifier.
     * @return Country Returns a country object or NULL if the country is not found.
     */
    public static function find_by_id($id)
    {
        if (array_key_exists($id, self::$id_cache)) {
            return self::$id_cache[$id];
        }

        return self::$id_cache[$id] = self::create(true)->find($id);
    }

    public function before_save($deferred_session_key = null)
    {
        if ($this->enabled) {
            $this->enabled_in_backend = 1;
        }
    }

    /**
     * @param $enabled
     * @param $enabled_in_backend
     * @deprecated
     */
    public function update_states($enabled, $enabled_in_backend)
    {
        $this->update_enabled_status($enabled, $enabled_in_backend);
    }
}
