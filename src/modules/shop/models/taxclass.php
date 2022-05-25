<?php

namespace Shop;

use Backend;
use Db\ActiveRecord;
use Db\Helper as DbHelper;
use Cms\Controller;
use Phpr\ApplicationException;
use Core\Number;

class TaxClass extends ActiveRecord
{
    public $table_name = 'shop_tax_classes';

    const shipping = 'shipping';

    protected static array $taxClassCache = [];
    protected static $customerContext = null;
    protected static bool $taxExempt = false;
    protected static $shippingTaxClass = null;

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
        $this->define_column('name', 'Name')
            ->order('asc')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('description', 'Description')
            ->validation()
            ->fn('trim');

        $this->define_column('rates', 'Rates')
            ->invisible()
            ->validation()
            ->required();

        $this->define_column('is_default', 'Default');

        $this->define_column('code', 'API Code');

        $this->defined_column_list = array();
        Backend::$events->fireEvent('shop:onExtendTaxClassModel', $this, $context);
        $this->api_added_columns = array_keys($this->defined_column_list);
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('name')
            ->tab('Tax Class');

        $this->add_form_field('description')
            ->comment('Description is optional.', 'above')
            ->size('small')
            ->tab('Tax Class');

        $this->add_form_field('is_default')
            ->comment(
                'Use this checkbox if you want the tax class to be applied to all new products by default.',
                'above'
            )
            ->tab('Tax Class');

        $this->add_form_field('rates')
            ->tab('Rates')
            ->renderAs(frm_widget, [
                'class' => 'Db_GridWidget',
                'sortable' => true,
                'scrollable' => true,
                'scrollable_viewport_class' => 'height-300',
                'csv_file_name' => 'tax-class',
                'columns' => [
                    'country' => [
                        'title' => 'Country Code',
                        'type' => 'text',
                        'autocomplete' => 'remote',
                        'autocomplete_custom_values' => true,
                        'minLength' => 0,
                        'width' => '100'
                    ],
                    'state' => [
                        'title' => 'State Code',
                        'type' => 'text',
                        'width' => '100',
                        'autocomplete' => 'remote',
                        'autocomplete_custom_values' => true,
                        'minLength' => 0
                    ],
                    'zip' => [
                        'title' => 'ZIP',
                        'type' => 'text',
                        'width' => '100'
                    ],
                    'city' => [
                        'title' => 'City',
                        'type' => 'text'
                    ],
                    'rate' => [
                        'title' => 'Rate, %',
                        'type' => 'text',
                        'width' => '60',
                        'align' => 'right'
                    ],
                    'priority' => [
                        'title' => 'Priority',
                        'type' => 'text',
                        'width' => '80',
                        'align' => 'right'
                    ],
                    'tax_name' => [
                        'title' => 'Tax Name',
                        'type' => 'text',
                        'width' => '80'
                    ],
                    'compound' => [
                        'title' => 'Compound',
                        'type' => 'checkbox',
                        'width' => '80'
                    ]
                ]
            ])->noLabel();

        $field = $this->add_form_field('code')
            ->tab('Tax Class');

        if ($this->code == TaxClass::shipping) {
            $field->disabled();
        }


