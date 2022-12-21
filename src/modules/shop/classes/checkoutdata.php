<?php

namespace Shop;

use Phpr;
use Backend;
use Cms\Exception as CmsException;
use Cms\Controller as Controller;
use Phpr\Validation as Validation;
use Phpr\ApplicationException;
use Db\Helper as DbHelper;

/**
 * Contains information collected during the checkout process.
 * The class acts as an internal checkout data storage.
 * It has methods for setting and loading the checkout information,
 * along with a method for placing a new order.
 * It allows to implement custom checkout scenarios. The default {@link action@shop:checkout actions}
 * use this class internally.
 * @documentable
 * @see action@shop:checkout
 * @author LSAPP - MJMAN
 * @package shop.classes
 */
class CheckoutData
{
    protected static $customerOverride = null;

    /**
     * Loads shipping and billing address information from a customer object.
     * By default this method doesn't update the address information if it
     * has already been set in the checkout data object. Pass TRUE value
     * to the <em>$force</em> parameter to override any existing data.
     * @documentable
     * @param Customer $customer Specifies the customer object to load data from.
     * @param boolean $force Determines whether any existing data should be overridden.
     */
    public static function load_from_customer($customer, $force = false)
    {
        $checkout_data = self::load();
        if (array_key_exists('billing_info', $checkout_data) && !$force) {
            return;
        }

        /*
             * Load billing info
         */

        $billingInfo = new CheckoutAddressInfo();
        $billingInfo->load_from_customer($customer);
        $checkout_data['billing_info'] = $billingInfo;

        /*
             * Load shipping info
         */

        $shippingInfo = new CheckoutAddressInfo();
        $shippingInfo->act_as_billing_info = false;
        $shippingInfo->load_from_customer($customer);
        $checkout_data['shipping_info'] = $shippingInfo;

        self::save($checkout_data);
    }

    /*
         * Billing info
     */

    /**
     * Sets billing address information from POST fields or from {@link CheckoutAddressInfo} object.
     * If the <em>$info</em> parameter is empty, the address information is
     * loaded from POST data using {@link CheckoutAddressInfo::set_from_post()} method.
     * If the <em>$info</em> parameter is not empty, the data is loaded from it.
     * @documentable
     * @param Customer $customer Specifies a customer object.
     * A currently logged in customer can be loaded with {@link Controller::get_customer()}.
     * @param CheckoutAddressInfo $info Specifies an optional address information object to load data from.
     */
    public static function set_billing_info($customer, $info = null)
    {
        if ($info === null) {
            $info = self::get_billing_info();
            $info->set_from_post($customer);
        } else {
            $info->act_as_billing_info = true;
        }

        $checkout_data = self::load();
        $checkout_data['billing_info'] = $info;

        self::save($checkout_data);
        self::save_custom_fields();

        self::set_customer_password();
    }

    public static function set_customer_password()
    {
        if (!post('register_customer')) {
            $checkout_data = self::load();
            $checkout_data['register_customer'] = false;

            self::save($checkout_data);
            return;
        }

        $validation = new Validation();
        $validation->add('customer_password');
        $validation->add('email');

        $email = post('email');
        $existing_customer = Customer::find_registered_by_email($email);
        if ($existing_customer) {
            $validation->setError(
                post(
                    'customer_exists_error',
                    'A customer with the specified email is already registered. Please log in or use another email.'
                ),
                'email',
                true
            );
        }

        if (array_key_exists('customer_password', $_POST)) {
            $allow_empty_password = trim(post('allow_empty_password'));
            $customer_password = trim(post('customer_password'));
            $confirmation = trim(post('customer_password_confirm'));

            if (!strlen($customer_password) && !$allow_empty_password) {
                $validation->setError(
                    post(
                        'no_password_error',
                        'Please enter your password.'
                    ),
                    'customer_password',
                    true
                );
            }

            if ($customer_password != $confirmation) {
                $validation->setError(
                    post(
                        'passwords_match_error',
                        'Password and confirmation password do not match.'
                    ),
                    'customer_password',
                    true
                );
            }

            $checkout_data = self::load();
            $checkout_data['customer_password'] = $customer_password;
            $checkout_data['register_customer'] = true;

            self::save($checkout_data);
        } else {
            $checkout_data = self::load();
            $checkout_data['customer_password'] = null;
            $checkout_data['register_customer'] = true;

            self::save($checkout_data);
        }
    }

    /**
     * Returns the billing address information.
     * If the billing address information is not set in the checkout data,
     * it can be loaded from the
     * {@link https://lsdomainexpired.mjman.net/docs/configuring_the_shipping_parameters/
     * default shipping location}.
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/configuring_the_shipping_parameters/ Shipping Configuration
     * @return CheckoutAddressInfo Returns the checkout address info object.
     */
    public static function get_billing_info()
    {
        $checkout_data = self::load();

        if (!array_key_exists('billing_info', $checkout_data)) {
            $obj = new CheckoutAddressInfo();
            $obj->set_from_default_shipping_location(array('country'));
            return $obj;
        } else {
            $obj = $checkout_data['billing_info'];
            if ($obj && !$obj->country) {
                $obj->set_from_default_shipping_location(array('country'));
                return $obj;
            }
        }

        return $checkout_data['billing_info'];
    }

    /**
     * Copies the billing address information into the shipping address information.
     * @documentable
     */
    public static function copy_billing_to_shipping()
    {
        $billing_info = CheckoutData::get_billing_info();
        $shipping_info = CheckoutData::get_shipping_info();

        $shipping_info->copy_from($billing_info);
        CheckoutData::set_shipping_info($shipping_info);
    }

    /*
         * Payment method
     */

    /**
     * Returns a payment method information previously set with
     * {@link CheckoutData::set_payment_method() set_payment_method()} method.
     * The method returns an object with the following fields:
     * <ul>
     *   <li><em>id</em> - specifies the payment method identifier.</li>
     *   <li><em>name</em> - specifies the payment method name.</li>
     *   <li><em>ls_api_code</em> - specifies the payment method API code.</li>
     * </ul>
     * If the payment method has not been set yet, the object's fields are empty.
     * @documentable
     * @return mixed Returns an object with <em>id</em>, <em>name</em> and <em>ls_api_code</em> fields.
     */
    public static function get_payment_method()
    {
        $checkout_data = self::load();

        if (!array_key_exists('payment_method_obj', $checkout_data)) {
            $method = array(
                'id' => null,
                'name' => null,
                'ls_api_code' => null
            );
            return (object)$method;
        }

        return $checkout_data['payment_method_obj'];
    }

    /**
     * Sets a payment method.
     * You can use the {@link PaymentMethod::find_by_api_code()} method for finding a specific payment method.
     * <pre>CheckoutData::set_payment_method(PaymentMethod::find_by_api_code('card')->id);</pre>
     * @documentable
     * @param integer $payment_method_id Specifies the payment method identifier.
     */
    public static function set_payment_method($payment_method_id = null)
    {
        $method = self::get_payment_method();
        $specific_option_id = $payment_method_id;

        $payment_method_id = $payment_method_id ? $payment_method_id : post('payment_method');

        if (!$payment_method_id) {
            throw new CmsException('Please select payment method.');
        }

        $db_method = PaymentMethod::create();
        if (!$specific_option_id) {
            $db_method->where('enabled=1');
        }

        $db_method = $db_method->find($payment_method_id);
        if (!$db_method) {
            throw new CmsException('Payment method not found.');
        }

        $db_method->define_form_fields();
        $method->id = $db_method->id;
        $method->name = $db_method->name;
        $method->ls_api_code = $db_method->ls_api_code;

        $checkout_data = self::load();
        $checkout_data['payment_method_obj'] = $method;
        self::save($checkout_data);
        self::save_custom_fields();
    }

    /*
         * Shipping info
     */

    /**
     * Sets shipping address information from POST fields or from {@link CheckoutAddressInfo} object.
     * If the <em>$info</em> parameter is empty, the address information is
     * loaded from POST data using {@link CheckoutAddressInfo::set_from_post()} method.
     * If the <em>$info</em> parameter is not empty, the data is loaded from it.
     * @documentable
     * @param CheckoutAddressInfo $info Specifies an optional address information object to load data from.
     */
    public static function set_shipping_info($info = null)
    {
        if ($info === null) {
            $info = self::get_shipping_info();
            $info->set_from_post();
        } else {
            $info->act_as_billing_info = false;
        }

        $checkout_data = self::load();
        $checkout_data['shipping_info'] = $info;
        self::save($checkout_data);
        self::save_custom_fields();
    }

    /**
     * Sets shipping country, state and ZIP/postal code.
     * This method allows to override the shipping
     * country, state and ZIP/postal code components of the shipping address.
     * @documentable
     * @param integer $country_id Specifies the country identifier.
     * @param integer $state_id Specifies the state identifier.
     * @param string $zip Specifies the ZIP/postal code.
     * @see CheckoutAddressInfo::set_location()
     */
    public static function set_shipping_location($country_id, $state_id, $zip)
    {
        $info = self::get_shipping_info();
        $info->set_location($country_id, $state_id, $zip);

        $checkout_data = self::load();
        $checkout_data['shipping_info'] = $info;
        self::save($checkout_data);
        self::save_custom_fields();
    }

    /**
     * Returns the shipping address information.
     * If the shipping address information is not set in the checkout data,
     * it can be loaded from the
     * {@link https://lsdomainexpired.mjman.net/docs/configuring_the_shipping_parameters/
     * default shipping location}.
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/configuring_the_shipping_parameters/ Shipping Configuration
     * @return CheckoutAddressInfo Returns the checkout address info object.
     */
    public static function get_shipping_info()
    {
        $checkout_data = self::load();

        if (!array_key_exists('shipping_info', $checkout_data) || !$checkout_data['shipping_info']->country) {
            $obj = new CheckoutAddressInfo();
            $obj->act_as_billing_info = false;
            $obj->set_from_default_shipping_location(array('country'));
            return $obj;
        }

        return $checkout_data['shipping_info'];
    }

    /*
         * Shipping method
     */