        Backend::$events->fireEvent('shop:onExtendTaxClassForm', $this, $context);
        foreach ($this->api_added_columns as $column_name) {
            $form_field = $this->find_form_field($column_name);
            if ($form_field) {
                $form_field->optionsMethod('get_added_field_options');
                $form_field->optionStateMethod('get_added_field_option_state');
            }
        }
    }

    public function get_added_field_options($db_name, $current_key_value = -1)
    {
        $result = Backend::$events->fireEvent('shop:onGetTaxClassFieldOptions', $db_name, $current_key_value);
        foreach ($result as $options) {
            if (is_array($options) || ($options !== false && $current_key_value != -1)) {
                return $options;
            }
        }
        return false;
    }

    public function get_added_field_option_state($db_name, $key_value)
    {
        $result = Backend::$events->fireEvent('shop:onGetTaxClassFieldState', $db_name, $key_value, $this);
        foreach ($result as $value) {
            if ($value !== null) {
                return $value;
            }
        }
        return false;
    }

    public function get_grid_autocomplete_values($db_name, $column, $term, $row_data)
    {
        if ($column == 'country') {
            return $this->get_country_list($term);
        }

        if ($column == 'state') {
            $country_code = isset($row_data['country']) ? $row_data['country'] : null;
            return $this->get_state_list($country_code, $term);
        }
    }

    protected function get_country_list($term)
    {
        $countries = DbHelper::objectArray(
            'select code, name from shop_countries where name like :term',
            array('term' => $term . '%')
        );
        $result = array();
        $result['*'] = '* - Any country';
        foreach ($countries as $country) {
            $result[$country->code] = $country->code . ' - ' . $country->name;
        }

        return $result;
    }

    protected function get_state_list($country_code, $term)
    {
        $result = array('*' => '* - Any state');

        $states = DbHelper::objectArray('select shop_states.code as state_code, shop_states.name
				from shop_states, shop_countries 
				where shop_states.country_id = shop_countries.id
				and shop_countries.code=:country_code
				and shop_states.name like :term
				order by shop_countries.code, shop_states.name', [
            'country_code' => $country_code,
            'term' => $term . '%'
        ]);

        foreach ($states as $state) {
            $result[$state->state_code] = $state->state_code . ' - ' . $state->name;
        }

        return $result;
    }

    public function before_delete($id = null)
    {
        if ($this->code == TaxClass::shipping) {
            throw new ApplicationException('Cannot delete Shopping tax class');
        }

        if (DbHelper::scalar(
            'select count(*) from shop_products where tax_class_id=:id',
            array('id' => $this->id)
        )) {
            throw new ApplicationException('Cannot delete this tax class, because it is in use.');
        }
    }

    public function before_save($deferred_session_key = null)
    {
        $this->validate_rates();
        $this->rates = serialize($this->rates);
    }

    public function after_save()
    {
        if ($this->is_default) {
            DbHelper::query('update shop_tax_classes set is_default=0 where id<>:id', ['id' => $this->id]);
        }
    }

    protected function get_rate($shipping_info, $priorities_to_ignore = array())
    {
        $shippingCountry = Country::find_by_id($shipping_info->country);
        if (!$shippingCountry) {
            return null;
        }

        $shippingCountryCode = trim(mb_strtoupper($shippingCountry->code));
        $shippingState = $shipping_info->get;
        $shippingStateCode = null;
        if (strlen($shipping_info->state)) {
            $shippingState = CountryState::find_by_id($shipping_info->state);
            $shippingStateCode = $shippingState ? trim(mb_strtoupper($shippingState->code)) : '*';
        }

        $shippingZipCode = trim(strtoupper($shipping_info->zip));
        if (!strlen($shippingZipCode)) {
            $shippingZipCode = '*';
        }

        $shippingCity = trim(mb_strtoupper($shipping_info->city));
        if (!strlen($shippingCity)) {
            $shippingCity = '*';
        }

        $rate = null;
        foreach ($this->rates as $row) {
            $rateValue = $row['rate'];
            $rateName = $row['tax_name'] ?? 'TAX';
            $rateTaxPriority = $row['priority'] ?? 1;
            $rateCountryCode = trim(mb_strtoupper($row['country'] ?? '*'));
            $rateStateCode = trim(mb_strtoupper($row['state'] ?? '*'));
            $rateZipCode = trim(mb_strtoupper($row['zip'] ?? '*'));
            $rateCity = trim(mb_strtoupper($row['city'] ?? '*'));
            $rateCompound = $row['compound'] ?? 0;

            if (in_array($rateTaxPriority, $priorities_to_ignore)) {
                continue;
            }

            $match = str_replace(' ', '', $rateCountryCode) == str_replace(' ', '', $shippingCountryCode);
            if (!$match && $rateCountryCode != '*') {
                continue;
            }

            if ($rateStateCode != $shippingStateCode && $rateStateCode != '*') {
                continue;
            }

            if ($rateZipCode != $shippingZipCode && $rateZipCode != '*') {
                continue;
            }

            $match = str_replace('-', '', $rateCity) == str_replace('-', '', $shippingCity);
            if (!$match && $rateCity != '*') {
                continue;
            }

            if (preg_match('/^[0-9]+$/', $rateCompound)) {
                $rateCompound = (int)$rateCompound;
            } else {
                $rateCompound = $rateCompound == 'Y' || $rateCompound == 'YES';
            }

            $rate_obj = array(
                'rate' => $rateValue,
                'priority' => $rateTaxPriority,
                'name' => $rateName,
                'compound' => $rateCompound
            );

            $rate = (object)$rate_obj;
            break;
        }

        return $rate;
    }

    public function get_tax_information($shipping_info)
    {
        $max_tax_num = 2;
        $priorities_to_ignore = array();
        $added_taxes = array();
        $compound_taxes = array();
        $result = array();

        for ($index = 1; $index <= $max_tax_num; $index++) {
            $tax_info = $this->get_rate($shipping_info, $priorities_to_ignore);
            if (!$tax_info) {
                break;
            }

            if (!$tax_info->compound) {
                $added_taxes[] = $tax_info;
            } else {
                $compound_taxes[] = $tax_info;
            }

            $priorities_to_ignore[] = $tax_info->priority;
        }

        foreach ($added_taxes as $added_tax) {
            $result[] = $added_tax;
        }

        foreach ($compound_taxes as $compound_tax) {
            $result[] = $compound_tax;
        }

        return $result;
    }

    /**
     * Returns tax rates for a specified amount
     * @return array return array of applicable taxes
     */
    public function get_tax_rates($amount, $shipping_info)
    {
        if (self::is_tax_exempt_context()) {
            return array();
        }

        $calculations = Backend::$events->fire_event('shop:onGetTaxRates', array(
            'amount' => $amount,
            'shipping_info' => $shipping_info
        ));

        foreach ($calculations as $calculation) {
            if ($calculation) {
                return $calculation;
            }
        }

        $max_tax_num = 2;
        $priorities_to_ignore = array();
        $added_taxes = array();
        $compound_taxes = array();
        for ($index = 1; $index <= $max_tax_num; $index++) {
            $tax_info = $this->get_rate($shipping_info, $priorities_to_ignore);
            if (!$tax_info) {
                break;
            }

            if (!$tax_info->compound) {
                $added_taxes[] = $tax_info;
            } else {
                $compound_taxes[] = $tax_info;
            }

            $priorities_to_ignore[] = $tax_info->priority;
        }

        $added_result = $amount;
        $result = array();
        foreach ($added_taxes as $added_tax) {
            $tax_info = array();
            $tax_info['name'] = $added_tax->name;
            $tax_info['tax_rate'] = $added_tax->rate / 100;
            $added_result += $tax_info['rate'] = $amount * ($added_tax->rate / 100);
            $tax_info['total'] = $tax_info['rate'];
            $tax_info['added_tax'] = true;
            $tax_info['compound_tax'] = false;

            $result[] = (object)$tax_info;
        }

        foreach ($compound_taxes as $compound_tax) {
            $tax_info = array();
            $tax_info['name'] = $compound_tax->name;
            $tax_info['tax_rate'] = $compound_tax->rate / 100;
            $tax_info['rate'] = $added_result * ($compound_tax->rate / 100);
            $tax_info['total'] = $tax_info['rate'];
            $tax_info['compound_tax'] = true;
            $tax_info['added_tax'] = false;

            $result[] = (object)$tax_info;
        }

        return $result;
    }

    public static function get_tax_rates_static($tax_class_id, $shipping_info)
    {
        if (!array_key_exists($tax_class_id, self::$taxClassCache)) {
            self::$taxClassCache[$tax_class_id] = self::create()->find($tax_class_id);
        }

        return self::$taxClassCache[$tax_class_id]->get_tax_rates(1, $shipping_info);
    }

    public static function get_shipping_tax_class()
    {
        if (self::$shippingTaxClass === null) {
            self::$shippingTaxClass = self::create()->find_by_code(self::shipping);
        }

        return self::$shippingTaxClass;
    }

    public static function get_shipping_tax_rates($shipping_option_id, $shipping_info, $shipping_quote)
    {
        if (self::is_tax_exempt_context()) {
            return array();
        }

        if (!ShippingOption::is_taxable($shipping_option_id)) {
            return array();
        }

        $obj = self::get_shipping_tax_class();
        if (!$obj) {
            return array();
        }

        return $obj->get_tax_rates($shipping_quote, $shipping_info);
    }

    public static function eval_total_tax($tax_list)
    {
        $result = 0;

        if (!$tax_list) {
            return $result;
        }

        foreach ($tax_list as $tax) {
            $result += $tax->rate;
        }

        return $result;
    }

    public static function find_by_id($id)
    {
        if (isset(self::$taxClassCache[$id])) {
            return self::$taxClassCache[$id];
        }

        return self::$taxClassCache[$id] = self::create()->find($id);
    }

    /**
     * Calculates individual and total taxes for a specific list of cart items
     *
     * @param mixed $cart_items collection of CartItem or ShopOrderItem
     * @param AddressInfo $shipping_info Shipping address info
     * @param array $context_params Context parameters can be used to pass additional considerations
     *  to the shop:onCalculateTaxes event.
     *
     * @return object Standard object containing result properties:
     *  tax_total (float) , taxes (array) , item_taxes (array)
     */
    public static function calculate_taxes($cart_items, $shipping_info, $context_params = array())
    {
        //Compatibility with legacy parameter
        $backend_call = null;
        if (!is_array($context_params)) {
            $backend_call = $context_params ? true : null;
        }

        $default_context_params = array(
            'cart_name' => 'main',
            'order' => null,
            'backend_call' => $backend_call
        );

        $context_params = array_merge($default_context_params, $context_params);

        $result = (object)array(
            'tax_total' => 0,
            'taxes' => array(),
            'item_taxes' => array()
        );

        if (self::is_tax_exempt_context()) {
            return $result;
        }

        $calculations = Backend::$events->fire_event('shop:onCalculateTaxes', array(
            'cart_items' => $cart_items,
            'shipping_info' => $shipping_info,
            'context_params' => $context_params
        ));

        foreach ($calculations as $calculation) {
            if ($calculation) {
                return $calculation;
            }
        }

        $item_taxes = array();
        $taxes = array();
        $tax_total = 0;

        foreach ($cart_items as $item_index => $item) {
            $tax_class = self::find_by_id($item->get_tax_class_id());
            if ($tax_class) {
                $this_item_price = 0;
                if ($item instanceof CartItem) {
                    $this_item_price = $item->get_offer_price();
                } else {
                    $this_item_price = $item->eval_single_price() - $item->discount;
                }

                $this_item_taxes = $tax_class->get_tax_rates($this_item_price, $shipping_info);

                $item_taxes[$item_index] = $this_item_taxes;

                foreach ($this_item_taxes as $tax) {
                    $key = $tax_class->id . '|' . $tax->name;

                    if (!array_key_exists($key, $taxes)) {
                        $effective_rate = $tax->tax_rate;

                        if ($tax->compound_tax) {
                            $added_tax = self::find_added_tax($this_item_taxes);
                            if ($added_tax) {
                                $effective_rate = $tax->tax_rate * (1 + $added_tax->tax_rate);
                            }
                        }

                        $taxes[$key] = array(
                            'total' => 0,
                            'rate' => $tax->rate,
                            'effective_rate' => $effective_rate,
                            'name' => $tax->name,
                            'tax_amount'
                        );
                    }

                    $item_tax_value = $this_item_price * $item->quantity;

                    $taxes[$key]['total'] += $item_tax_value;
                }
            }
        }

        $compound_taxes = array();

        foreach ($taxes as $tax_total_info) {
            if (!array_key_exists($tax_total_info['name'], $compound_taxes)) {
                $tax_data = array('name' => $tax_total_info['name'], 'total' => 0);
                $compound_taxes[$tax_total_info['name']] = (object)$tax_data;
            }

            $tax_value = $tax_total_info['total'] * $tax_total_info['effective_rate'];
            $compound_taxes[$tax_total_info['name']]->total += $tax_value;

            $tax_total += $tax_value;
        }


        $result->tax_total = $tax_total;
        $result->taxes = $compound_taxes;
        $result->item_taxes = $item_taxes;

        return $result;
    }

    protected static function find_added_tax($tax_list)
    {
        foreach ($tax_list as $tax) {
            if ($tax->added_tax) {
                return $tax;
            }
        }

        return null;
    }

    protected function validate_rates()
    {
        if (!is_array($this->rates) || !count($this->rates)) {
            $this->field_error('rates', 'Please specify tax rates.');
        }

        /*
             * Preload countries and states
         */

        $db_country_codes = DbHelper::objectArray('select * from shop_countries order by code');
        $countries = array();
        foreach ($db_country_codes as $country) {
            $countries[$country->code] = $country;
        }

        $country_codes = array_merge(array('*'), array_keys($countries));
        $db_states = DbHelper::objectArray('select * from shop_states order by code');

        $states = array();
        foreach ($db_states as $state) {
            if (!array_key_exists($state->country_id, $states)) {
                $states[$state->country_id] = array('*' => null);
            }

            $states[$state->country_id][mb_strtoupper($state->code)] = $state;
        }

        foreach ($countries as $country) {
            if (!array_key_exists($country->id, $states)) {
                $states[$country->id] = array('*' => null);
            }
        }

        /*
             * Validate table rows
         */

        $rate_list = $this->rates;

        $is_manual_disabled = isset($rate_list['disabled']);
        if ($is_manual_disabled) {
            $rate_list = unserialize($rate_list['serialized']);
        }

        $processed_rates = array();

        $line_number = 0;
        foreach ($rate_list as $row_index => &$rates) {
            $line_number++;

            $empty = true;
            foreach ($rates as $value) {
                if (strlen(trim($value))) {
                    $empty = false;
                    break;
                }
            }

            if ($empty) {
                continue;
            }

            /*
                 * Validate country
             */
            $country = $rates['country'] = trim(mb_strtoupper($rates['country']));
            if (!strlen($country)) {
                $this->field_error('rates', 'Please specify country code. Valid codes are: ' . implode(
                        ', ',
                        $country_codes
                    ) . '. Line: ' . $line_number, $row_index, 'country');
            }

            if (!array_key_exists($country, $countries) && $country != '*') {
                $this->field_error('rates', 'Invalid country code. Valid codes are: ' . implode(
                        ', ',
                        $country_codes
                    ) . '. Line: ' . $line_number, $row_index, 'country');
            }

            /*
                 * Validate state
             */
            if ($country != '*') {
                $country_obj = $countries[$country];
                $country_states = $states[$country_obj->id];
                $state_codes = array_keys($country_states);

                $state = $rates['state'] = trim(mb_strtoupper($rates['state']));
                if (!strlen($state)) {
                    $this->field_error(
                        'rates',
                        'Please specify state code. State codes, valid for ' . $country_obj->name . ' are: ' . implode(
                            ', ',
                            $state_codes
                        ) . '. Line: ' . $line_number,
                        $row_index,
                        'state'
                    );
                }

                if (!in_array($state, $state_codes) && $state != '*') {
                    $this->field_error(
                        'rates',
                        'Invalid state code. State codes, valid for ' . $country_obj->name . ' are: ' . implode(
                            ', ',
                            $state_codes
                        ) . '. Line: ' . $line_number,
                        $row_index,
                        'state'
                    );
                }
            } else {
                $state = $rates['state'] = trim(mb_strtoupper($rates['state']));
                if (!strlen($state) || $state != '*') {
                    $this->field_error(
                        'rates',
                        'Please specify state code as wildcard (*) to indicate "Any state" condition. 
                          Line: ' . $line_number,
                        $row_index,
                        'state'
                    );
                }
            }

            /*
                 * Validate rate
             */

            $rate = $rates['rate'] = trim(mb_strtoupper($rates['rate']));
            if (!strlen($rate)) {
                $this->field_error('rates', 'Please specify rate. Line: ' . $line_number, $row_index, 'rate');
            }

            if (!Number::isValidFloat($rate)) {
                $this->field_error(
                    'rates',
                    'Invalid numeric value in column Rate. Line: ' . $line_number,
                    $row_index,
                    'rate'
                );
            }

            /*
                 * Validate priority
             */

            $priority = $rates['priority'] = trim(mb_strtoupper($rates['priority']));
            if (!strlen($priority)) {
                $this->field_error('rates', 'Please specify priority. Line: ' . $line_number, $row_index, 'priority');
            }

            if (!Number::isValidFloat($priority)) {
                $this->field_error(
                    'rates',
                    'Invalid numeric value in column Priority. Line: ' . $line_number,
                    $row_index,
                    'priority'
                );
            }

            /*
                 * Validate compound
             */

            $compound = $rates['compound'] = trim(mb_strtoupper($rates['compound']));
            if (strlen($compound)) {
                if (preg_match('/^[0-9]+$/', $compound)) {
                    $compound = (int)$compound;
                } else {
                    $compound = strtoupper($compound);
                }

                $booley = [
                    'Y',
                    'N',
                    'YES',
                    'NO',
                    0,
                    1
                ];

                if (!in_array($compound, $booley)) {
                    $info = implode(',', $booley) . '. Line: ' . $line_number;
                    $this->field_error(
                        'rates',
                        'Invalid Boolean value in column Compound. Please use the following values: ' . $info,
                        $row_index,
                        'compound'
                    );
                }
            }

            /*
                 * Validate tax name
             */

            $tax_name = $rates['tax_name'] = trim(mb_strtoupper($rates['tax_name']));
            if (!strlen($tax_name)) {
                $this->field_error('rates', 'Please specify tax name', $row_index, 'tax_name');
            }

            $rates['zip'] = trim(mb_strtoupper($rates['zip']));
            $rates['city'] = trim($rates['city']);

            $processed_rates[] = $rates;
        }

        if (!count($processed_rates)) {
            $this->field_error('rates', 'Please specify tax rates.');
        }

        $this->rates = $processed_rates;
    }

    protected function field_error($field, $message, $grid_row = null, $grid_column = null)
    {
        if ($grid_row != null) {
            // $rule = $this->validation->getRule($field);
            // if ($rule)
            //  $rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
            $this->validation->setWidgetData(Db_GridWidget::get_cell_error_data(
                $this,
                'rates',
                $grid_column,
                $grid_row
            ));
        }

        $this->validation->setError($message, $field, true);
    }

    protected function after_fetch()
    {
        $this->rates = strlen($this->rates) ? unserialize($this->rates) : array();
    }

    public static function get_default_class_id()
    {
        return DbHelper::scalar('select id from shop_tax_classes where is_default=1');
    }

    /**
     * Returns total tax value for a specific tax class and amount
     */
    public static function get_total_tax($tax_class_id, $amount)
    {
        if (self::is_tax_exempt_context()) {
            return 0;
        }

        if (!array_key_exists($tax_class_id, self::$taxClassCache)) {
            self::$taxClassCache[$tax_class_id] = self::create()->find($tax_class_id);
        }

        $tax_class = self::$taxClassCache[$tax_class_id];

        if (!$tax_class) {
            return 0;
        }

        $taxes = $tax_class->get_tax_rates($amount, CheckoutData::get_shipping_info());

        $result = 0;
        foreach ($taxes as $tax) {
            $result += $tax->tax_rate * $amount;
        }

        return $result;
    }

    /**
     * Returns subtotal value for specified total amount and tax class
     */
    public static function get_subtotal($tax_class_id, $total, $shipping_info = null)
    {
        if (self::is_tax_exempt_context()) {
            return $total;
        }

        if (!array_key_exists($tax_class_id, self::$taxClassCache)) {
            self::$taxClassCache[$tax_class_id] = self::create()->find($tax_class_id);
        }

        $tax_class = self::$taxClassCache[$tax_class_id];

        if (!$shipping_info) {
            $shipping_info = CheckoutData::get_shipping_info();
        }

        $max_tax_num = 2;
        $priorities_to_ignore = array();
        $added_taxes = array();
        $compound_taxes = array();
        for ($index = 1; $index <= $max_tax_num; $index++) {
            $tax_info = $tax_class->get_rate($shipping_info, $priorities_to_ignore);
            if (!$tax_info) {
                break;
            }

            if (!$tax_info->compound) {
                $added_taxes[] = $tax_info;
            } else {
                $compound_taxes[] = $tax_info;
            }

            $priorities_to_ignore[] = $tax_info->priority;
        }

        /*
             * No applicable taxes case
         */
        if (!$added_taxes && !$compound_taxes) {
            return $total;
        }

        if ($added_taxes && !$compound_taxes) {
            /*
                 * No compound taxes case
             */

            if (count($added_taxes) == 1) {
                /*
                     * A single added tax case
                 */
                return $total / (1 + $added_taxes[0]->rate / 100);
            }

            /*
                 * Two added taxes case
             */

            return $total / (1 + $added_taxes[0]->rate / 100 + $added_taxes[1]->rate / 100);
        }

        if (!count($added_taxes)) {
            /*
                 * No added taxes case (there should be no such cases)
             */
            if (count($compound_taxes) == 2) {
                return $total / ((1 + $compound_taxes[0]->rate / 100) * (1 + $compound_taxes[1]->rate / 100));
            }
            return $total / (1 + $compound_taxes[0]->rate / 100);
        }

        /*
             * Single added tax + single compound tax case
         */
        return $total / ((1 + $added_taxes[0]->rate / 100) * (1 + $compound_taxes[0]->rate / 100));
    }

    /**
     * Combines two arrays of taxes by tax name
     * You can pass arrays of shipping and sales taxes to the method
     * @return array Returns an array of tax information objects. Each object has the name and total fields
     */
    public static function combine_taxes_by_name($tax_array1, $tax_array2)
    {
        $result = array();

        $tax_array = array();
        foreach ($tax_array1 as $tax_info) {
            $tax_array[] = $tax_info;
        }

        foreach ($tax_array2 as $tax_info) {
            $tax_array[] = $tax_info;
        }

        foreach ($tax_array as $tax_info) {
            $tax_name = $tax_info->name;
            if (!array_key_exists($tax_name, $result)) {
                $info = array('name' => $tax_name, 'total' => 0);
                $result[$tax_name] = (object)$info;
            }
            $result[$tax_name]->total += $tax_info->total;
        }

        return $result;
    }

    public function after_modify($operation, $deferred_session_key)
    {
        Module::update_catalog_version();
    }

    /**
     * Sets the customer context. All tax calculations are referring this customer for applying the tax exempt rules.
     * If the customer context is not set, the current front-end customer used.
     */
    public static function set_customer_context($customer)
    {
        self::$customerContext = $customer;
    }

    /**
     * Determines whether the tax exempt mode should be applied.
     */
    public static function set_tax_exempt($value)
    {
        self::$taxExempt = $value;
    }

    /**
     * Returns a sum of a given amount and its total tax.
     * @param integer $tax_class_id Specifies tax class identifier.
     * @param float $price Specifies amount to apply the tax to.
     * @return float Returns a sum of the amount and its total tax
     */
    public static function apply_tax($tax_class_id, $price)
    {
        return self::get_total_tax($tax_class_id, $price) + $price;
    }

    /**
     * Returns a sum of a given amount and its total tax,
     * if the "Display catalog/cart prices including tax" option is enabled.
     * If the option is disabled, returns the unchanged amount.
     * @param integer $tax_class_id Specifies tax class identifier.
     * @param float $price Specifies amount to apply the tax to.
     * @return float Returns a sum of the amount and its total tax
     */
    public static function apply_tax_conditional($tax_class_id, $price)
    {
        $include_tax = CheckoutData::display_prices_incl_tax();
        if (!$include_tax) {
            return $price;
        }

        return self::apply_tax($tax_class_id, $price);
    }

    /**
     * Resets internal class caches
     */
    public static function reset_cache()
    {
        self::$taxClassCache = array();
        self::$customerContext = null;
        self::$taxExempt = false;
        self::$shippingTaxClass = null;
    }

    protected static function is_tax_exempt_context()
    {
        if (self::$taxExempt) {
            return true;
        }

        if (!self::$customerContext) {
            $group = Controller::get_customer_group();
            if (!$group) {
                return false;
            }

            return $group->tax_exempt;
        }

        return CustomerGroup::is_tax_exempt(self::$customerContext->customer_group_id);
    }
}