    /**
     * Returns a shipping method information previously set with
     * {@link CheckoutData::set_shipping_method() set_shipping_method()} method.
     * The method returns an object with the following fields:
     * <ul>
     *   <li><em>id</em> - specifies the shipping method identifier.</li>
     *   <li><em>sub_option_id</em> - specifies the shipping method specific sub-option identifier.</li>
     *   <li><em>name</em> - specifies the shipping method name.</li>
     *   <li><em>sub_option_name</em> - specifies the shipping sub-option name.</li>
     *   <li><em>ls_api_code</em> - specifies the shipping method API code.</li>
     *   <li><em>quote</em> - specifies the shipping quote.</li>
     *   <li><em>quote_no_tax</em> - specifies the shipping quote without the shipping tax applied.</li>
     *   <li><em>quote_tax_incl</em> - specifies the shipping quote with the shipping tax applied.</li>
     *   <li><em>is_free</em> - determines the shipping option is free.</li>
     *   <li><em>internal_id</em> - specifies the internal shipping method identifier,
     *       which includes both shipping method identifier and shipping sub-option identifier, for example
     *       <em>2_9b6fd8f11e836e9c3aceb8933d7a710b</em>.</li>
     * </ul>
     * If the shipping method has not been set yet, the object's fields are empty.
     * @documentable
     * @return mixed Returns an object.
     */
    public static function get_shipping_method()
    {
        $checkout_data = self::load();

        if (!array_key_exists('shipping_method_obj', $checkout_data)) {
            $method = array(
                'id' => null,
                'sub_option_id' => null,
                'quote' => 0,
                'quote_no_tax' => 0,
                'quote_tax_incl' => 0,
                'name' => null,
                'sub_option_name' => null,
                'is_free' => false,
                'internal_id' => null,
                'ls_api_code' => null,
                'quote_data' => array()
            );
            return (object)$method;
        }

        return $checkout_data['shipping_method_obj'];
    }

    /**
     * Sets a shipping method.
     * You can use the {@link ShippingOption::find_by_api_code()} method for finding a specific shipping method.
     * <pre>CheckoutData::set_shipping_method(ShippingOption::find_by_api_code('default')->id);</pre>
     * For multi-option shipping methods, like FedEx the <em>$shipping_method_id</em> parameter
     * should contain both shipping method identifier and shipping method specific option identifier,
     * separated with the underscore character, for example: <em>2_9b6fd8f11e836e9c3aceb8933d7a710b</em>
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/creating_shipping_method_partial/
     *  Creating the Shipping Method partial
     * @param string $shipping_method_id Specifies the shipping method identifier.
     * @param string $cart_name Specifies the shopping cart name.
     */
    public static function set_shipping_method($shipping_option_id = null, $cart_name = 'main')
    {
        $method = self::get_shipping_method();

        $specific_option_id = $shipping_option_id;

        $selected_shipping_option_id = $shipping_option_id ? $shipping_option_id : post('shipping_option');
        if (!$selected_shipping_option_id) {
            throw new CmsException('Please select shipping method.');
        }

        $sub_option_id = null;
        if (strpos($selected_shipping_option_id, '_') !== false) {
            $parts = explode('_', $selected_shipping_option_id);
            $selected_shipping_option_id = $parts[0];
            $sub_option_id = $parts[1];
        }

        $option = ShippingOption::create();
        if (!$specific_option_id) {
            $option->where('enabled=1');
        }

        $option = $option->find($selected_shipping_option_id);
        if (!$option) {
            throw new CmsException('Shipping method not found.');
        }

        if (!$option->multi_option) {
            $method->sub_option_id = $option->id . '_' . $sub_option_id;
        }

        self::update_shipping_method($option, $method, $cart_name);
        self::save_custom_fields();
    }

    /**
     * Refreshes shipping method quote and re-saves.
     * @documentable
     * @param string $cart_name Specifies the shopping cart name.
     */

    public static function refresh_active_shipping_quote($cart_name = 'main')
    {
        self::update_shipping_method(null, null, $cart_name);
    }

    /**
     * Deletes the shipping method information from the checkout data.
     * @documentable
     */
    public static function reset_shipping_method()
    {
        $checkout_data = self::load();
        if (array_key_exists('shipping_method_obj', $checkout_data)) {
            unset($checkout_data['shipping_method_obj']);
        }

        self::save($checkout_data);
    }

    protected static function update_shipping_method($option = null, $method = null, $cart_name = 'main')
    {
        $method = is_object($method) ? $method : self::get_shipping_method();
        $option = is_a($option, 'Shop\ShippingOption') ? $option : self::get_shipping_method_option();

        try {
            if (!$option) {
                self::reset_shipping_method();
                throw new ApplicationException('Shipping option is not valid');
            }
            $option->define_form_fields();
            $option->apply_checkout_quote($cart_name);
        } catch (\Exception $ex) {
            // Rethrow system exception as CMS exception
            throw new CmsException($ex->getMessage());
        }

        if (!$option->multi_option) {
            $method->quote_no_discount = $option->quote_no_discount;
            $method->discount = $option->discount;
            $method->quote_no_tax = $option->quote_no_tax;
            $method->quote = $option->quote;
            $method->sub_option_id = null;
            $method->sub_option_name = null;
            $method->internal_id = $option->id;
            $method->is_free = $option->is_free;
            $method->quote_data = $option->quote_data;
        } else {
            $sub_option_found = false;
            foreach ($option->sub_options as $key => $rate_obj) {
                $sub_option_id = $option->id . '_' . md5($rate_obj->name);
                if ($method->sub_option_id == $sub_option_id) {
                    $sub_option_found = true;
                    $method->quote_no_discount = $rate_obj->quote_no_discount;
                    $method->quote = $rate_obj->quote;
                    $method->quote_no_tax = $rate_obj->quote_no_tax;
                    $method->sub_option_id = $sub_option_id;
                    $method->sub_option_name = $rate_obj->name;
                    $method->internal_id = $option->id . '_' . $rate_obj->id;
                    $method->discount = $rate_obj->discount;
                    $method->is_free = $rate_obj->is_free;
                    $method->quote_data = $rate_obj->quote_data;
                    break;
                }
            }

            if (!$sub_option_found) {
                throw new CmsException('Selected shipping option is not applicable.');
            }
        }

        $method->id = $option->id;
        $method->name = $option->name;
        $method->ls_api_code = $option->ls_api_code;
        if ($method->is_free) {
            $method->quote = 0;
            $method->quote_no_tax = 0;
        }
        $checkout_data = self::load();
        $checkout_data['shipping_method_obj'] = $method;
        self::save($checkout_data);
    }

    protected static function get_shipping_method_option()
    {
        $method = self::get_shipping_method();
        $id = $method->internal_id;
        if (strpos($id, '_') !== false) {
            $parts = explode('_', $id);
            $id = $parts[0];
        }
        if (is_numeric($id)) {
            $option = ShippingOption::create()->find($id);
            if ($option) {
                return $option;
            }
        }
        return false;
    }


    protected static function get_total_per_product_cost($cart_name)
    {
        $cart_items = Cart::list_active_items($cart_name);
        $shipping_info = self::get_shipping_info();

        $total_per_product_cost = 0;
        foreach ($cart_items as $item) {
            $product = $item->product;
            if ($product) {
                $shipping_cost = $product->get_shipping_cost(
                    $shipping_info->country,
                    $shipping_info->state,
                    $shipping_info->zip
                );
                $total_per_product_cost += ($shipping_cost * $item->quantity);
            }
        }

        return $total_per_product_cost;
    }

    /**
     * Returns a list of available shipping methods.
     * The list of available shipping methods is based on the customer's shipping location
     * and the cart contents. The method returns a list of {@link ShippingOption} objects. The {@link ShippingOption}
     * class has the following properties which are required for displaying a list of available options:
     * <ul>
     *   <li><em>quote</em> - specifies the shipping quote.</li>
     *   <li><em>quote_no_tax</em> - specifies the shipping quote without the shipping tax applied.</li>
     *   <li><em>quote_tax_incl</em> - specifies the shipping quote with the shipping tax applied.</li>
     *   <li><em>sub_options</em> - an array of the the shipping method specific sub-options.</li>
     *   <li><em>multi_option</em> - indicates whether the option has sub-options.</li>
     *   <li><em>error_hint</em> - an optional error message. This field is not empty in case if the shipping method
     *       returned an error. The content of this field can be displayed in the list of shipping methods.</li>
     * </ul>
     * The <em>sub_options</em> array is not empty only for multi-option shipping methods (FedEx, USPS, etc.).
     * Each element in the array is an object with the following fields:
     * <ul>
     *   <li><em>id</em> - specifies the sub-option identifier. Identifiers are specific for each shipping method.</li>
     *   <li><em>name</em> - specifies the sub-option name.</li>
     *   <li><em>quote</em> - specifies the sub-option shipping quote.</li>
     *   <li><em>is_free</em> - indicates whether the sub-option is free</li>
     * </ul>
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/creating_shipping_method_partial/
     *  Creating the Shipping Method partial
     * @param Customer $customer Specifies the customer object.
     * A currently logged in customer can be loaded with {@link Controller::get_customer()}.
     * @param string $cart_name Specifies the shopping cart name.
     * @param array $options Specifies options for filtering.
     * @return array Returns an array of {@link ShippingOption} objects.
     */
    public static function list_available_shipping_options($customer, $cart_name = 'main', $options = array())
    {
        global $activerecord_no_columns_info;

        $default_options = array(
            'cart_name' => $cart_name,
            'include_tax' => 1,
            'customer_group_id' => Controller::get_customer_group_id(),
        );

        $options = array_merge($default_options, $options);
        $customer = Controller::get_customer();

        $shipping_info = CheckoutData::get_shipping_info();

        //run eval discounts on cart items to mark free shipping items, updates by reference
        $cart_items = Cart::list_active_items($cart_name);
        self::eval_discounts($cart_name, $cart_items);
        $incTax = $options['include_tax'] ? $options['include_tax'] : CheckoutData::display_prices_incl_tax();
        $params = array(
            'display_prices_including_tax' => $incTax,
            'shipping_info' => $shipping_info,
            'total_price' => Cart::total_price_no_tax($cart_name, false),
            'total_volume' => Cart::total_items_volume($cart_items),
            'total_weight' => Cart::total_items_weight($cart_items),
            'total_item_num' => Cart::get_item_total_num($cart_name),
            'cart_items' => $cart_items,
            'customer' => is_object($customer) ? $customer : null,
            'customer_id' => is_object($customer) ? $customer->id : $customer,
            'customer_group_id' => Controller::get_customer_group_id(),
            'currency_code' => self::get_currency($as_object = false),
            'payment_method' => CheckoutData::get_payment_method(),
            'coupon_code' => CheckoutData::get_coupon_code(),
        );

        $params = array_merge($params, $options);

        $available_options = ShippingOption::get_applicable_options($params);
        $available_options = self::add_discount_applied_shipping_options($available_options, $params);

        if (!ShippingParams::get()->display_shipping_service_errors) {
            $options = array();
            foreach ($available_options as $key => $option) {
                if (!strlen($option->error_hint)) {
                    $options[$key] = $option;
                }
            }

            $available_options = $options;
        }

        $multi_options = array();
        $options = array();
        foreach ($available_options as $key => $option) {
            if ($option->multi_option) {
                $multi_options[$key] = $option;
            } else {
                $options[$key] = $option;
            }
        }

        return $multi_options + $options;
    }

    /**
     * Allows discount rules to expose hidden shipping options
     * @param array $shipping_options Specifies the shipping option list to flatten.
     * @param array $params Specifies the shipping calculation parameters.
     * @return array Returns an updated array of shipping options.
     * @ignore
     */
    protected static function add_discount_applied_shipping_options($shipping_options, $params = array())
    {
        $payment_method = is_object($params['payment_method']) ? $params['payment_method'] : null;
        $payment_method_obj = $payment_method ? PaymentMethod::find_by_id($payment_method->id) : null;
        $cart_name = isset($params['cart_name']) ? $params['cart_name'] : 'main';
        $customer_id = isset($params['customer_id']) ? $params['customer_id'] : Controller::get_customer();

        $discount_info = CartPriceRule::evaluate_discount(
            $payment_method_obj,
            null,
            isset($params['cart_items']) ? $params['cart_items'] : Cart::list_active_items($cart_name),
            isset($params['shipping_info']) ? $params['shipping_info'] : CheckoutData::get_shipping_info(),
            isset($params['coupon_code']) ? $params['coupon_code'] : CheckoutData::get_coupon_code(),
            $customer_id,
            isset($params['total_price']) ? $params['total_price'] : Cart::total_price_no_tax($cart_name, false)
        );
        if (isset($discount_info->add_shipping_options) && count($discount_info->add_shipping_options)) {
            foreach ($discount_info->add_shipping_options as $option_id) {
                $option = ShippingOption::create()->find($option_id);
                if ($option) {
                    $shipping_options[] = $option;
                }
            }
        }
        return $shipping_options;
    }

    public static function get_discount_applied_shipping_options($cart_name = 'main')
    {
        //run eval discounts on cart items to mark free shipping items, updates by reference
        $cart_items = Cart::list_active_items($cart_name);
        self::eval_discounts($cart_name, $cart_items);
        $options = array();
        $params = array(
            'cart_name' => $cart_name,
            'cart_items' => $cart_items,
            'payment_method' => CheckoutData::get_payment_method(),
        );
        return self::add_discount_applied_shipping_options($options, $params);
    }

    /**
     * Converts multi-option shipping options to single-option options.
     * Flat shipping option lists simplify front-end coding.
     * @documentable
     * @param array $shipping_options Specifies the shipping option list to flatten.
     * @return array Returns an array of flat shipping options.
     */
    public static function flatten_shipping_options($shipping_options)
    {
        $result = array();

        foreach ($shipping_options as $key => $option) {
            if ($option->multi_option) {
                foreach ($option->sub_options as $sub_option) {
                    $sub_option->multi_option_name = $option->name;
                    $sub_option->multi_option = true;
                    $sub_option->multi_option_id = $option->id;
                    $sub_option->description = $option->description;
                    $sub_option->error_hint = $option->error_hint;
                    $result[$sub_option->id] = $sub_option;
                }
            } else {
                $result[$key] = $option;
            }
        }

        return $result;
    }


    /*
         * Coupon codes
     */

    public static function get_changed_coupon_code()
    {
        $coupon_code = self::get_coupon_code();
        $return = Backend::$events->fireEvent('shop:onBeforeDisplayCouponCode', $coupon_code);
        foreach ($return as $changed_code) {
            if ($changed_code) {
                return $changed_code;
            }
        }
        return $coupon_code;
    }

    /**
     * Returns a coupon code previously set with {@link CheckoutData::set_coupon_code() set_coupon_code()} method.
     * @documentable
     * @return string Returns the coupon code.
     */
    public static function get_coupon_code()
    {
        $checkout_data = self::load();

        if (!array_key_exists('coupon_code', $checkout_data)) {
            return null;
        }

        return $checkout_data['coupon_code'];
    }

    /**
     * Sets a specific coupon code.
     * This method doesn't checks whether the coupon code exists or valid.
     * @documentable
     * @param string $code Specifies the coupon code.
     */
    public static function set_coupon_code($code)
    {
        $checkout_data = self::load();
        $checkout_data['coupon_code'] = $code;
        self::save($checkout_data);
        self::save_custom_fields();
    }

    /*
         * Totals and discount calculations
     */

    /**
     * Returns checkout totals information.
     * The information is calculated basing on the checkout data set with
     * {@link CheckoutData::set_billing_info() set_billing_info()},
     * {@link CheckoutData::set_shipping_info() set_shipping_info()},
     * {@link CheckoutData::set_shipping_method() set_shipping_method()},
     * {@link CheckoutData::set_payment_method() set_payment_method()} methods or basing on default
     * values if possible.
     * The method returns an object with the following fields:
     *   <li><em>all_taxes</em> - an array of all taxes applied to products and shipping.
     *       Each element is an object with two fields: <em>name</em> and <em>total</em>.</li>
     *   <li><em>discount</em> - the applied discount value.</li>
     *   <li><em>discount_tax_incl</em> - the applied discount value with tax included.</li>
     *   <li><em>free_shipping</em> - determines whether free shipping was applied by the Discount Engine.</li>
     *   <li><em>goods_tax</em> - total amount of sales taxes applied to the products.</li>
     *   <li><em>product_taxes</em> - an array of sales taxes applied to products.
     *       Each element is an object with two fields: <em>name</em> and <em>total</em>.</li>
     *   <li><em>shipping_quote</em> - specifies the shipping quote.</li>
     *   <li><em>shipping_quote_tax_incl</em> - specifies the shipping quote with the shipping tax applied.</li>
     *   <li><em>shipping_tax</em> - specifies the shipping tax amount.</li>
     *   <li><em>shipping_taxes</em> - an array of shipping. Each element is an object with two fields:
     *       <em>name</em> and <em>total</em>.</li>
     *   <li><em>subtotal</em> - subtotal amount with no discounts applied.</li>
     *   <li><em>subtotal_discounts</em> - subtotal amount with discounts applied.</li>
     *   <li><em>subtotal_tax_incl</em> - subtotal amount with discounts and taxes applied.</li>
     *   <li><em>total</em> - total amount.</li>
     * @documentable
     * @param string $cart_name Specifies the cart name.
     * @return mixed Returns an object.
     */
    public static function calculate_totals($cart_name = 'main')
    {
        $shipping_info = CheckoutData::get_shipping_info();

        $subtotal = Cart::total_price_no_tax($cart_name, false);

        /**
         * Apply discounts
         */

        $shipping_method = CheckoutData::get_shipping_method();
        $payment_method = CheckoutData::get_payment_method();

        $payment_method_obj = $payment_method->id ? PaymentMethod::find_by_id($payment_method->id) : null;
        $shipping_method_obj = $shipping_method->id ? ShippingOption::find_by_id($shipping_method->id) : null;


        $cart_items = Cart::list_active_items($cart_name);

        $discount_info = CartPriceRule::evaluate_discount(
            $payment_method_obj,
            $shipping_method_obj,
            $cart_items,
            $shipping_info,
            CheckoutData::get_coupon_code(),
            Controller::get_customer(),
            $subtotal
        );

        $tax_context = array(
            'cart_name' => $cart_name
        );
        $tax_info = TaxClass::calculate_taxes($cart_items, $shipping_info, $tax_context);
        $goods_tax = $tax_info->tax_total;

        $subtotal = Cart::total_price_no_tax($cart_name, true, $cart_items);
        $subtotal_no_discounts = Cart::total_price_no_tax($cart_name, false, $cart_items);
        $subtotal_tax_incl = Cart::total_price($cart_name, true, $cart_items);
        $total = $subtotal + $goods_tax;

        $shipping_taxes = array();
        $has_free_option = array_key_exists($shipping_method->internal_id, $discount_info->free_shipping_options);
        if (!$has_free_option && strlen($shipping_method->id)) {
            $shipping_taxes = self::get_shipping_taxes($shipping_method, $cart_name);
            $total += $shipping_tax = TaxClass::eval_total_tax($shipping_taxes);
            $total += $shipping_quote = $shipping_method->quote_no_tax;
        } else {
            $shipping_tax = 0;
            $shipping_quote = 0;
        }

        $result = array(
            'goods_tax' => $goods_tax,
            'subtotal' => $subtotal_no_discounts,
            'subtotal_discounts' => $subtotal,
            'subtotal_tax_incl' => $subtotal_tax_incl,
            'discount' => $discount_info->cart_discount,
            'discount_tax_incl' => $discount_info->cart_discount_incl_tax,
            'discount_info' => $discount_info,
            'shipping_tax' => $shipping_tax,
            'shipping_quote' => $shipping_quote,
            'shipping_quote_tax_incl' => $shipping_quote + $shipping_tax,
            'free_shipping' => $discount_info->free_shipping,
            'total' => $total,
            'product_taxes' => $tax_info->taxes,
            'shipping_taxes' => $shipping_taxes,
            'all_taxes' => TaxClass::combine_taxes_by_name($tax_info->taxes, $shipping_taxes)
        );

        return (object)$result;
    }

    public static function eval_discounts($cart_name = 'main', $cart_items = null)
    {
        $shipping_method = CheckoutData::get_shipping_method();
        $payment_method = CheckoutData::get_payment_method();

        $payment_method_obj = $payment_method->id ? PaymentMethod::find_by_id($payment_method->id) : null;
        $shipping_method_obj = $shipping_method->id ? ShippingOption::find_by_id($shipping_method->id) : null;

        $shipping_info = CheckoutData::get_shipping_info();
        $subtotal = Cart::total_price_no_tax($cart_name, false);

        if ($cart_items === null) {
            $cart_items = Cart::list_active_items($cart_name);
        }

        $discount_info = CartPriceRule::evaluate_discount(
            $payment_method_obj,
            $shipping_method_obj,
            $cart_items,
            $shipping_info,
            CheckoutData::get_coupon_code(),
            Controller::get_customer(),
            $subtotal
        );

        return $discount_info;
    }


    /*
         * Cart identifier
     */

    public static function set_cart_id($value)
    {
        $checkout_data = self::load();
        $checkout_data['cart_id'] = $value;
        self::save($checkout_data);
    }

    /**
     * Returns the shopping cart content identifier saved in the beginning of the checkout process.
     * Comparing the result of this method with the result of the Cart::get_content_id() allows
     * to recognize whether the shopping cart content was changed during the checkout process.
     * @documentable
     * @return string Returns the cart content identifier.
     * @see Cart::get_content_id()
     */
    public static function get_cart_id()
    {
        $checkout_data = self::load();
        return array_key_exists('cart_id', $checkout_data) ? $checkout_data['cart_id'] : null;
    }

    /*
         * Customer notes
     */

    /**
     * Sets customer notes string.
     * Customer notes are saved to the {@link Order order} record.
     * @documentable
     * @param string $notes Specifies the customer notes string.
     */
    public static function set_customer_notes($notes)
    {
        $checkout_data = self::load();
        $checkout_data['customer_notes'] = $notes;
        self::save($checkout_data);
        self::save_custom_fields();
    }

    /**
     * Returns the customer notes string previously set with
     * {@link CheckoutData::set_customer_notes() set_customer_notes()} method.
     * @documentable
     * @return string Returns the customer notes string
     */
    public static function get_customer_notes()
    {
        $checkout_data = self::load();
        return array_key_exists('customer_notes', $checkout_data) ? $checkout_data['customer_notes'] : null;
    }


    /*
         * Custom fields
     */

    public static function save_custom_fields($data = null)
    {
        if ($data === null) {
            $data = $_POST;
        }

        $checkout_data = self::load();

        if (!array_key_exists('custom_fields', $checkout_data)) {
            $checkout_data['custom_fields'] = array();
        }

        foreach ($data as $field => $value) {
            $checkout_data['custom_fields'][$field] = $value;
        }

        self::save($checkout_data);
    }

    public static function get_custom_fields()
    {
        $checkout_data = self::load();
        if (!array_key_exists('custom_fields', $checkout_data)) {
            return array();
        }

        return $checkout_data['custom_fields'];
    }

    public static function get_custom_field($name)
    {
        $fields = self::get_custom_fields();
        if (array_key_exists($name, $fields)) {
            return $fields[$name];
        }

        return null;
    }

    /*
         * Order registration
     */

    /**
     * Creates a new order order.
     * The checkout information must be prepared with
     * {@link CheckoutData::set_billing_info() set_billing_info()},
     * {@link CheckoutData::set_shipping_info() set_shipping_info()},
     * {@link CheckoutData::set_shipping_method() set_shipping_method()},
     * {@link CheckoutData::set_payment_method() set_payment_method()} methods before this method is called.
     * @documentable
     * @param Customer Specifies a currently logged in customer.
     * You can load a customer object from the CMS controller: {@link Controller::get_customer()}.
     * @param boolean $register_customer Determines whether a guest customer should be
     *  automatically registered (converted to a registered customer).
     * @param string $cart_name Specifies the shopping cart name to load the order item list from.
     * @param boolean $empty_cart Specifies whether the shopping cart should be emptied after the order is placed.
     * @return Order Returns the order object.
     */
    public static function place_order($customer, $register_customer = false, $cart_name = 'main', $empty_cart = true)
    {
        $payment_method_info = CheckoutData::get_payment_method();
        $payment_method = PaymentMethod::create()->find($payment_method_info->id);
        if (!$payment_method) {
            throw new CmsException('The selected payment method is not found');
        }

        $payment_method->define_form_fields();

        $checkout_data = self::load();
        $customer_password = $checkout_data['customer_password'] ?? null;
        $register_customer_opt = $checkout_data['register_customer'] ?? null;
        $register_customer = $register_customer || $register_customer_opt;

        $options = array();
        if ($register_customer) {
            $options['customer_password'] = $customer_password;
        }


        $order = Order::place_order($customer, $register_customer, $cart_name, $options);

        if ($empty_cart) {
            Cart::remove_active_items($cart_name);
            CheckoutData::set_customer_notes('');
            CheckoutData::set_coupon_code('');
        }

        if ($order && $register_customer && !$customer) {
            if (post('customer_auto_login')) {
                Phpr::$frontend_security->customerLogin($order->customer_id);
            }

            if (post('customer_registration_notification')) {
                $order->customer->send_registration_confirmation();
            }
        }

        return $order;
    }

    /*
         * Include tax to price rule
     */

    /**
     * Determines whether prices should be displayed with taxes included.
     * Use this method to determine whether prices should be displayed with tax included
     * in {@link https://lsdomainexpired.mjman.net/docs/configuring_lemonstand_for_tax_inclusive_environments/
     * tax inclusive environments}.
     * By default the method loads the customer's location from the currently logged in customer,
     * but if the <em>$order</em> parameter is provided, the customer data is loaded from that object.
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/configuring_lemonstand_for_tax_inclusive_environments/
     *  Configuring LSAPP for tax inclusive environments
     * @param Order $order Specifies an optional order object to load the customer information from.
     * @return boolean Returns TRUE if prices should be displayed with taxes included. Returns FALSE otherwise.
     */
    public static function display_prices_incl_tax($order = null)
    {
        $customerGroup = null;
        $customerDisableTax = false;

        //attempt to fetch customer group from customer override
        $customerOverride = (self::$customerOverride && self::$customerOverride->group);
        if ($customerOverride) {
            $customerGroup = self::$customerOverride->group;
        }

        //attempt to fetch customer group from order
        if (!$customerGroup && $order) {
            $customer = $order->customer;
            if ($customer) {
                $customerGroup = $customer->group;
            }
        }

        //attempt to fetch customer group from controller
        if (!$customerGroup) {
            $customerGroup = Controller::get_customer_group();
        }

        //determine if customer group disables tax
        if ($customerGroup) {
            $customerDisableTax = ($customerGroup->disable_tax_included || $customerGroup->tax_exempt);
        }

        if ($customerDisableTax) {
            return false;
        }

        //use default store config
        return ConfigurationRecord::get()->display_prices_incl_tax;
    }

    /*
         * The following method is used by LSAPP internally
     */

    public static function override_customer($customer)
    {
        self::$customerOverride = $customer;
    }

    /*
         * Auto shipping required detection
     */

    /**
     * Determines whether the shopping cart contains any shippable items.
     * By default shippable items are those items which belong to the Goods product type.
     * If the cart contains only downloadable or service-type products the method returns FALSE.
     * @documentable
     * @param string $cart_name Specifies the shopping cart name
     * @return boolean Returns TRUE if the cart contains any shippable items. Returns FALSE otherwise.
     * @see
     *  https://lsdomainexpired.mjman.net/docs/skipping_the_shipping_method_step_for_downloadable_products_or_services/
     *  Skipping the Shipping Method step for downloadable products or services
     */
    public static function shipping_required($cart_name = 'main')
    {
        $items = Cart::list_active_items($cart_name);
        foreach ($items as $item) {
            if ($item->product->product_type->shipping) {
                return true;
            }
        }

        return false;
    }

    public static function is_currency_set()
    {
        $checkout_data = self::load();
        if (!array_key_exists('currency_code', $checkout_data) || !$checkout_data['currency_code']) {
            return false;
        }
        return true;
    }

    public static function set_currency($currency = null)
    {
        $checkout_data = self::load();
        $currency_code = null;

        if (is_a($currency, 'CurrencySettings')) {
            $currency_code = $currency->code;
        } else {
            $valid_currency_code = DbHelper::scalar(
                'SELECT shop_currency_settings.code FROM shop_currency_settings WHERE shop_currency_settings.code = ?',
                $currency
            );
            if ($valid_currency_code) {
                $currency_code = $valid_currency_code;
            }
        }

        $checkout_data['currency_code'] = $currency_code;
        self::save($checkout_data);
    }

    public static function get_currency($object = true)
    {
        $checkout_data = self::load();
        if (!self::is_currency_set()) {
            $checkout_data['currency_code'] = self::get_default_currency_code();
        }
        if ($object) {
            $currency = CurrencyHelper::get_currency_setting($checkout_data['currency_code']);
            if ($currency) {
                return $currency;
            }
        }
        return $checkout_data['currency_code'];
    }


    protected static function get_default_currency_code()
    {
        return CurrencySettings::get()->code;
    }

    /*
         * Save/load methods
     */

    public static function reset_data()
    {
        $checkout_data = self::load();
        if (array_key_exists('register_customer', $checkout_data)) {
            unset($checkout_data['register_customer']);
        }

        if (array_key_exists('customer_password', $checkout_data)) {
            unset($checkout_data['customer_password']);
        }

        if (array_key_exists('shipping_method_obj', $checkout_data)) {
            unset($checkout_data['shipping_method_obj']);
        }

        if (array_key_exists('custom_fields', $checkout_data)) {
            unset($checkout_data['custom_fields']);
        }

        self::save($checkout_data);
    }

    /**
     * Removes any checkout data from the session.
     * @documentable
     */
    public static function reset_all()
    {
        $checkout_data = array();
        self::save($checkout_data);
    }

    /**
     * Returns a set of shipping tax rates based on customers cart data
     * @param        $shipping_method
     * @param string $cart_name
     *
     * @return array|mixed
     */
    public static function get_shipping_taxes($shipping_method, $cart_name = 'main')
    {
        $shipping_taxes = TaxClass::get_shipping_tax_rates(
            $shipping_method->id,
            CheckoutData::get_shipping_info(),
            $shipping_method->quote_no_tax
        );

        $return = Backend::$events->fireEvent(
            'shop:onCheckoutGetShippingTaxes',
            $shipping_taxes,
            $shipping_method,
            $cart_name
        );

        foreach ($return as $updated_shipping_taxes) {
            if ($updated_shipping_taxes) {
                return $updated_shipping_taxes;
            }
        }

        return $shipping_taxes;
    }

    protected static function load()
    {
        return Phpr::$session->get('shop_checkout_data', array());
    }

    protected static function save(&$data)
    {
        Phpr::$session['shop_checkout_data'] = $data;
    }


    //
    // Deprecated methods
    //

    /**
     * @ignore
     * @deprecated Use {@link CheckoutData::reset_shipping_method()} instead.
     *
     */
    public static function reset_shiping_method() // deprecated
    {
        self::reset_shipping_method();
    }
}
