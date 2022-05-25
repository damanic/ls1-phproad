<?php

namespace Shop;

$phpr_order_no_tax_mode = false;

use Phpr;
use Backend;
use Phpr\Date;
use Phpr\DateTime as PhprDateTime;
use Phpr\ApplicationException;
use Phpr\SystemException;
use Db\Helper as DbHelper;
use Cms\Page;
use System\CompoundEmailVar;
use Core\ModuleManager;
use Core\Rss;
use Core\Number;
use FileSystem\Csv;

/**
 * Represents an order.
 *
 * @property bool $free_shipping Indicates whether free shipping is applied to the order.
 * @property \Db\DataCollection $items A collection of order items.
 * Each element is the collection is an object of the {@link OrderItem} class.
 * @property float $discount Total order discount.
 * @property float $discount_tax_incl Total order discount, tax inclusive.
 * @property float $goods_tax Total value of the sales tax.
 * @property float $shipping_quote Value of the shipping quote.
 * @property float $shipping_quote_tax_incl Shipping quote, tax inclusive.
 * @property float $shipping_tax Value of the shipping tax.
 * @property float $subtotal Order subtotal sum - a sum of the order item totals.
 * @property float $subtotal_tax_incl Order subtotal, tax inclusive
 * @property float $tax_total Total tax value: $goods_tax + $shipping_tax.
 * @property float $total Total order amount.
 * It includes the subtotal value, tax value and the shipping quote value.
 * The discount value is already subtracted from the $total value.
 * @property float $subtotal_before_discounts subtotal before the discount applied.
 * @property int $id Specifies the order record identifier.
 * @property PhprDateTime $order_datetime Specifies a date and time when the order was placed.
 * @property PhprDateTime $status_update_datetime A reference to the date/time object representing
 *  date and time when the order status has been updated last time.
 * @property PhprDateTime $deleted_at Specifies a date and time when the order was marked as deleted.
 * See {@link Order::delete_order() delete_order()} method.
 * @property Country $billing_country A reference to the billing country.
 * @property Country $shipping_country A reference to the shipping country.
 * @property CountryState $billing_state A reference to the billing state.
 * @property CountryState $shipping_state A reference to the shipping state.
 * @property Coupon $coupon An object, representing a coupon applied to the order, if any.
 * The Coupon object has the <en>$code</en> property, which contains the coupon code.
 * @property Customer $customer A reference to the customer who placed the order.
 * @property OrderStatus $status A reference to the current order status.
 * Usually the status information can be loaded with {@link Db_ActiveRecord::displayField() displayField()} method.
 * See {@link OrderStatus} class documentation for details.
 * @property PaymentMethod $payment_method A reference to a payment method, selected by the customer for the order.
 * @property ShippingOption $shipping_method A reference to a shipping method,
 *  selected by the customer for the order.
 * @property string $billing_city Specifies the customer billing city.
 * @property string $billing_company Specifies the customer billing company.
 * @property string $billing_email Specifies the customer email address.
 * @property string $billing_first_name Specifies the customer billing first name.
 * @property string $billing_last_name Specifies the customer billing last name.
 * @property string $billing_phone Specifies the customer billing phone number.
 * @property string $billing_street_addr Specifies the customer billing address.
 * @property string $billing_zip Specifies a billing ZIP/postal code.
 * @property string $customer_ip Customer's IP address.
 * @property string $order_notes Specifies order notes provided by the customer.
 * @property string $shipping_city Specifies the customer shipping city.
 * @property string $shipping_company Specifies the customer shipping company.
 * @property string $shipping_first_name Specifies the customer shipping first name.
 * @property string $shipping_last_name Specifies the customer shipping last name.
 * @property string $shipping_phone Specifies the customer shipping phone number.
 * @property string $shipping_street_addr Specifies the customer shipping street address.
 * @property string $shipping_sub_option Specifies a shipping method sub-option name, if any.
 * @property string $shipping_zip Specifies a shipping ZIP/postal code.
 * @documentable
 * @see https://lsdomainexpired.mjman.net/docs/order_details_page Order Details page
 * @package shop.models
 * @author LSAPP - MJMAN
 */
class Order extends ActiveRecord
{
    public $table_name = 'shop_orders';
    public $native_controller = 'Shop\Orders';
    public $implement = 'Db_ModelLog';
    public $model_log_auto = false;

    public $belongs_to = [
        'shipping_country' => ['class_name' => 'Shop\Country', 'foreign_key' => 'shipping_country_id'],
        'billing_country' => ['class_name' => 'Shop\Country', 'foreign_key' => 'billing_country_id'],
        'shipping_state' => ['class_name' => 'Shop\CountryState', 'foreign_key' => 'shipping_state_id'],
        'billing_state' => ['class_name' => 'Shop\CountryState', 'foreign_key' => 'billing_state_id'],
        'shipping_method' => ['class_name' => 'Shop\ShippingOption', 'foreign_key' => 'shipping_method_id'],
        'payment_method' => ['class_name' => 'Shop\PaymentMethod', 'foreign_key' => 'payment_method_id'],
        'status' => ['class_name' => 'Shop\OrderStatus', 'foreign_key' => 'status_id'],
        'registered_customer' => ['class_name' => 'Shop\Customer', 'foreign_key' => 'customer_id'],
        'customer' => ['class_name' => 'Shop\Customer', 'foreign_key' => 'customer_id'],
        'coupon' => ['class_name' => 'Shop\Coupon', 'foreign_key' => 'coupon_id'],
    ];
    public $has_many = [
        'log_records' => [
            'class_name' => 'Shop\OrderStatusLog',
            'foreign_key' => 'order_id',
            'order' => 'shop_order_status_log_records.created_at DESC, id DESC',
            'delete' => true
        ],
        'items' => [
            'class_name' => 'Shop\OrderItem',
            'foreign_key' => 'shop_order_id',
            'delete' => true,
            'order' => 'shop_order_items.id'
        ],
        'payment_attempts' => [
            'class_name' => 'Shop\PaymentLogRecord',
            'foreign_key' => 'order_id',
            'order' => 'shop_order_payment_log.created_at desc',
            'delete' => true
        ],
        'customer_notifications' => [
            'class_name' => 'Shop\OrderNotification',
            'foreign_key' => 'order_id',
            'order' => 'created_at',
            'delete' => true
        ],
        'payment_transactions' => [
            'class_name' => 'Shop\PaymentTransaction',
            'foreign_key' => 'order_id',
            'order' => 'shop_payment_transactions.created_at desc, shop_payment_transactions.id desc',
            'delete' => true
        ],
        'notes' => [
            'class_name' => 'Shop\OrderNote',
            'foreign_key' => 'order_id',
            'order' => 'shop_order_notes.created_at desc',
            'delete' => true
        ]
    ];

    public $calculated_columns = [
        'tax_total' => [
            'sql' => 'shipping_tax+goods_tax',
            'type' => db_float
        ],
        'has_notes' => [
            'sql' => 'select count(shop_order_notes.id) from shop_order_notes where shop_order_notes.order_id=shop_orders.id',
            'type' => db_number
        ]
    ];
    public $custom_columns = [
        'create_guest_customer' => db_bool,
        'register_customer' => db_bool,
        'notify_registered_customer' => db_bool,
        'payment_page_url' => db_text,
        'subtotal_before_discounts' => db_float,
        'subtotal_tax_incl' => db_float,
        'shipping_quote_tax_incl' => db_float,
        'discount_tax_incl' => db_float,
        'total_shipping_discount' => db_float,
        'shipping_quote_discounted' => db_float,
        'shipping_quote_no_discount' => db_float,
        'order_reference' => db_text,
    ];

    public $shipping_sub_option_id;
    public $internal_shipping_suboption_id;

    protected $api_added_columns = [];

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
        $this->define_column('id', '#');

        $this->define_column('order_datetime', 'Order Created At')
            ->dateAsIs()
            ->dateFormat('%x %H:%M')
            ->order('desc');

        $this->define_relation_column('status', 'status', 'Status', db_varchar, '@name');

        $this->define_column('status_update_datetime', 'Status Updated')
            ->defaultInvisible()
            ->dateAsIs()
            ->dateFormat('%x %H:%M')
            ->order('desc');

        $this->define_column('billing_first_name', 'First Name')
            ->defaultInvisible()
            ->listTitle('Bl. First Name')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('billing_last_name', 'Last Name')
            ->defaultInvisible()
            ->listTitle('Bl. Last Name')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('billing_email', 'Email')
            ->listTitle('Email')
            ->validation()
            ->fn('trim')
            ->required()
            ->email();

        $this->define_column('billing_phone', 'Phone')
            ->defaultInvisible()
            ->listTitle('Bl. Phone')
            ->validation()
            ->fn('trim');

        $this->define_column('billing_company', 'Company')
            ->defaultInvisible()
            ->listTitle('Bl. Company')
            ->validation()
            ->fn('trim');

        $this->define_relation_column(
            'billing_country',
            'billing_country',
            'Country ',
            db_varchar,
            '@name'
        )
            ->listTitle('Bl. Country')
            ->defaultInvisible()
            ->validation()
            ->required();

        $this->define_relation_column(
            'billing_state',
            'billing_state',
            'State ',
            db_varchar,
            '@name'
        )
            ->listTitle('Bl. State')
            ->defaultInvisible();

        $this->define_column('billing_street_addr', 'Street Address')
            ->defaultInvisible()
            ->listTitle('Bl. Address')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('billing_city', 'City')
            ->defaultInvisible()
            ->listTitle('Bl. City')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('billing_zip', 'Zip/Postal Code')
            ->defaultInvisible()
            ->listTitle('Bl. Zip/Postal Code')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('shipping_first_name', 'First Name')
            ->defaultInvisible()
            ->listTitle('Sh. First Name')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('shipping_last_name', 'Last Name')
            ->defaultInvisible()
            ->listTitle('Sh. Last Name')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('shipping_phone', 'Phone')
            ->defaultInvisible()
            ->listTitle('Sh. Phone')
            ->validation()
            ->fn('trim');

        $this->define_column('shipping_company', 'Company')
            ->defaultInvisible()
            ->listTitle('Sh. Company')
            ->validation()
            ->fn('trim');

        $this->define_relation_column(
            'shipping_country',
            'shipping_country',
            'Country ',
            db_varchar,
            '@name'
        )
            ->listTitle('Sh. Country')
            ->defaultInvisible()
            ->validation()
            ->required();

        $this->define_relation_column(
            'shipping_state',
            'shipping_state',
            'State ',
            db_varchar,
            '@name'
        )
            ->listTitle('Sh. State')
            ->defaultInvisible();

        $this->define_column('shipping_street_addr', 'Street Address')
            ->defaultInvisible()
            ->listTitle('Sh. Address')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('shipping_city', 'City')
            ->listTitle('Sh. City')
            ->defaultInvisible()
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('shipping_zip', 'Zip/Postal Code')
            ->defaultInvisible()
            ->listTitle('Sh. Zip/Postal Code')
            ->validation()
            ->fn('trim')
            ->required();

        $this->define_column('shipping_addr_is_business', 'Business address')
            ->invisible();

        $this->define_column('discount', 'Subtotal Discount')
            ->currency(true)
            ->defaultInvisible();

        $this->define_column('subtotal', 'Subtotal')
            ->currency(true);

        $this->define_column('goods_tax', 'Sales Tax')
            ->currency(true)
            ->defaultInvisible();

        $this->define_column('shipping_quote', 'Shipping Quote')
            ->currency(true);

        $this->define_column('shipping_tax', 'Shipping Tax')
            ->defaultInvisible()
            ->currency(true);

        $this->define_column('shipping_sub_option', 'Shipping Option')
            ->defaultInvisible();

        $this->define_column('total', 'Total')
            ->currency(true);

        $this->define_column('tax_total', 'Tax Total')
            ->currency(true);

        $this->define_column('total_cost', 'Total Cost')
            ->defaultInvisible()
            ->currency(true);

        $this->define_column('subtotal_before_discounts', 'Subtotal Before Discounts')
            ->currency(true)
            ->invisible();

        $this->define_column('free_shipping', 'Free Shipping')
            ->defaultInvisible();

        $this->define_column('auto_discount_price_eval', 'Evaluate the order discount and free shipping automatically')
            ->invisible();

        $this->define_relation_column(
            'shipping_method',
            'shipping_method',
            'Shipping Method',
            db_varchar,
            '@name'
        )
            ->defaultInvisible()
            ->validation()
            ->required('Please select shipping method.');

        $this->define_relation_column(
            'payment_method',
            'payment_method',
            'Payment Method',
            db_varchar,
            '@name'
        )
            ->defaultInvisible()
            ->validation()
            ->required('Please select payment method.');

        $this->define_relation_column('status_color', 'status', 'Status Color', db_varchar, '@color')
            ->invisible();

        $this->define_multi_relation_column('log_records', 'log_records', 'Log Records', "@status_id")
            ->invisible();

        $this->define_multi_relation_column('items', 'items', 'Items', "@id")
            ->invisible()
            ->validation();

        $this->define_relation_column(
            'registered_customer',
            'registered_customer',
            'Customer',
            db_varchar,
            "concat(@first_name, ' ', @last_name, ' (', @email, ')')"
        )
            ->invisible();

        $this->define_relation_column(
            'customer_obj',
            'customer',
            'Customer object',
            db_varchar,
            "concat(@first_name, ' ', @last_name, ' (', @email, ')')"
        )
            ->defaultInvisible();

        $this->define_relation_column('coupon', 'coupon', 'Coupon', db_varchar, "@code")
            ->defaultInvisible();

        $this->define_column('create_guest_customer', 'Create new customer')
            ->invisible()
            ->validation();

        $this->define_column('register_customer', 'Register customer')
            ->invisible()
            ->validation();

        $this->define_column('notify_registered_customer', 'Notify customer')
            ->invisible()
            ->validation();

        $this->define_column('deleted_at', 'Deleted')
            ->invisible()
            ->dateFormat('%x %H:%M');

        $this->define_column('payment_processed', 'Payment Processed')
            ->defaultInvisible()
            ->dateFormat('%x %H:%M');

        $this->define_column('customer_ip', 'Customer IP')
            ->defaultInvisible();

        $this->define_column('customer_notes', 'Customer Notes')
            ->defaultInvisible();

        $this->define_column('payment_page_url', 'Payment Page')
            ->invisible();

        $this->define_column('tax_exempt', 'Tax Exempt')
            ->defaultInvisible();

        $this->define_column('override_shipping_quote', 'Fixed shipping quote')
            ->invisible();

        $this->define_column('manual_shipping_quote', 'Shipping quote')
            ->invisible();

        $this->define_column('shipping_discount', 'Internal Shipping Discount')
            ->currency(true)
            ->invisible();

        $this->define_column('total_shipping_discount', 'Shipping Quote Discount')
            ->currency(true)
            ->invisible();

        $this->define_column('shipping_quote_discounted', 'Shipping Quote')
            ->currency(true)
            ->invisible();

        $this->define_column('shipping_quote_no_discount', 'Shipping Quote Before Discounts')
            ->currency(true)
            ->invisible();

        $this->define_column('currency_code', 'Order Currency Code')
            ->defaultInvisible();

        $this->define_column('shop_currency_rate', 'Base Currency Exchange Rate')
            ->defaultInvisible();

        $this->define_column('shop_currency_code', 'Base Currency Code')
            ->defaultInvisible();

        $has_notes_column = $this->define_column('has_notes', 'Has Notes')
            ->listNoTitle()
            ->defaultInvisible();
        if ($context == 'list_settings') {
            $has_notes_column->listTitle('Has Notes');
        }

        $this->define_relation_column('notes', 'notes', 'Notes ', db_varchar, '@note')
            ->invisible();

        $this->define_column('order_reference', 'Order Reference')
            ->invisible();

        $this->defined_column_list = array();
        Backend::$events->fireEvent('shop:onExtendOrderModel', $this, $context);
        $this->api_added_columns = array_keys($this->defined_column_list);
    }

    public function define_form_fields($context = null)
    {
        if ($context == 'invoice' || $context == 'for-customer') {
            $context = null;
        }

        if (strlen($context)) {
            $this->add_form_field('order_datetime', 'left')
                ->tab('Order Details')
                ->noForm();

            $this->add_form_field('status', 'right')
                ->tab('Order Details')
                ->noForm()
                ->previewNoRelation();

            $this->add_form_field('customer_ip')
                ->tab('Order Details')
                ->noForm();

            $this->add_form_field('subtotal_before_discounts', 'left')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Subtotal before discounts</strong>
                            <br/>The sum of all order items without discounts applied'
                );

            $this->add_form_field('discount', 'right')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp('<strong>Discount</strong><br/>Total amount of discount');

            $this->add_form_field('subtotal', 'left')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Order subtotal</strong>
                           <br/>Subtotal is a sum of all order items, taking into account applied discounts'
                );

            $this->add_form_field('goods_tax', 'right')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Sales tax</strong><br/>Sum of all taxes applied to all order items'
                );

            $this->add_form_field('shipping_quote_no_discount', 'left')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Shipping Price</strong>
                           <br/>The shipping quote calculated before any discounts applied'
                );

            $this->add_form_field('total_shipping_discount', 'right')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp('<strong>Shipping Discount</strong><br/>Total amount of shipping discount applied');

            $this->add_form_field('shipping_quote_discounted', 'left')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Cost of shipping</strong>
                     <br/>The cost of shipping, including handling fee, if applicable'
                );

            $this->add_form_field('shipping_tax', 'right')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Shipping tax</strong>
                     <br/>Sum of all taxes applied to the shipping service'
                );

            $this->add_form_field('tax_total', 'right')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Total tax amount</strong><br/>Tax  total = Sales Tax + Shipping Tax'
                );

            $this->add_form_field('total', 'left')
                ->tab('Order Details')
                ->noForm()
                ->previewHelp(
                    '<strong>Total order amount</strong><br/>Total = Subtotal + Shipping Quote + Tax Total'
                );

            if ($this->tax_exempt) {
                $this->add_form_field('tax_exempt')
                    ->tab('Order Details');
            }

            $this->add_form_field('coupon')
                ->tab('Order Details')
                ->noForm()
                ->previewNoOptionsMessage('<a coupon code was not specified>');

            $this->add_form_field('customer_notes')
                ->tab('Order Details')
                ->noForm()
                ->nl2br(true);

            $this->add_form_field('payment_method')
                ->tab('Billing Information');

            $this->add_form_field('billing_first_name', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_last_name', 'right')
                ->tab('Billing Information');

            $this->add_form_field('billing_email')
                ->tab('Billing Information');

            $this->add_form_field('billing_company', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_phone', 'right')
                ->tab('Billing Information');

            $this->add_form_field('billing_country', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_state', 'right')
                ->tab('Billing Information');

            $this->add_form_field('billing_street_addr')
                ->tab('Billing Information')
                ->nl2br(true);

            $this->add_form_field('billing_city', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_zip', 'right')
                ->tab('Billing Information');

            if ($context == 'preview') {
                $this->add_form_field('payment_page_url')
                    ->tab('Billing Information');
            }

            $this->add_form_field('shipping_method')
                ->tab('Shipping Information');

            if (strlen($this->shipping_sub_option)) {
                $this->add_form_field('shipping_sub_option')
                    ->tab('Shipping Information')->noForm();
            }

            $this->add_form_field('shipping_first_name', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_last_name', 'right')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_company', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_phone', 'right')
                ->tab('Shipping Information');

            if ($this->shipping_addr_is_business) {
                $this->add_form_field('shipping_addr_is_business')
                    ->tab('Shipping Information');
            }

            $this->add_form_field('shipping_country', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_state', 'right')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_street_addr')
                ->tab('Shipping Information')
                ->nl2br(true);

            $this->add_form_field('shipping_city', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_zip', 'right')
                ->tab('Shipping Information');

            Backend::$events->fireEvent('shop:onExtendOrderForm', $this, $context);
        } else {
            if ($this->is_new_record()) {
                $this->add_form_field('registered_customer')
                    ->tab('Customer')
                    ->renderAs(frm_record_finder, [
                        'sorting' => 'first_name, last_name, email',
                        'list_columns' => 'first_name,last_name,email,guest,created_at',
                        'search_columns' => 'shop_customers.first_name,shop_customers.last_name,shop_customers.email,shop_customers.guest,shop_customers.created_at',
                        'search_prompt' => 'Find customer by name or email',
                        'form_title' => 'Find Customer',
                        'display_name_field' => 'full_name',
                        'display_description_field' => 'email',
                        'prompt' => 'Click the Find button to find a customer'
                    ])
                    ->comment('Please select a customer or check "Create new customer".', 'above');

                $this->add_form_field('create_guest_customer')
                    ->tab('Customer');

                $this->add_form_field('register_customer')
                    ->cssClassName('hidden')->tab('Customer')
                    ->comment('Use this checkbox to create a registered customer.');

                $this->add_form_field('notify_registered_customer')
                    ->cssClassName('hidden')
                    ->tab('Customer')
                    ->comment(
                        'Use this checkbox to send a registration notification with a password to the new customer.'
                    );
            }

            $this->add_form_field('items')
                ->tab('Order');

            $this->add_form_field('tax_exempt')
                ->tab('Order')
                ->comment('Use this checkbox if the tax should not be applied to this order.');

            $this->add_form_field('billing_first_name', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_last_name', 'right')
                ->tab('Billing Information');

            $this->add_form_field('billing_email')
                ->tab('Billing Information');

            $this->add_form_field('billing_company', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_phone', 'right')
                ->tab('Billing Information');

            $this->add_form_field('billing_country', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_state', 'right')
                ->tab('Billing Information');

            $this->add_form_field('billing_street_addr')
                ->tab('Billing Information')
                ->nl2br(true)
                ->renderAs(frm_textarea)
                ->size('small');

            $this->add_form_field('billing_city', 'left')
                ->tab('Billing Information');

            $this->add_form_field('billing_zip', 'right')
                ->tab('Billing Information');

            $this->add_form_custom_area('copy_shipping_address')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_first_name', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_last_name', 'right')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_company', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_phone', 'right')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_addr_is_business')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_country', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_state', 'right')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_street_addr')
                ->tab('Shipping Information')
                ->nl2br(true)
                ->renderAs(frm_textarea)
                ->size('small');

            $this->add_form_field('shipping_city', 'left')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_zip', 'right')
                ->tab('Shipping Information');

            $this->add_form_field('shipping_method')
                ->tab('Shipping Method')
                ->renderAs(frm_radio);

            $this->add_form_field('override_shipping_quote', 'left')
                ->tab('Shipping Method')
                ->comment('Use this checkbox if you want to enter the shipping quote manually.');

            $this->add_form_field('manual_shipping_quote', 'right')
                ->tab('Shipping Method')
                ->cssClassName('checkbox_align');

            $this->add_form_field('payment_method')
                ->tab('Payment Method')
                ->renderAs(frm_radio);

            $this->add_form_field('free_shipping')
                ->tab('Discounts');

            $this->add_form_field('auto_discount_price_eval')
                ->tab('Discounts');

            $this->add_form_field('coupon', 'right')
                ->tab('Discounts')
                ->emptyOption('<no coupon>');

            $this->add_form_custom_area('enter_discount')
                ->tab('Discounts');

            Backend::$events->fireEvent('shop:onExtendOrderForm', $this, $context);
        }

        foreach ($this->api_added_columns as $column_name) {
            $form_field = $this->find_form_field($column_name);
            if ($form_field) {
                $form_field->optionStateMethod('get_added_field_option_state');
                $form_field->optionsMethod('get_added_field_options');
            }
        }
    }

    public function get_added_field_options($db_name, $current_key_value = -1)
    {
        $result = Backend::$events->fireEvent('shop:onGetOrderFieldOptions', $db_name, $current_key_value);
        foreach ($result as $options) {
            if (is_array($options) || (strlen($options && $current_key_value != -1))) {
                return $options;
            }
        }

        return false;
    }

    public function get_added_field_option_state($db_name, $key_value)
    {
        $result = Backend::$events->fireEvent('shop:onGetOrderFieldState', $db_name, $key_value);
        foreach ($result as $value) {
            return $value;
        }

        return false;
    }

    public function get_shipping_country_options($key_value = -1)
    {
        return $this->list_countries($key_value, $this->shipping_country_id);
    }

    public function get_billing_country_options($key_value = -1)
    {
        return $this->list_countries($key_value, $this->billing_country_id);
    }

    protected function list_countries($key_value = -1, $default = -1)
    {
        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            $obj = Country::create()->find($key_value);
            return $obj ? $obj->name : null;
        }

        $records = DbHelper::objectArray(
            'select * from shop_countries where enabled_in_backend=1 or id=:id order by name',
            array('id' => $default)
        );
        $result = array(null => '<please select>');
        foreach ($records as $country) {
            $result[$country->id] = $country->name;
        }

        return $result;
    }

    public function copy_billing_address($data)
    {
        $this->shipping_first_name = $data['billing_first_name'];
        $this->shipping_last_name = $data['billing_last_name'];
        $this->shipping_company = $data['billing_company'];
        $this->shipping_phone = $data['billing_phone'];
        $this->shipping_country_id = $data['billing_country_id'];
        $this->shipping_state_id = $data['billing_state_id'];
        $this->shipping_street_addr = $data['billing_street_addr'];
        $this->shipping_city = $data['billing_city'];
        $this->shipping_zip = $data['billing_zip'];

        $results = Backend::$events->fireEvent('shop:onOrderCopyBillingAddress', $this, $data);

        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            foreach ($result as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function set_shipping_address($data)
    {
        $this->shipping_first_name = $data['shipping_first_name'];
        $this->shipping_last_name = $data['shipping_last_name'];
        $this->shipping_company = $data['shipping_company'];
        $this->shipping_phone = $data['shipping_phone'];
        $this->shipping_country_id = $data['shipping_country_id'];
        $this->shipping_state_id = $data['shipping_state_id'];
        $this->shipping_street_addr = $data['shipping_street_addr'];
        $this->shipping_city = $data['shipping_city'];
        $this->shipping_zip = $data['shipping_zip'];
        $this->shipping_addr_is_business = $data['shipping_addr_is_business'] ?? false;
    }

    public function set_billing_address($data)
    {
        $this->billing_country_id = $data['billing_country_id'];
        $this->billing_state_id = $data['billing_state_id'];
        $this->billing_zip = $data['billing_zip'];
    }

    public function set_form_data($form_data = null)
    {
        $data = $form_data ? $form_data : post(get_class_id('Shop_\Order'), array());
        if (!empty($data)) {
            $this->update($data);
            $this->set_shipping_address($data);
            $this->set_billing_address($data);
            $this->tax_exempt = array_key_exists('tax_exempt', $data) ? $data['tax_exempt'] : null;
            $this->override_shipping_quote = array_key_exists(
                'override_shipping_quote',
                $data
            ) ? $data['override_shipping_quote'] : null;
            $this->manual_shipping_quote = array_key_exists(
                'manual_shipping_quote',
                $data
            ) ? $data['manual_shipping_quote'] : null;
            $this->shipping_method_id = array_key_exists(
                'shipping_method_id',
                $data
            ) ? $data['shipping_method_id'] : null;
            $this->shipping_sub_option = array_key_exists(
                'shipping_sub_option',
                $data
            ) ? $data['shipping_sub_option'] : $this->shipping_sub_option;
            $this->payment_method_id = array_key_exists('payment_method_id', $data) ? $data['payment_method_id'] : null;
            $this->coupon_id = array_key_exists('coupon_id', $data) ? $data['coupon_id'] : null;
            $this->discount = array_key_exists('discount', $data) ? $data['discount'] : 0;
            $this->free_shipping = array_key_exists('free_shipping', $data) ? $data['free_shipping'] : 0;

            $currency_code = array_key_exists('currency_code', $data) ? $data['currency_code'] : $this->currency_code;
            if (($this->currency_code === null) || ($this->currency_code !== $currency_code)) {
                $this->set_currency($currency_code); //sets the order currency and updates base rate
            }
        }
    }

    public function get_shipping_state_options($key_value = -1)
    {
        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            $obj = CountryState::create()->find($key_value);
            return $obj ? $obj->name : null;
        }

        return $this->get_country_state_options($this->shipping_country_id, $this->shipping_state_id);
    }

    public function get_billing_state_options($key_value = -1)
    {
        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            $obj = CountryState::create()->find($key_value);
            return $obj ? $obj->name : null;
        }

        return $this->get_country_state_options($this->billing_country_id, $this->billing_state_id);
    }

    public function get_registered_customer_options($key_value = -1)
    {
        return array();
    }

    /**
     * Returns a list of states, mapping state ID to state NAME for a given Country ID
     *
     * @param int $country_id The ID for the Country record
     * @param mixed $include_state_id A CountryState ID can be provided to
     * guarantee an assigned State record is included even if that record has since been disabled
     *
     * @return array|string[]
     */
    protected function get_country_state_options($country_id, $include_state_id = null)
    {
        $result = array(null => '<no states available>');
        $country = null;

        if ($country_id) {
            $country = Country::create()->find_proxy($country_id);
        }

        if ($country) {
            $result = $country->get_state_options($include_state_id);
        }

        return $result;
    }

    public function after_validation($deferred_session_key = null)
    {
        $items = $this->list_related_records_deferred('items', $deferred_session_key);
        if (!$items->count) {
            $this->validation->setError('Please add order items.', 'items', true);
        }
    }

    /**
     * Returns shipping address information object.
     * The object is populated with shipping address information from the order.
     * @documentable
     * @return AddressInfo Returns an address information object.
     */
    public function get_shipping_address_info()
    {
        $result = new AddressInfo();
        $result->act_as_billing_info = false;

        $result->first_name = $this->shipping_first_name;
        $result->last_name = $this->shipping_last_name;
        $result->company = $this->shipping_company;
        $result->phone = $this->shipping_phone;
        $result->email = $this->billing_email ? $this->billing_email : $this->customer->email;
        $result->street_address = $this->shipping_street_addr;
        $result->city = $this->shipping_city;
        $result->state = $this->shipping_state_id;
        $result->country = $this->shipping_country_id;
        $result->zip = $this->shipping_zip;
        $result->shipping_addr_is_business = $this->is_business;

        return $result;
    }

    /**
     * Determines whether the order contains any products which support invoices.
     * Modules should handle the {@link shop:onOrderSupportsInvoices} event
     * if they provide order invoices functionality.
     * @documentable
     * @return bool Returns TRUE if the order contains any products which support invoices. Returns FALSE otherwise.
     */
    public function invoices_supported()
    {
        if ($this->parent_order_id) {
            return false;
        }

        $result = Backend::$events->fireEvent('shop:onOrderSupportsInvoices', $this);
        foreach ($result as $value) {
            if ($value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether there are any modules which support invoices.
     * @documentable
     * @return bool Returns TRUE if there are any modules which support invoices. Returns FALSE otherwise.
     */
    public static function invoice_system_supported()
    {
        $result = Backend::$events->fireEvent('shop:onInvoiceSystemSupported');
        foreach ($result as $value) {
            if ($value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether there are any modules which support automated billing
     * @documentable
     * @return bool Returns TRUE if there are any modules which support automated billing. Returns FALSE otherwise.
     */
    public static function automated_billing_supported()
    {
        $result = Backend::$events->fireEvent('shop:onAutomatedBillingSupported');
        foreach ($result as $value) {
            if ($value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a list of invoices.
     * Invoices are orders which are grouped under the given order.
     * @documentable
     * @return \Db\DataCollection Returns a collection of {@link Order} objects.
     */
    public function list_invoices()
    {
        $obj = Order::create();
        $obj->where('parent_order_id=?', $this->id);
        $obj->order('id asc');
        return $obj->find_all();
    }

    /**
     * Creates an order from data collected during the checkout process.
     * The order uses data contained in {@link CheckoutData} object.
     * The <em>$options</em> array can contain the <em>customer_password</em> element,
     * containing a password for the new customer.
     * This value is applicable only if <em>$register_customer</em> parameter is TRUE.
     *
     * Cart content and checkout information can be altered by {@link shop:onOrderBeforeCreate} event handlers.
     * @documentable
     * @param Customer $customer Specifies an existing customer object.
     * If this parameter is NULL a new customer will be created.
     * @param bool $register_customer Determines whether the customer should be registered. Registered customers can
     * {@link https://lsdomainexpired.mjman.net/docs/customer_login_and_logout log into} the store.
     * @param string $cart_name Specifies a name of the shopping cart to load order items from.
     * @param array $options A list of options.
     * @return Order Returns the new order object.
     * @see shop:onOrderBeforeCreate
     * @see CheckoutData
     */
    public static function place_order($customer, $register_customer = false, $cart_name = 'main', $options = array())
    {
        Backend::$events->fireEvent('shop:onOrderBeforeCreate', $cart_name);

        $cart_items = Cart::list_active_items($cart_name);
        $new_customer = null;
        $items = array();

        try {
            /*
             * Check order item availability
             */

            foreach ($cart_items as $cart_item_index => $item) {
                Cart::check_availability($item->product, $item->quantity, $item->options, false);
            }

            /*
             * Update or create a customer
             */

            if (!$customer) {
                $new_customer = $customer = Customer::create();
                $customer->init_columns_info();
                if (!$register_customer) {
                    $customer->guest = 1;
                } else {
                    $password = array_key_exists(
                        'customer_password',
                        $options
                    ) ? $options['customer_password'] : post('password', null);
                    if (!empty($password)) {
                        $customer->password = $password;
                    }
                }
            }

            TaxClass::set_customer_context($customer);

            $billing_info = CheckoutData::get_billing_info();
            $shipping_info = CheckoutData::get_shipping_info();

            $billing_info->save_to_customer($customer);
            $shipping_info->save_to_customer($customer);
            $customer->set_api_fields(CheckoutData::get_custom_fields());
            Backend::$events->fireEvent('shop:onOrderAfterUpdateCustomer', $customer);
            $customer->save();

            /*
             * Calculate discounts
             */

            $payment_method = CheckoutData::get_payment_method();
            $shipping_method = CheckoutData::get_shipping_method();

            $payment_method_obj = $payment_method->id ? PaymentMethod::create()->find($payment_method->id) : null;
            $shipping_method_obj = $shipping_method->id ? ShippingOption::create()->find($shipping_method->id) : null;

            $subtotal = Cart::total_price_no_tax($cart_name, false);

            $discount_info = CartPriceRule::evaluate_discount(
                $payment_method_obj,
                $shipping_method_obj,
                $cart_items,
                $shipping_info,
                CheckoutData::get_coupon_code(),
                $customer,
                $subtotal
            );

            $tax_context = array(
                'cart_name' => $cart_name
            );
            $tax_info = TaxClass::calculate_taxes($cart_items, $shipping_info, $tax_context);

            /*
             * Create order
             */

            $order = self::create();
            $order->init_columns_info();

            if (CheckoutData::is_currency_set()) {
                $currency_code = CheckoutData::get_currency(false);
                $order->set_currency($currency_code);
            }

            $order->customer_id = $customer->id;

            $shipping_info->save_to_order($order);
            $billing_info->save_to_order($order);

            $order->shipping_method_id = $shipping_method->id;
            $order->shipping_quote = round($shipping_method->quote_no_tax, 2);
            $order->shipping_discount = isset($shipping_method->discount) ? round($shipping_method->discount, 2) : 0;

            $shipping_taxes = CheckoutData::get_shipping_taxes($shipping_method, $cart_name);
            $order->apply_shipping_tax_array($shipping_taxes);
            $order->shipping_tax = $shipping_tax = TaxClass::eval_total_tax($shipping_taxes);
            $order->shipping_sub_option = $shipping_method->sub_option_name;

            $order->payment_method_id = $payment_method->id;
            $order->goods_tax = $goods_tax = $tax_info->tax_total;

            $subtotal = Cart::total_price_no_tax($cart_name, true, $cart_items);

            /*
             * Update order currency fields
             */

            $order->discount = $discount_info->cart_discount;
            $order->set_sales_taxes($tax_info->taxes);

            $order->free_shipping = array_key_exists(
                $shipping_method->internal_id,
                $discount_info->free_shipping_options
            ) ? 1 : 0;

            if ($order->free_shipping) {
                $order->shipping_quote = 0;
                $order->shipping_discount = 0;
                $order->shipping_tax = 0;
            }

            $order->subtotal = $subtotal;
            $order->auto_discount_price_eval = 1;

            $coupon_code = CheckoutData::get_coupon_code();
            if (strlen($coupon_code)) {
                $coupon = Coupon::find_coupon($coupon_code);
                $order->coupon_id = $coupon->id;
            } else {
                $order->coupon_id = null;
            }

            $session_key = uniqid('front_end_order', true);

            /*
             * Create order items
             */

            $total_cost = 0;
            $item_map = array();
            foreach ($cart_items as $cart_item_index => $item) {
                $obj = OrderItem::create();
                $obj->shop_product_id = $item->product->id;
                $obj->product = $item->product;
                $obj->quantity = $item->quantity;
                $obj->options = serialize($item->options);
                $obj->auto_discount_price_eval = 1;
                $obj->tax = 0;

                $extras = array();
                foreach ($item->extra_options as $extra) {
                    $extras[] = array(
                        $extra->get_price_no_tax($item->product),
                        $extra->description,
                        $extra->get_price($item->product, true)
                    );
                }

                $obj->extras = serialize($extras);

                $effective_quantity = $item->quantity;
                if ($item->product->tier_prices_per_customer) {
                    $effective_quantity += $customer->get_purchased_item_quantity($item->product);
                }


                if ($item->is_bundle_item()) {
                    /*
                     * NOTE
                     * Price overrides on bundle items are not factored into cart totals as a discount,
                     * therefore in this context the items single price is the
                     * price after all discounts have been applied.
                     */
                    $product_price = $item->single_price();
                } else {
                    $item_om_record = $item->get_om_record();
                    if ($item_om_record) {
                        $obj->option_matrix_record_id = $item_om_record->id;
                        $product_price = $item_om_record->get_sale_price(
                            $item->product,
                            $effective_quantity,
                            $customer->customer_group_id,
                            true
                        );
                    } else {
                        $product_discount = $item->price_is_overridden($effective_quantity) ? 0 : round(
                            $item->product->get_discount($effective_quantity),
                            2
                        );
                        $product_price = $item->single_price_no_tax(false, $effective_quantity) - $product_discount;
                    }
                }

                $obj->price = $product_price;
                $obj->cost = $item->om('cost');
                $obj->discount = $item->applied_discount;

                $total_cost += $obj->cost * $item->quantity;

                if (array_key_exists($cart_item_index, $tax_info->item_taxes)) {
                    $obj->apply_tax_array($tax_info->item_taxes[$cart_item_index]);
                }

                $custom_fields = $item->get_data_fields();
                foreach ($custom_fields as $custom_field => $custom_value) {
                    $obj->$custom_field = $custom_value;
                }

                $item->copy_files_to_order_item($obj);

                $obj->save();

                $items[] = $obj;

                $item_map[$item->key] = $obj;
            }

            /*
             * Apply bundle item data
             */

            foreach ($cart_items as $cart_item_index => $item) {
                if ($item->is_bundle_item()) {
                    $master_item = $item->get_master_bundle_item();
                    if ($master_item && array_key_exists($master_item->key, $item_map)) {
                        $item_map[$item->key]->bundle_master_order_item_id = $item_map[$master_item->key]->id;

                        $bundle_offer = $item->get_bundle_offer();
                        $item_map[$item->key]->bundle_offer_id = $bundle_offer->id;
                        $item_map[$item->key]->bundle_offer_name = $bundle_offer->name;

                        $item_map[$item->key]->save();
                    }
                }
            }

            foreach ($items as $item) {
                $order->items->add($item, $session_key);
            }

            /*
             * Set customer notes and custom fields
             */

            $order->total_cost += $total_cost;
            $order->customer_notes = CheckoutData::get_customer_notes();
            $order->set_api_fields(CheckoutData::get_custom_fields());
            $order->total = $order->get_order_total();
            if ($order->total < 0) {
                $order->total = 0;
            }

            $order->save(null, $session_key);
            $order->customer = $customer;

            /*
             * Save applied discount rules information
             */

            $order->set_applied_cart_rules($discount_info->applied_rules);

            $order = Order::create()->find($order->id);
            $order->customer = $customer;

            return $order;
        } catch (\Exception $ex) {
            Backend::$events->fireEvent('shop:onOrderError', $cart_name, $ex->getMessage());

            if ($new_customer) {
                $new_customer->delete();
            }

            foreach ($items as $item) {
                $item->delete();
            }

            throw $ex;
        }
    }

    public function eval_subtotal_before_discounts()
    {
        $result = 0;
        foreach ($this->items as $item) {
            $result += $item->single_price * $item->quantity;
        }
        return number_format($result, 2, '.', '');
    }

    public function eval_discount_tax_incl()
    {
        $result = 0;
        foreach ($this->items as $item) {
            $result += $item->discount_tax_included * $item->quantity;
        }

        return $result;
    }

    public function eval_shipping_quote_tax_incl()
    {
        return $this->free_shipping ? 0 : $this->shipping_tax_2 + $this->shipping_tax_1 + $this->get_shipping_quote();
    }

    public function eval_subtotal_tax_incl()
    {
        $result = 0;
        foreach ($this->items as $item) {
            $result += $item->subtotal_tax_incl;
        }

        return $result;
    }

    public function apply_shipping_tax_array($tax_array)
    {
        if (isset($tax_array[0])) {
            $this->shipping_tax_1 = $tax_array[0]->rate;
            $this->shipping_tax_name_1 = $tax_array[0]->name;
        } else {
            $this->shipping_tax_1 = 0;
            $this->shipping_tax_name_1 = null;
        }

        if (isset($tax_array[1])) {
            $this->shipping_tax_2 = $tax_array[1]->rate;
            $this->shipping_tax_name_2 = $tax_array[1]->name;
        } else {
            $this->shipping_tax_2 = 0;
            $this->shipping_tax_name_2 = null;
        }
    }

    public function before_create($deferred_session_key = null)
    {
        $this->order_hash = $this->create_hash();
        while (DbHelper::scalar(
            'select count(*) from shop_orders where order_hash=:hash',
            array('hash' => $this->order_hash)
        )) {
            $this->order_hash = $this->create_hash();
        }

        if ($this->order_datetime === null) {
            $this->order_datetime = Date::userDate(PhprDateTime::now());
            $this->order_date = $this->order_datetime;
        }

        $userIp = Phpr::$request->getUserIp();
        if ($userIp == '::1') {
            $userIp = '127.0.0.1';
        }

        $this->customer_ip = $userIp;

        /*
         * Create guest customer
         */

        if ($this->create_guest_customer) {
            $new_customer = $customer = Customer::create();
            $new_customer->copy_from_order($this);
            $new_customer->disable_column_cache();
            $new_customer->define_columns();

            if (!$this->register_customer) {
                $new_customer->guest = 1;
            } else {
                $new_customer->guest = false;
                $new_customer->generate_password();
            }

            $new_customer->save();
            $this->customer_id = $new_customer->id;

            if ($this->register_customer && $this->notify_registered_customer) {
                $new_customer->send_registration_confirmation();
            }
        }

        if (!$this->customer_id && !$this->create_guest_customer) {
            $this->validation->setError(
                'Please select customer or create new guest customer.',
                'create_guest_customer',
                true
            );
        }


        Backend::$events->fireEvent('shop:onBeforeOrderRecordCreate', $this, $deferred_session_key);

        /*
         * Add default currency if not already set
         */
        $this->set_currency();
    }

    public function before_update($session_key = null)
    {
        Backend::$events->fireEvent('shop:onOrderBeforeUpdate', $this, $session_key);
    }

    /**
     * Triggered every time the order is modified
     * @param string $operation will be one of following values 'updated', 'created', 'deleted'
     * @param  $session_key
     */
    public function after_modify($operation, $session_key = null)
    {
        Backend::$events->fireEvent('shop:onOrderAfterModify', $this, $operation, $session_key);
    }

    /**
     * Returns the last used order identifier.
     * @return int Returns the last used order identifier.
     */
    public static function get_last_used_order_id()
    {
        $status = DbHelper::object("SHOW TABLE STATUS where Name='shop_orders'");
        if (!$status) {
            throw new ApplicationException('Error requesting the last used order number');
        }

        return $status->Auto_increment - 1;
    }

    public static function set_next_order_id($new_id)
    {
        $new_id = trim($new_id);

        if (!strlen($new_id) || !preg_match('/^[0-9]+$/', $new_id)) {
            throw new ApplicationException('Invalid order number specified');
        }

        $prev_id = self::get_last_used_order_id();
        if ($prev_id >= $new_id) {
            throw new ApplicationException(
                'New order number should be more than the last used number (' . $prev_id . ')'
            );
        }

        DbHelper::query('ALTER TABLE shop_orders AUTO_INCREMENT=' . $new_id);
    }

    /**
     * Can be used to return a foreign/custom reference code for the order
     * @return string Returns the order reference.
     */
    public function get_order_reference()
    {
        $lookup = Backend::$events->fireEvent('shop:onGetOrderReference', $this);
        foreach ($lookup as $result) {
            if (!empty($result) && (is_string($result) || is_numeric($result))) {
                return $result;
            }
        }
        return $this->id;
    }

    /**
     * Get the currency code that applies to totals stored on the order
     * @return string Returns the currency code in alpha ISO-4217.
     */
    public function get_currency_code($options = null)
    {
        $code = null;
        if ($this->currency_code && strlen($this->currency_code) == 3) {
            $code = $this->currency_code;
        } else {
            $lookup = Backend::$events->fireEvent('shop:onGetOrderCurrencyCode', $this);
            foreach ($lookup as $result) {
                if (!empty($result) && is_string($result) && (strlen($result) == 3)) {
                    $code = $result;
                    break;
                }
            }
        }

        if ($code && is_numeric($code)) { //convert iso numeric to alpha code
            $currency = new CurrencySettings();
            $currency = $currency->where('iso_4217_code = :code', array('code' => $code))->limit(1)->find_all();
            $code = $currency->code;
        }

        return $code ? $code : CurrencySettings::get()->code;
    }

    public function get_currency()
    {
        $currency = new CurrencySettings();
        $currency = $currency->find_by_code($this->currency_code);
        return $currency;
    }

    /**
     * Set the currency code that applies to totals stored on the order
     */
    public function set_currency_code($code = null)
    {
        if (empty($code)) {
            $code = $this->get_currency_code();
        }

        if (strlen($code) !== 3) {
            throw new ApplicationException('Must provide correct code in ISO-4217 alpha or numerical');
        }
        $currency = new CurrencySettings();
        $currency = $currency->where(
            'code = :code || iso_4217_code = :code',
            array('code' => $code)
        )->limit(1)->find_all();
        if (!$currency) {
            throw new ApplicationException('Invalid currency code given');
        }
        $this->currency_code = $currency->code;
        $this->shop_currency_code = $this->get_shop_currency_code();
    }

    /**
     * Set the currency code and exchange rate for totals stored on the order
     */
    public function set_currency($code = null, $rate = null)
    {
        $this->set_currency_code($code);
        $this->set_currency_rate($rate);
    }

    /*
     * This rate is used to convert order totals back to native shop currency.
     * order_totals * this_rate = shop_currency
     */
    protected function set_currency_rate($rate = null)
    {
        if (is_numeric($rate)) {
            $this->shop_currency_rate = $rate;
            return;
        }
        //this rate field is used to convert totals back to shop currency
        $from_currency_code = $this->get_currency_code();
        $to_currency_code = $this->get_shop_currency_code();
        $rate = 1;
        if ($from_currency_code !== $to_currency_code) {
            $rate = 1;
            $currency_converter = CurrencyConverter::create();
            $rate = $currency_converter->convert(1, $from_currency_code, $to_currency_code, 4);
        }
        $this->shop_currency_rate = $rate;
    }

    protected function get_shop_currency_code()
    {
        return $this->shop_currency_code ? $this->shop_currency_code : CurrencySettings::get()->code;
    }

    public function format_currency($value)
    {
        return CurrencyHelper::format_currency($value, 2, $this->get_currency_code());
    }

    public function display_in_shop_currency($value)
    {
        $value = $this->convert_to_shop_currency($value);
        return CurrencyHelper::format_currency($value, 2, $this->get_shop_currency_code());
    }

    public function convert_to_shop_currency($value)
    {
        if (!is_numeric($value)) {
            return null;
        }

        $order_shop_currency_code = $this->get_shop_currency_code();
        $active_shop_currency_code = CurrencySettings::get()->code;

        if ($this->get_currency_code() !== $active_shop_currency_code) { //conversion required
            if ($order_shop_currency_code == $active_shop_currency_code) {
                //use stored rate
                $value = round(($value * $this->shop_currency_rate), 4);
            } else {
                //use todays rate
                $currency_converter = CurrencyConverter::create();
                $from_currency_code = $this->get_currency_code();
                $to_currency_code = $active_shop_currency_code;
                $value = $currency_converter->convert(1, $from_currency_code, $to_currency_code, 4);
            }
        }
        return $value;
    }

    public function displayField($dbName, $media = 'form')
    {
        $column_definitions = $this->get_column_definitions();
        if (!array_key_exists($dbName, $column_definitions)) {
            throw new SystemException(
                'Cannot execute method "displayField" for field ' . $dbName . ' 
                - the field is not defined in column definition list.'
            );
        }

        $column_definition = $column_definitions[$dbName];
        if ($column_definition->type == db_float && $column_definition->currency && is_numeric($this->$dbName)) {
            return CurrencyHelper::format_currency($this->$dbName, 2, $this->get_currency_code());
        }
        return $column_definitions[$dbName]->displayValue($media);
    }

    /**
     * Used to find an order using foreign/custom reference code
     * @return Order|false Returns the order if found or FALSE otherwise.
     */
    public static function find_by_order_reference($order_ref)
    {
        $lookup = Backend::$events->fireEvent('shop:onOrderFindByOrderReference', $order_ref);
        foreach ($lookup as $order) {
            if ($order && is_a($order, 'Shop\Order')) {
                return $order;
            }
        }
        $order = Order::create()->find($order_ref);
        return $order ? $order : false;
    }

    public function set_api_fields($fields)
    {
        if (!is_array($fields)) {
            return;
        }

        foreach ($fields as $field => $value) {
            if (in_array($field, $this->api_added_columns)) {
                $this->$field = $value;
            }
        }
    }

    public function after_create_saved()
    {
        $status_id = OrderStatus::get_status_new()->id;

        $order_copy = Order::create()->find($this->id);
        OrderStatusLog::create_record($status_id, $order_copy);

        Backend::$events->fireEvent('shop:onNewOrder', $this->id);

        $payment_method = $this->payment_method;
        if ($payment_method) {
            $payment_method->define_form_fields();
            $payment_method->get_paymenttype_object()->order_after_create($payment_method, $this);
        }
    }

    /**
     * Marks the order as deleted.
     * Orders marked as deleted remain in the database and can be restored with the
     * {@link Order::restore_order() restore_order()} method.
     * @documentable
     * @see Order::restore_order() restore_order()
     * @see shop:onOrderMarkedDeleted
     */
    public function delete_order()
    {
        $this->before_delete($db_delete = false);
        $this->deleted_at = PhprDateTime::now();
        $this->save();
        Backend::$events->fireEvent('shop:onOrderMarkedDeleted', $this);
    }

    public function before_delete($db_delete = true)
    {
        Backend::$events->fireEvent('shop:onOrderBeforeDelete', $this, $db_delete);
    }

    public function after_delete()
    {
        self::delete_order_data($this->id);

        /*
         * Delete invoices and assigned data
         */

        $invoice_ids = DbHelper::scalarArray(
            'select id from shop_orders where parent_order_id=:order_id',
            array('order_id' => $this->id)
        );
        foreach ($invoice_ids as $invoice_id) {
            self::delete_order_data($invoice_id);
        }

        Backend::$events->fireEvent('shop:onOrderAfterDelete', $this);
    }

    /**
     * Removes an order and all records which refer to it
     */
    public static function delete_order_data($order_id)
    {
        $bind = array('order_id' => $order_id);
        DbHelper::query('delete from shop_orders where id=:order_id', $bind);
        DbHelper::query('delete from shop_order_items where shop_order_id=:order_id', $bind);
        DbHelper::query('delete from shop_order_notes where order_id=:order_id', $bind);
        DbHelper::query('delete from shop_order_notifications where order_id=:order_id', $bind);
        DbHelper::query('delete from shop_order_payment_log where order_id=:order_id', $bind);
        DbHelper::query('delete from shop_order_status_log_records where order_id=:order_id', $bind);
        DbHelper::query('delete from shop_order_applied_rules where shop_order_id=:order_id', $bind);
        DbHelper::query('delete from shop_payment_transactions where order_id=:order_id', $bind);
    }

    public function after_has_many_bind($obj, $relation_name)
    {
        if ($relation_name == 'items') {
            Backend::$events->fireEvent('shop:onOrderItemAdded', $obj);
        }
    }

    /**
     * Restores an order previously marked as deleted with {@link Order::delete_order() delete_order()} method.
     * @documentable
     * @see Order::delete_order() delete_order()
     * @see shop:onOrderRestored
     */
    public function restore_order()
    {
        $this->deleted_at = null;
        $this->save();
        Backend::$events->fireEvent('shop:onOrderRestored', $this);
    }

    public function __get($name)
    {
        if ($name == 'color') {
            return $this->displayField('status_color');
        }

        if ($name == 'goods_tax_total') {
            return $this->goods_tax;
        }

        return parent::__get($name);
    }

    public function set_applied_cart_rules($rules)
    {
        $bind = array('id' => $this->id);
        DbHelper::query('delete from shop_order_applied_rules where shop_order_id=:id', $bind);

        foreach ($rules as $rule_id) {
            $bind['rule_id'] = $rule_id;
            $rule = CartPriceRule::create()->find($rule_id);
            $bind['rule_serialized'] = ($rule && $rule->id) ? serialize($rule->serialize(false)) : null;
            $sql = 'INSERT INTO shop_order_applied_rules (shop_order_id, shop_cart_rule_id, shop_cart_rule_serialized) 
                    VALUES (:id, :rule_id, :rule_serialized)';
            DbHelper::query($sql, $bind);
        }
        Backend::$events->fireEvent('shop:onOrderSetAppliedCartRules', $this);
    }

    public function get_applied_cart_rules()
    {
        $rules = array();
        $results = DbHelper::queryArray('SELECT * FROM shop_order_applied_rules WHERE shop_order_id=?', $this->id);
        if ($results) {
            foreach ($results as $rule_data) {
                if (!empty($rule_data['shop_cart_rule_serialized'])) {
                    $rule = CartPriceRule::create()->unserialize(unserialize($rule_data['shop_cart_rule_serialized']));
                } else {
                    $rule = CartPriceRule::create()->find($rule_data['shop_cart_rule_id']);
                    if (!$rule) {
                        $rule = CartPriceRule::create();
                        $rule->id = $rule_data['shop_cart_rule_id'];
                    }
                }
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    public function get_payment_method()
    {
        $payment_method = null;
        if ($this->payment_method_id) {
            if ($this->payment_method && ($this->payment_method->id == $this->payment_method_id)) {
                $payment_method = $this->payment_method;
            } else {
                $this->payment_method = $payment_method = PaymentMethod::create()->find($this->payment_method_id);
            }
        }
        return $payment_method;
    }

    /**
     * Returns URL of the order payment page URL.
     * Use this method for creating links to the payment page for unpaid orders.
     * Payment page is a page based on {@link action@shop:pay} action.
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/pay_page Payment page
     * @return string Returns the page URL. Returns NULL if the page is not found.
     */
    public function get_payment_page_url()
    {
        $custom_pay_page = null;
        $payment_method = $this->get_payment_method();
        if ($payment_method) {
            $payment_method->define_form_fields();
            $custom_pay_page = $payment_method->get_paymenttype_object()->get_custom_payment_page($payment_method);
        }

        $pay_page = $custom_pay_page ? $custom_pay_page : Page::create()->find_by_action_reference('shop:pay');

        if ($pay_page) {
            $protocol = null;
            if ($pay_page->protocol != 'any') {
                $protocol = $pay_page->protocol;
            }

            return root_url($pay_page->url . '/' . $this->order_hash, true, $protocol);
        }

        return null;
    }


    /**
     * Sets values for common order email template variables
     * @param string $message_text Specifies a message text to substitute variables in
     * @return string
     */
    public function set_order_email_vars($message_text, $status_comment = null, $status = null)
    {
        $var_values = Backend::$events->fireEvent('shop:onApplyOrderEmailVars', $this, $status_comment, $status);
        foreach ($var_values as $value_set) {
            foreach ($value_set as $var => $value) {
                $message_text = str_replace('{' . $var . '}', $value, $message_text);
            }
        }


        $include_tax = CheckoutData::display_prices_incl_tax($this);

        $message_text = str_replace('{order_total}', $this->format_currency($this->total), $message_text);
        $message_text = str_replace(
            '{order_payment_due}',
            $this->format_currency($this->get_payment_due()),
            $message_text
        );
        $message_text = str_replace('{order_id}', $this->id, $message_text);
        $message_text = str_replace('{order_reference}', $this->get_order_reference(), $message_text);
        $message_text = str_replace('{order_date}', $this->order_datetime->format('%x'), $message_text);
        $message_text = str_replace(
            '{order_subtotal}',
            $include_tax ? $this->format_currency($this->subtotal_tax_incl) : $this->format_currency($this->subtotal),
            $message_text
        );

        $quote = $include_tax ? $this->shipping_quote_tax_incl : $this->get_shipping_quote();
        $message_text = str_replace(
            '{order_shipping_quote}',
            $this->format_currency($quote),
            $message_text
        );
        $message_text = str_replace('{order_shipping_tax}', $this->format_currency($this->shipping_tax), $message_text);
        $message_text = str_replace('{order_tax}', $this->format_currency($this->goods_tax), $message_text);
        $message_text = str_replace(
            '{order_total_tax}',
            $this->format_currency($this->goods_tax + $this->shipping_tax),
            $message_text
        );

        $message_text = str_replace(
            '{customer_notes}',
            strlen($this->customer_notes) ? h($this->customer_notes) : h('<not specified>'),
            $message_text
        );
        $message_text = str_replace(
            '{cart_discount}',
            $include_tax ? $this->format_currency($this->discount_tax_incl) : $this->format_currency($this->discount),
            $message_text
        );
        $message_text = str_replace('{tax_incl_label}', tax_incl_label($this), $message_text);

        $message_text = str_replace(
            '{net_amount}',
            $this->format_currency($this->total - $this->goods_tax - $this->shipping_tax),
            $message_text
        );

        $message_text = str_replace('{billing_country}', h($this->displayField('billing_country')), $message_text);
        $message_text = str_replace('{billing_state}', h($this->displayField('billing_state')), $message_text);
        $message_text = str_replace(
            '{billing_street_addr}',
            h($this->displayField('billing_street_addr')),
            $message_text
        );
        $message_text = str_replace('{billing_city}', h($this->displayField('billing_city')), $message_text);
        $message_text = str_replace('{billing_zip}', h($this->displayField('billing_zip')), $message_text);
        $message_text = str_replace('{shipping_country}', h($this->displayField('shipping_country')), $message_text);
        $message_text = str_replace('{shipping_state}', h($this->displayField('shipping_state')), $message_text);
        $message_text = str_replace(
            '{shipping_street_addr}',
            h($this->displayField('shipping_street_addr')),
            $message_text
        );
        $message_text = str_replace('{shipping_city}', h($this->displayField('shipping_city')), $message_text);
        $message_text = str_replace('{shipping_zip}', h($this->displayField('shipping_zip')), $message_text);
        $message_text = str_replace(
            '{order_coupon}',
            $this->coupon ? h($this->coupon->code) : h('<not specified>'),
            $message_text
        );
        $message_text = str_replace(
            '{billing_customer_name}',
            h($this->billing_first_name . ' ' . $this->billing_last_name),
            $message_text
        );
        $message_text = str_replace(
            '{shipping_customer_name}',
            h($this->shipping_first_name . ' ' . $this->shipping_last_name),
            $message_text
        );


        $message_text = str_replace('{customer_email}', h($this->billing_email), $message_text);
        $message_text = str_replace('{customer_first_name}', h($this->billing_first_name), $message_text);
        $message_text = str_replace('{customer_last_name}', h($this->billing_last_name), $message_text);
        $message_text = str_replace(
            '{customer_name}',
            h($this->billing_first_name . ' ' . $this->billing_last_name),
            $message_text
        );

        $status_comment = strlen(trim($status_comment)) ? $status_comment : '<not specified>';
        $message_text = str_replace('{order_status_comment}', h($status_comment), $message_text);

        if ($status) {
            $message_text = str_replace('{order_status_name}', h($status->name), $message_text);

            if (strpos($message_text, '{order_previous_status}') !== false) {
                $prev_status = OrderStatus::create()->find($this->status_id);
                if ($prev_status) {
                    $message_text = str_replace('{order_previous_status}', h($prev_status->name), $message_text);
                }
            }
        } elseif (strpos($message_text, '{order_status_name}') !== false) {
            $status = OrderStatus::create()->find($this->status_id);
            if ($status) {
                $message_text = str_replace('{order_status_name}', h($status->name), $message_text);
            }
        }

        if ((strpos($message_text, '{payment_page_link}') !== false) || strpos(
            $message_text,
            '{payment_page_url}'
        ) !== false) {
            $pay_page_url = $this->get_payment_page_url();
            $message_text = str_replace(
                '{payment_page_link}',
                '<a href="' . $pay_page_url . '">' . $pay_page_url . '</a>',
                $message_text
            );
            $message_text = str_replace('{payment_page_url}', $pay_page_url, $message_text);
        }

        if (strpos($message_text, '{shipping_codes}') !== false) {
            $codes = OrderTrackingCode::find_by_order($this);
            $codes_str = '';
            if ($codes->count) {
                $codes_str = '<ul>';
                foreach ($codes as $code) {
                    $dValue = $code->displayField('code_shipping_method');
                    $codes_str .= '<li>' . h($dValue) . ': ' . h($code->code) . '</li>';
                }

                $codes_str .= '</ul>';
            }

            $message_text = str_replace('{shipping_codes}', $codes_str, $message_text);
        }

        if (strpos($message_text, '{order_shipping_option}') !== false) {
            $method = $this->displayField('shipping_method');
            $option = $this->displayField('shipping_sub_option');
            $output = strlen($option) ? $option : $method;
            $message_text = str_replace('{order_shipping_option}', $output, $message_text);
        }

        if (strpos($message_text, '{order_shipping_sub_option}') !== false) {
            $option = $this->displayField('shipping_sub_option');
            $message_text = str_replace('{order_shipping_sub_option}', $option, $message_text);
        }

        if (strpos($message_text, '{order_shipping_method}') !== false) {
            $method = $this->displayField('shipping_method');
            $message_text = str_replace('{order_shipping_method}', $method, $message_text);
        }

        return $message_text;
    }

    public function set_order_and_customer_email_vars($customer, $message, $status_comment, $params = array())
    {
        if (!$customer) {
            $customer = $this->customer;
        }

        $items = OrderItem::create()->where(
            'shop_order_id=?',
            $this->id
        )->order('shop_order_items.id')->find_all();

        $email_scope_vars = array('order' => $this, 'customer' => $customer, 'items' => $items);
        $email_scope_vars = array_merge($email_scope_vars, $params);

        $message = CompoundEmailVar::apply_scope_variables($message, 'shop:order', $email_scope_vars);

        $status = array_key_exists('prev_status', $params) ? $params['prev_status'] : null;
        $message = $this->set_order_email_vars($message, $status_comment, $status);
        $message = $customer->set_customer_email_vars($message);
        $message = ModuleManager::applyEmailVariables($message, $this, $customer);

        return $message;
    }

    public function send_customer_notification($template, $status_comment = null, $params = array())
    {
        global $phpr_order_no_tax_mode;
        $phpr_order_no_tax_mode = false;

        $customer = Customer::create()->find($this->customer_id);
        if (!$customer) {
            return;
        }

        $message = $this->set_order_and_customer_email_vars($customer, $template->content, $status_comment, $params);
        $template->subject = $this->set_order_and_customer_email_vars(
            $customer,
            $template->subject,
            $status_comment,
            $params
        );

        $reply_to = $template->get_reply_address(
            null,
            null,
            $this->billing_email,
            $this->billing_first_name . ' ' . $this->billing_last_name
        );

        OrderNotification::add_message($this, $customer, $message, $template->subject, null, $reply_to);

        $customer->email = $this->billing_email;
        $template->send_to_customer(
            $customer,
            $message,
            null,
            null,
            $this->billing_email,
            $this->billing_first_name . ' ' . $this->billing_last_name,
            array('order' => $this)
        );
    }

    public function send_team_notifications($template, $users, $status_comment = null, $params = array())
    {
        global $phpr_order_no_tax_mode;
        $phpr_order_no_tax_mode = true;

        $customer = Customer::create()->find($this->customer_id);
        if (!$customer) {
            return;
        }

        $message = $this->set_order_and_customer_email_vars($customer, $template->content, $status_comment, $params);
        $template->subject = $this->set_order_and_customer_email_vars(
            $customer,
            $template->subject,
            $status_comment,
            $params
        );

        $user = Phpr::$security->getUser();
        $sender_email = $user ? $user->email : null;
        $sender_name = $user ? $user->name : null;

        $reply_to = $template->get_reply_address(
            null,
            null,
            $this->billing_email,
            $this->billing_first_name . ' ' . $this->billing_last_name
        );
        OrderNotification::add_system_message($this, $users, $message, $template->subject, null, $reply_to);

        $template->send_to_team(
            $users,
            $message,
            $sender_email,
            $sender_name,
            $this->billing_email,
            $this->billing_first_name . ' ' . $this->billing_last_name
        );
    }

    public function update_stock_values()
    {
        $this->stock_updated = true;
        DbHelper::query('update shop_orders set stock_updated=1 where id=:id', array('id' => $this->id));

        foreach ($this->items as $item) {
            if ($item->product) {
                $item->product->decrease_stock($item->quantity, $item->get_om_record());
            }
        }
    }

    protected function create_hash()
    {
        return md5(uniqid('order', microtime()));
    }

    /**
     * Updates the internal flag which indicates whether the order's payment has been processed.
     * This method is used by
     * {@link https://lsdomainexpired.mjman.net/docs/developing_payment_modules payment modules} internally.
     * @documentable
     * @see Order::payment_processed() payment_processed()
     * @see https://lsdomainexpired.mjman.net/docs/developing_payment_modules Developing payment modules
     */
    public function set_payment_processed()
    {
        $current_status = $this->payment_processed(false);

        if (!$current_status) {
            $now = PhprDateTime::now();
            $this->payment_processed = $now;

            /*
             * Instantly update the DB column, because saving the model (ActiveRecord) could
             * cause undesirable delay which could result in duplicate
             * payment notifications
             */

            DbHelper::query(
                'update shop_orders set payment_processed=:payment_processed where id=:id',
                array('id' => $this->id, 'payment_processed' => $now)
            );

            /*
             * Save the model
             */

            $this->save();
        }

        return !$current_status;
    }

    /**
     * Returns TRUE if the order has
     * {@link https://lsdomainexpired.mjman.net/docs/understanding_the_paid_order_status/ Paid status},
     * or the Paid status is in the order status history.
     * The common usage for the is_paid() method is restricting customer's access to product files
     * if you sell downloadable products.
     * @documentable
     * @return bool Returns TRUE if the order is paid. Returns FALSE otherwise.
     * @see Order::payment_processed() payment_processed()
     */
    public function is_paid()
    {
        return DbHelper::scalar(
            'select count(*) from 
				shop_order_status_log_records, shop_order_statuses 
				where shop_order_statuses.id=shop_order_status_log_records.status_id 
				and shop_order_status_log_records.order_id=:order_id and shop_order_statuses.code=:code',
            array(
                'order_id' => $this->id,
                'code' => OrderStatus::status_paid
            )
        );
    }

    /**
     * Returns TRUE if a customer has successfully paid the order.
     * The {@link Order::is_paid() is_paid()} and payment_processed() methods
     * could return opposite results in a case when a customer have paid an order
     * (the payment_processed() method will return TRUE),
     * but you (a merchant) have not validated the payment and have not sent the order into the
     * {@link https://lsdomainexpired.mjman.net/docs/understanding_the_paid_order_status/ Paid status}
     * (the {@link Order::is_paid() is_paid()} method will return FALSE). Payment methods in LSAPP can be configured
     * in such a way that a successful payment automatically sends an order to the Paid status.
     * Another way is to automatically send the
     * order to some pending status, validate the payment manually, and then send the order to the Paid status.
     *
     * The common usage for the payment_processed() method is hiding the Pay button on the
     * {@link https://lsdomainexpired.mjman.net/docs/order_details_page order details page}.
     * The common usage for the is_paid() method is restricting customer's
     * access to product files when you sell downloadable products.
     * @documentable
     * @param bool $use_cached Determines whether subsequent calls of the method can use a cached value
     *  instead of loading it from the database each time.
     * @return bool Returns TRUE if the order payment has been successfully processed.
     */
    public function payment_processed($use_cached = true)
    {
        if ($use_cached) {
            return $this->payment_processed;
        }

        return DbHelper::scalar('select payment_processed from shop_orders where id=:id', array('id' => $this->id));
    }

    /**
     * Returns true if payment transaction operations (changing transaction status,
     * requesting transaction status) are allowed.
     * The operations are allowed if there are any transactions registered for the order
     */
    public function transaction_operations_allowed()
    {
        $has_transactions = $this->payment_transactions->count;
        if (!$has_transactions) {
            return false;
        }

        if (!$this->payment_method) {
            return false;
        }

        return true;
    }

    public function get_payment_transaction_methods()
    {
        $payment_methods = array();
        if ($this->payment_transactions) {
            foreach ($this->payment_transactions as $transaction) {
                $payment_methods[$transaction->payment_method_id] = $transaction->payment_method;
            }
        }
        return $payment_methods;
    }

    public function get_payment_transaction_types()
    {
        $payment_types = array();
        $payment_methods = $this->get_payment_transaction_methods();
        foreach ($payment_methods as $payment_method) {
            $payment_type = $payment_method->get_paymenttype_object();
            $payment_types[get_class_id($payment_type)] = $payment_type;
        }
        return $payment_types;
    }

    public function get_payment_due()
    {
        $payable = $this->get_total_value_payable();
        $due = $payable ? $payable : 0;
        if ($this->is_paid()) {
            $due = 0;
        }
        if ($this->id) {
            $transaction_paid = PaymentTransaction::get_order_balance($this);
            if ($transaction_paid !== null) {
                $payable -= $transaction_paid;
                $due = round($payable, 2);
            }
        }
        return $due;
    }

    /**
     * This method is used to help determine the amount of payment still due for an order.
     * It allows external modules to discount part of the total order value from being considered as due for payment.
     *
     * Example usage:
     * A credit note module that records and enables refunds.
     * Normally when a payment transaction is refunded on an order it raises an amount due.
     * With this method the module can reduce the total value of the order
     * expected for payment after credit note refunds.
     *
     * @return int Total amount payable
     */
    public function get_total_value_payable()
    {
        $order_value = $this->total ? $this->total : 0;
        $value_not_payable = 0;
        if ($order_value) {
            $results = Backend::$events->fire_event(array('name' => 'shop:onOrderGetTotalValueNotPayable'), $this);
            if ($results && count($results)) {
                foreach ($results as $key => $value) {
                    if (is_numeric($value)) {
                        $value_not_payable += $value;
                    }
                }
            }
        }
        return $order_value - $value_not_payable;
    }

    /**
     * Returns a list of taxes applied to order items.
     * The method returns an array of objects containing the following fields:
     * <ul>
     *   <li><em>name</em> - the tax name, for example GST.</li>
     *   <li><em>total</em> - total tax value.</li>
     * </ul>
     * Use this method for displaying a list of order taxes on the order
     * {@link https://lsdomainexpired.mjman.net/docs/payment_receipt_page receipt page},
     * for example:
     * <pre>
     * Applied sales taxes:
     *   <? foreach ($order->list_item_taxes() as $tax): ?>
     *   <?= ($tax->name) ?>: <?= format_currency($tax->total) ?><br/>
     * <? endforeach ?>
     * </pre>
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/payment_receipt_page Order receipt page
     * @return array Returns an array
     */
    public function list_item_taxes()
    {
        $result = array();

        if (!strlen($this->sales_taxes)) {
            return $result;
        }

        try {
            $taxes = unserialize($this->sales_taxes);
            foreach ($taxes as $tax_name => $tax_info) {
                if ($tax_info->total > 0) {
                    $this->add_tax_item($result, $tax_name, $tax_info->total, 0, 'Sales tax');
                }
            }
        } catch (\Exception $ex) {
            return $result;
        }

        return $result;
    }

    public function set_sales_taxes($taxes)
    {
        if (!is_array($taxes)) {
            $taxes = array();
        }

        $taxes_to_save = $taxes;
        $this->sales_taxes = serialize($taxes_to_save);
    }

    /**
     * Returns a list of taxes applied to the order shipping service.
     * The method returns an array of objects containing the following fields:
     * <ul>
     *   <li><em>name</em> - the tax name, for example GST.</li>
     *   <li><em>total</em> - total tax value.</li>
     * </ul>
     * Use this method for displaying a list of order taxes on the order
     * {@link https://lsdomainexpired.mjman.net/docs/payment_receipt_page receipt page},
     * for example:
     * <pre>
     * Applied shipping taxes:
     *   <? foreach ($order->list_shipping_taxes() as $tax): ?>
     *   <?= ($tax->name) ?>: <?= format_currency($tax->total) ?><br/>
     * <? endforeach ?>
     * </pre>
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/payment_receipt_page Order receipt page
     * @param array $taxes Specifies a list of existing taxes. This parameter is used by LSAPP internally.
     * @return array Returns an array
     */
    public function list_shipping_taxes($result = null)
    {
        if (!$result) {
            $result = array();
        }

        if ($this->shipping_tax_1 > 0) {
            $this->add_tax_item($result, $this->shipping_tax_name_1, $this->shipping_tax_1, 0);
        }

        if ($this->shipping_tax_2 > 0) {
            $this->add_tax_item($result, $this->shipping_tax_name_2, $this->shipping_tax_2, 0);
        }

        return $result;
    }

    /**
     * Returns a list of sales and shipping taxes applied to the order.
     * The method returns an array of objects containing the following fields:
     * <ul>
     *   <li><em>name</em> - the tax name, for example GST.</li>
     *   <li><em>total</em> - total tax value.</li>
     * </ul>
     * Use this method for displaying a list of order taxes on the order
     * {@link https://lsdomainexpired.mjman.net/docs/payment_receipt_page receipt page},
     * for example:
     * <pre>
     * Applied taxes:
     *   <? foreach ($order->list_all_taxes() as $tax): ?>
     *   <?= ($tax->name) ?>: <?= format_currency($tax->total) ?><br/>
     * <? endforeach ?>
     * </pre>
     * @documentable
     * @see https://lsdomainexpired.mjman.net/docs/payment_receipt_page Order receipt page
     * @return array Returns an array
     */
    public function list_all_taxes()
    {
        $result = $this->list_item_taxes();
        return $this->list_shipping_taxes($result);
    }

    protected function add_tax_item(&$list, $name, $amount, $discount, $default_name = 'tax')
    {
        if (!$name) {
            $name = $default_name;
        }

        if (!array_key_exists($name, $list)) {
            $tax_info = array('name' => $name, 'amount' => 0, 'discount' => 0, 'total' => 0);
            $list[$name] = (object)$tax_info;
        }

        $list[$name]->amount += $amount;
        $list[$name]->discount += $discount;
        $list[$name]->total += ($amount - $discount);
    }

    public static function get_rss($record_number = 20)
    {
        $orders = Order::create();
        $orders->order('id desc');
        $orders = $orders->limit($record_number)->find_all();

        $root_url = Phpr::$request->getRootUrl();

        $rss = new Rss(
            'LSAPP Order List',
            $root_url . url('shop/orders'),
            'A list of recent orders in LSAPP',
            $root_url . url('shop/orders/rss')
        );

        $gmt_zone = new \DateTimeZone('GMT');
        $user_zone = Date::getUserTimezone();

        foreach ($orders as $order) {
            $link = $root_url . url('shop/orders/preview/' . $order->id);

            $order_items = $order->items;
            $item_descriptions = array();
            foreach ($order_items as $index => $item) {
                $item_descriptions[] = ($index + 1) . '. ' . h($item->output_product_name(
                    true,
                    true
                )) . ' x ' . $item->quantity . ' (' . $item->product->sku . ')';
            }

            $item_descriptions = implode('<br/>', $item_descriptions);
            $item_body = '<p>Order #' . $order->id . ', created: ' . $order->order_datetime->format('%x %X');
            $item_body .= '<br/>Total: ' . $order->format_currency($order->total) . '</p>';
            $item_body .= '<p><strong>Customer</strong><br/>';
            $item_body .= h($order->billing_first_name) . ' ' . h($order->billing_last_name);
            $item_body .=' (' . $order->columnValue('billing_country') . '), ' . $order->billing_email . '</p>';
            $order_datetime = $order->order_datetime;
            $order_datetime->assignTimeZone($user_zone);
            $order_datetime->setTimeZone($gmt_zone);

            $rss->add_entry(
                'Order #' . $order->id,
                $link,
                $order->id,
                $order_datetime,
                $item_descriptions,
                $order_datetime,
                'LSAPP',
                $item_body . '<p><strong>Items</strong><br/>' . $item_descriptions . '</p>'
            );
        }

        return $rss->to_xml();
    }

    /**
     * Returns the order invoice date according the invoice settings (System/Settings/Company Information and Settings)
     */
    public function get_invoice_date()
    {
        $ci = CompanyInformation::get();
        if (!$ci) {
            return null;
        }

        if ($ci->invoice_date_source == 'order_date') {
            return $this->order_datetime;
        }

        if ($ci->invoice_date_source == 'print_date') {
            return Date::userDate(PhprDateTime::gmtNow());
        }

        $parts = explode(':', $ci->invoice_date_source);
        if (count($parts) != 2) {
            return null;
        }

        $status_id = trim($parts[1]);
        if (!preg_match('/^[0-9]+$/', $status_id)) {
            return null;
        }

        $transition = OrderStatusLog::get_latest_transition_to($this->id, $status_id);
        if (!$transition) {
            return null;
        }

        return $transition->created_at;
    }

    public function create_order_copy(
        $items = array(),
        $save = true,
        $session_key = null,
        $order = null,
        $apply_discounts = true
    ) {
        if (!$order) {
            $order = self::create();
            $order->init_columns_info();
        }

        $order->coupon_id = $this->coupon_id;

        /*
         * Apply customer information
         */

        $customer = $this->customer;
        if (!$customer) {
            throw new ApplicationException('Customer for order #' . $this->id . ' not found.');
        }

        $customer->copy_from_order($this);
        $customer->copy_to_order($order);
        $order->customer_id = $this->customer_id;

        TaxClass::set_tax_exempt($this->tax_exempt);
        TaxClass::set_customer_context($customer);

        /*
         * Set order items
         */
        if (!$items) {
            $items = array();
            foreach ($this->items as $item) {
                $new_item = OrderItem::create()->copy_from($item);
                $new_item->save();
                $items[] = $new_item;
            }
        }

        $session_key = $session_key ? $session_key : uniqid('front_end_order', true);
        foreach ($items as $item) {
            $order->items->add($item, $session_key);
        }

        $cart_items = OrderHelper::items_to_cart_items_array($items);

        /*
         * Set shipping and payment methods
         */

        $order->shipping_method_id = $this->shipping_method_id;
        $order->shipping_sub_option = $this->shipping_sub_option;

        $order->payment_method_id = $this->payment_method_id;

        /*
         * Calculate shipping cost
         */
        $shipping_options = $order->list_available_shipping_options($session_key, false);

        if (!array_key_exists($order->shipping_method_id, $shipping_options)) {
            throw new ApplicationException('Shipping method ' . $this->shipping_method->name . ' is not applicable.');
        }

        $shipping_method = $shipping_options[$order->shipping_method_id];

        if (!$shipping_method->multi_option) {
            $order->shipping_quote = round($shipping_method->quote_no_tax, 2);
            $order->shipping_discount = isset($shipping_method->discount) ? round($shipping_method->discount, 2) : 0;
            $order->shipping_sub_option = null;
            $order->internal_shipping_suboption_id = $order->shipping_method_id;
        } else {
            $sub_option_id = md5($order->shipping_sub_option);
            $option_found = false;

            foreach ($shipping_method->sub_options as $sub_option) {
                $internalIdHash =  $order->shipping_method_id . '_' . $sub_option_id;
                $internalId =  $order->shipping_method_id . '_' . $sub_option->suboption_id;
                if ($sub_option->id == $internalIdHash) {
                    $order->shipping_quote = round($sub_option->quote_no_tax, 2);
                    $order->shipping_discount = round($sub_option->discount, 2);
                    $order->shipping_sub_option = $sub_option->name;
                    $order->internal_shipping_suboption_id = $internalId;
                    $option_found = true;
                    break;
                }
            }

            if (!$option_found) {
                $dispName =  $this->shipping_method->name . '/' . $order->shipping_sub_option;
                throw new ApplicationException(
                    'Shipping method ' . $dispName . ' is not applicable.'
                );
            }
        }

        /*
         * Apply shipping tax
         */

        $shipping_info = new AddressInfo();
        $shipping_info->act_as_billing_info = false;
        $shipping_info->load_from_customer($customer);

        $shipping_taxes = OrderHelper::get_shipping_taxes($order);
        $order->apply_shipping_tax_array($shipping_taxes);
        $order->shipping_tax = TaxClass::eval_total_tax($shipping_taxes);

        if ($this->free_shipping) {
            $order->shipping_quote = 0;
            $order->shipping_discount = 0;
            $order->shipping_tax = 0;
        }

        /*
         * Calculate discounts
         */

        if ($apply_discounts) {
            $payment_method_obj = PaymentMethod::find_by_id($this->payment_method_id);

            $cart_items = OrderHelper::items_to_cart_items_array($items);

            $subtotal = 0;
            foreach ($cart_items as $cart_item) {
                $subtotal += $cart_item->total_price_no_tax(false);
            }

            $discount_info = CartPriceRule::evaluate_discount(
                $payment_method_obj,
                $shipping_method,
                $cart_items,
                $shipping_info,
                $this->columnValue('coupon'),
                $customer,
                $subtotal
            );

            $order->discount = $discount_info->cart_discount;

            $order->free_shipping = array_key_exists(
                $order->internal_shipping_suboption_id,
                $discount_info->free_shipping_options
            );
            foreach ($cart_items as $cart_item) {
                $cart_item->order_item->discount = $cart_item->total_discount_no_tax();
            }
        }

        $tax_context = array(
            'backend_call' => true,
            'order' => $order
        );
        $tax_info = TaxClass::calculate_taxes($cart_items, $shipping_info, $tax_context);


        /*
         * Apply tax
         */

        $order->goods_tax = $goods_tax = $tax_info->tax_total;
        $order->set_sales_taxes($tax_info->taxes);

        foreach ($items as $item_index => $item) {
            if (array_key_exists($item_index, $tax_info->item_taxes)) {
                $item->apply_tax_array($tax_info->item_taxes[$item_index]);
            }

            $item->save();
            $item->eval_custom_columns();
        }

        /*
         * Save the order
         */

        $discount = 0;
        $subtotal = 0;
        $total_cost = 0;
        foreach ($items as $item) {
            $subtotal += $item->subtotal;
            $discount += $item->discount;
            $total_cost += $item->product->cost * $item->quantity;
        }

        $order->total_cost = $total_cost;
        $order->subtotal = $subtotal;
        $order->discount = $order->subtotal - $this->subtotal_before_discounts;
        $order->total = $order->get_order_total();

        if ($save) {
            $order->save(null, $session_key);
        }

        return $order;
    }

    /**
     * Creates a sub-order
     * @param array $items An array containing a list of order items (OrderItem)
     * @return Order Returns a new Order object
     */
    public function create_sub_order(
        $items = array(),
        $save = true,
        $session_key = null,
        $order = null,
        $apply_discounts = true
    ) {
        $session_key = $session_key ? $session_key : uniqid('front_end_order', true);
        $sub_order = $this->create_order_copy($items, false, $session_key, $order, $apply_discounts);
        $sub_order->parent_order_id = $this->id;
        if ($save) {
            $sub_order->save(null, $session_key);
        }

        return $sub_order;
    }

    public function get_total_weight()
    {
        $weight = 0;
        foreach ($this->items as $item) {
            $weight += $item->total_weight();
        }

        return $weight;
    }

    public function display_total_weight($return = false)
    {
        $string = $this->get_total_weight() . ' ' . ShippingParams::get()->weight_unit;
        if ($return) {
            return $string;
        }
        echo $string;
    }

    public function get_csv_import_columns($import = true)
    {
        $columns = $this->get_column_definitions();
        $import_columns = array();
        foreach ($columns as $column_name => $column) {
            if ($column_name == 'id') {
                $column->listTitle = 'Order ID';
                $column->displayName = 'Order ID';
                $column->validation()->required();
                $import_columns[$column_name] = $column;
            }
        }
        $column_info = array(
            'dbName' => 'shop_order_shipping_track_codes',
            'displayName' => 'Tracking codes',
            'listTitle' => 'Tracking codes',
            'type' => db_text
        );
        $import_columns['shop_order_shipping_track_codes'] = (object)$column_info;
        return $import_columns;
    }

    public static function export_orders_and_products_header($header)
    {
        array_push(
            $header,
            'Line number',
            'Product Description',
            'Product SKU',
            'Price',
            'Discount',
            'Quantity',
            'Item Total',
            'Item Tax Total'
        );
        return $header;
    }

    /**
     * Outputs a file of a downloadable product.
     * This method allows to create custom pages for downloading product files.
     * This method stops the script execution in case if the file is successfully returned to the browser.
     * Please read the {@link http://lemonstandapp.com/docs/implementing_downloadable_products/
     * Integrating downloadable products}
     * documentation article for the usage examples.
     * @documentable
     * @see http://lemonstandapp.com/docs/implementing_downloadable_products/ Integrating downloadable products
     * @param int $file_id Specifies an identifier of a {@link ProductFile product file}.
     * @param string $mode Specifies the file disposition - <em>attachment</em> or <em>inline</em>.
     * Depending on the mode the browser either displays the file contents or
     * offers to download it.
     */
    public function output_product_file($file_id, $mode = 'attachment')
    {
        foreach ($this->items as $item) {
            foreach ($item->product->files as $file) {
                if ($file->id == $file_id) {
                    if ($mode != 'inline' || $mode != 'attachment') {
                        $mode = 'inline';
                    }

                    $file->output($mode);
                    die();
                }
            }
        }
    }

    /*
    * @param Order $row
    * @param array $row_data
    * @param string $separator
    */
    public static function export_orders_and_products_row($row, $row_data, $separator)
    {
        $order_items = DbHelper::queryArray('
				select 
					sp.name as product_name, 
					sp.grouped_option_desc, 
					if(
						ifnull(sp.grouped, 0) = 0, 
						sp.grouped_attribute_name, 
						(select grouped_attribute_name from shop_products gl where gl.id=sp.product_id)
					) as grouped_menu_label, 
					sp.sku as product_sku,
					om.sku as om_sku,
					soi.* 
				from 
					shop_order_items soi 
				inner join shop_products sp on (sp.id = soi.shop_product_id) 
				left join shop_option_matrix_records om on om.id = soi.option_matrix_record_id
				where 
					soi.shop_order_id=:id', array('id' => $row->id));

        if (count($order_items)) {
            $item_obj = OrderItem::create();
            foreach ($order_items as $index => $item) {
                /*
                 * Fill the order item object
                 */
                $item_row = $row_data;
                $item_obj->reset_custom_columns();
                $item_obj->fill($item);

                /*
                 * Format item description
                 */
                $desc = $item_obj->product_name;

                if (!Phpr::$config->get('DISABLE_GROUPED_PRODUCTS') && $item['grouped_option_desc']) {
                    $desc .= "\n" . $item['grouped_menu_label'] . ': ' . $item['grouped_option_desc'];
                }

                $options = $item_obj->get_options();
                $option_list = array();
                foreach ($options as $name => $value) {
                    $option_list[] = $name . ': ' . $value;
                }

                if ($option_list) {
                    $desc .= "\n " . implode('; ', $option_list);
                }

                $extra_options = $item_obj->get_extra_options();
                $extra_option_list = array();
                foreach ($extra_options as $value) {
                    $extra_option_list[] = '+ ' . $value[1] . ': ' . format_currency($value[0]);
                }

                if ($extra_option_list) {
                    $desc .= "\n " . implode(",\n ", $extra_option_list);
                }

                /*
                 * Output the row
                 */
                $item_total = format_currency(($item_obj->single_price - $item_obj->discount) * $item_obj->quantity);

                array_push(
                    $item_row,
                    $index + 1,
                    $desc,
                    $item_obj->om_sku ? $item_obj->om_sku : $item_obj->product_sku,
                    format_currency($item_obj->single_price),
                    format_currency($item_obj->discount),
                    $item_obj->quantity,
                    $item_total,
                    format_currency($item_obj->tax_2 + $item_obj->tax)
                );
                Csv::outputCsvRow($item_row, $separator);
            }
        } else {
            Csv::outputCsvRow($row_data, $separator);
        }
    }

    public function lock_order()
    {
        Backend::$events->fireEvent('shop:onBeforeOrderRecordLocked', $this);
        $this->locked = 1;
    }

    public function unlock_order()
    {
        Backend::$events->fireEvent('shop:onBeforeOrderRecordUnlocked', $this);
        $this->locked = null;
    }

    public function is_order_locked()
    {
        if ($this->locked) {
            return true;
        }
        return false;
    }

    public function get_order_total()
    {
        return $this->goods_tax + $this->subtotal + $this->get_shipping_quote_discounted() + $this->shipping_tax;
    }

    public function get_total_discount_applied()
    {
        return round($this->discount + $this->get_shipping_discount(), 2);
    }

    public function eval_shipping_quote_no_discount()
    {
        return $this->get_shipping_quote_no_discount();
    }

    public function has_shipping_quote_override()
    {
        if ($this->override_shipping_quote && Number::isValidFloat($this->manual_shipping_quote)) {
            return true;
        }
        return false;
    }

    public function get_shipping_quote()
    {
        if ($this->has_shipping_quote_override()) {
            return $this->manual_shipping_quote;
        }
        return $this->shipping_quote;
    }

    public function get_shipping_quote_no_discount()
    {
        return max(($this->get_shipping_quote() + $this->get_shipping_discount(false)), 0);
    }

    public function eval_shipping_quote_discounted()
    {
        return $this->get_shipping_quote_discounted();
    }

    public function get_shipping_quote_discounted()
    {
        return max(($this->get_shipping_quote_no_discount() - $this->get_shipping_discount()), 0);
    }

    public function eval_total_shipping_discount()
    {
        return $this->get_shipping_discount(true);
    }

    public function get_shipping_discount($extended = true)
    {
        if ($this->has_shipping_quote_override()) {
            return 0;
        }
        if ($extended) {
            return max(($this->shipping_discount + $this->get_extended_shipping_discounts()), 0);
        }
        return $this->shipping_discount;
    }


    public function get_item_count()
    {
        $total_item_num = 0;
        foreach ($this->items as $item) {
            $total_item_num += $item->quantity;
        }
        return $total_item_num;
    }

    public function get_total_volume()
    {
        $total_volume = 0;
        foreach ($this->items as $item) {
            $total_volume += $item->total_volume();
        }
        return $total_volume;
    }

    public function list_available_shipping_options($deferred_session_key = null, $frontend_only = false)
    {
        $items = $this->items;
        $deferred_items = empty($deferred_session_key) ? false : $this->list_related_records_deferred(
            'items',
            $deferred_session_key
        );
        if ($deferred_items && $deferred_items->count) {
            $this->items = $deferred_items; //consider deferred assignments in these calculations
        }
        $include_tax = false;
        $payment_method = $this->get_payment_method();
        $shipping_info = new AddressInfo();
        $shipping_info->load_from_order($this, false);
        $coupon = $this->coupon_id ? Coupon::create()->find($this->coupon_id) : null;
        $coupon_code = $coupon ? $coupon->code : null;
        $customer = $this->customer_id ? Customer::create()->find($this->customer_id) : null;

        $params = array(
            'order' => $this,
            'shipping_info' => $shipping_info,
            'total_price' => $this->eval_subtotal_before_discounts(),
            'total_volume' => $this->get_total_volume(),
            'total_weight' => $this->get_total_weight(),
            'total_item_num' => $this->get_item_count(),
            'include_tax' => $include_tax,
            'display_prices_including_tax' => $include_tax ? $include_tax : CheckoutData::display_prices_incl_tax(),
            //out of scope
            'return_disabled' => false,
            'order_items' => $this->items,
            'cart_items' => OrderHelper::items_to_cart_items_array($this->items),
            'customer_group_id' => null,
            'customer' => $customer,
            'shipping_option_id' => null,
            'backend_only' => $frontend_only ? null : true,
            'payment_method' => $payment_method,
            'currency_code' => $this->get_currency_code(),
            'coupon_code' => $coupon_code,
        );
        $result = ShippingOption::get_applicable_options($params);
        if ($deferred_items) {
            $this->items = $items; //restore items
        }
        return $result;
    }

    public function eval_order_reference()
    {
        return $this->get_order_reference();
    }


    public function get_extended_shipping_discounts()
    {
        $external_discount = 0;
        $results = Backend::$events->fireEvent('shop:onOrderGetShippingDiscount', $this);
        if ($results) {
            foreach ($results as $discount_amount) {
                if (is_numeric($discount_amount)) {
                    $external_discount += abs($discount_amount);
                }
            }
        }
        return $external_discount;
    }

    public function get_items_shipping()
    {
        $items_shipping = array();
        foreach ($this->items as $item) {
            $shipping_enabled = isset($item->product->product_type->id) ? $item->product->product_type->shipping : true;
            if ($shipping_enabled) {
                $items_shipping[] = $item;
            }
        }
        return $items_shipping;
    }

    public function count_items_shipping($by_quantity = true)
    {
        $items_shipping = $this->get_items_shipping();
        if (!$by_quantity) {
            return count($items_shipping);
        }
        $count = 0;
        foreach ($items_shipping as $item) {
            $count += $item->quantity;
        }
        return $count;
    }

    protected function after_fetch()
    {
        //All order items inherit order currency
        foreach ($this->items as $item) {
            $item->set_currency_code($this->get_currency_code());
        }
        Backend::$events->fireEvent('shop:onAfterOrderRecordFetch', $this);
    }


    /**
     * @param $country_id
     *
     * @return array|string[]
     * @deprecated
     */
    public function list_states($country_id)
    {
        return $this->get_country_state_options($country_id);
    }

    /*
     * Event descriptions
     */

    /**
     * Allows to define new columns in the order model.
     * The event handler should accept two parameters - the order object and the form
     * execution context string. To add new columns to the order model,
     * call the {@link Db_ActiveRecord::define_column() define_column()}
     * method of the order object. Before you add new columns to the model, you should add them to the
     * database (the <em>shop_orders</em> table).
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderModel', $this, 'extend_order_model');
     *   Backend::$events->addEvent('shop:onExtendOrderForm', $this, 'extend_order_form');
     * }
     *
     * public function extend_order_model($order, $context)
     * {
     *   $order->define_column('x_extra_description', 'Extra description');
     * }
     *
     * public function extend_order_form($order, $context)
     * {
     *   $order->add_form_field('x_extra_description')->tab('Billing Information');
     * }
     * </pre>
     * @event shop:onExtendOrderModel
     * @param Order $order Specifies the order object.
     * @param string $context Specifies the execution context.
     * @see shop:onExtendOrderForm
     * @see shop:onGetOrderFieldOptions
     * @see shop:onGetOrderFieldState
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onExtendOrderModel($order, $context)
    {
    }

    /**
     * Allows to add new fields to the Create/Edit Order and Preview Order forms in the Administration Area.
     * Usually this event is used together with the {@link shop:onExtendOrderModel} event.
     * To add new fields to the order form, call the
     * {@link Db_ActiveRecord::add_form_field() add_form_field()} method of the
     * order object.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderModel', $this, 'extend_order_model');
     *   Backend::$events->addEvent('shop:onExtendOrderForm', $this, 'extend_order_form');
     * }
     *
     * public function extend_order_model($order, $context)
     * {
     *   $order->define_column('x_extra_description', 'Extra description');
     * }
     *
     * public function extend_order_form($order, $context)
     * {
     *   $order->add_form_field('x_extra_description')->tab('Billing Information');
     * }
     * </pre>
     * @event shop:onExtendOrderForm
     * @param Order $order Specifies the order object.
     * @param string $context Specifies the execution context.
     * This parameter value is <em>preview</em> if the form rendered on the Order Preview page.
     * @see shop:onExtendOrderModel
     * @see shop:onGetOrderFieldOptions
     * @see shop:onGetOrderFieldState
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onExtendOrderForm($order, $context)
    {
    }

    /**
     * Allows to populate drop-down, radio- or checkbox list fields,
     * which have been added with {@link shop:onExtendOrderForm} event.
     * Usually you do not need to use this event for fields which represent
     * {@link https://lsdomainexpired.mjman.net/docs/extending_models_with_related_columns data relations}.
     * But if you want a standard
     * field (corresponding an integer-typed database column, for example),
     * to be rendered as a drop-down list, you should
     * handle this event.
     *
     * The event handler should accept 2 parameters - the field name and a current field value. If the current
     * field value is -1, the handler should return an array containing a list of options. If the current
     * field value is not -1, the handler should return a string (label), corresponding the value.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderModel', $this, 'extend_order_model');
     *   Backend::$events->addEvent('shop:onExtendOrderForm', $this, 'extend_order_form');
     *   Backend::$events->addEvent('shop:onGetOrderFieldOptions', $this, 'get_order_options');
     * }
     *
     * public function extend_order_model($order, $context)
     * {
     *   $order->define_column('x_gender', 'Gender');
     * }
     *
     * public function extend_order_form($order, $context)
     * {
     *   $order->add_form_field('x_gender')->tab('Billing Information');
     * }
     *
     * public function get_order_options($field_name, $current_value)
     * {
     *   if ($field_name == 'x_gender')
     *   {
     *     $options = array('male'=>'Male', 'female'=>'Female', 'unisex'=>'Unisex');
     *     if ($current_value == -1)
     *       return $options;
     *
     *     if (array_key_exists($current_value, $options))
     *       return $options[$current_value];
     *   }
     * }
     * </pre>
     * @event shop:onGetOrderFieldOptions
     * @param string $db_name Specifies the field name.
     * @param string $field_value Specifies the field value.
     * @return mixed Returns a list of options or a specific option label.
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onExtendOrderModel
     * @see shop:onExtendOrderForm
     * @see shop:onGetOrderFieldState
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     */
    private function event_onGetOrderFieldOptions($db_name, $field_value)
    {
    }

    /**
     * Determines whether a custom radio button or checkbox list option is checked.
     * This event should be handled if you added custom radio-button and or checkbox list fields with
     * {@link shop:onExtendOrderForm} event.
     * @event shop:onGetOrderFieldState
     * @param string $db_name Specifies the field name.
     * @param string $field_value Specifies the field value.
     * @param Order $order Specifies the order object.
     * @return bool Returns TRUE if the field is checked. Returns FALSE otherwise.
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onExtendOrderModel
     * @see shop:onExtendOrderForm
     * @see shop:onGetOrderFieldOptions
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     */
    private function event_onGetOrderFieldState($db_name, $field_value, $order)
    {
    }

    /**
     * Triggered when order billing information is copied to shipping information.
     * This event is triggered when a user clicks <em>Copy billing address</em> link on
     * the Create/Edit Order page in the Administration Area
     * and allows to override order property values. The <em>$data</em> parameter of the
     * event handler is an array with the following elements:
     * <ul>
     * <li><em>billing_first_name</em> - specifies the billing first name.</li>
     * <li><em>billing_last_name</em> - specifies the billing last name.</li>
     * <li><em>billing_company</em> - specifies the billing company name.</li>
     * <li><em>billing_phone</em> - specifies the billing phone number.</li>
     * <li><em>billing_country_id</em> - specifies the billing {@link Country country} identifier.</li>
     * <li><em>billing_state_id</em> - specifies the billing {@link CountryState state} identifier.</li>
     * <li><em>billing_street_addr</em> - specifies the billing street address.</li>
     * <li><em>billing_city</em> - specifies the billing city name.</li>
     * <li><em>billing_zip</em> - specifies the billing ZIP/postal code.</li>
     * </ul>
     * @event shop:onOrderCopyBillingAddress
     * @param Order $order Specifies the order object.
     * @param array $data Contains the shipping information data fields.
     * @return array Return an associative array of order property names and
     *  values to override the order's shipping information.
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onOrderCopyBillingAddress($order, $data)
    {
    }

    /**
     * Allows to enable the invoice support for a specific order.
     * By default the invoice support is disabled, but some modules, for example
     * {@link https://lsdomainexpired.mjman.net/docs/subscriptions_module Subscriptions Module}, require it.
     * When the invoice support is enabled for specific order, the Order Preview form displays the Invoices tab,
     * which contains a list of the order invoices.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderSupportsInvoices', $this, 'eval_order_supports_invoices');
     * }
     *
     * public function eval_order_supports_invoices($order)
     * {
     *   foreach ($order->items as $item)
     *   {
     *     if ($item->product)
     *     {
     *       if ($item->product->subscription_plan)
     *         return true;
     *     }
     *   }
     *
     *   return false;
     * }
     * </pre>
     * @event shop:onOrderSupportsInvoices
     * @param Order $order Specifies the order object.
     * @return bool Returns TRUE if invoices are supported by the order. Returns FALSE otherwise.
     * @author LSAPP - MJMAN
     * @package shop.events
     * @see shop:onInvoiceSystemSupported
     */
    private function event_onOrderSupportsInvoices($order)
    {
    }

    /**
     * Allows to enable the invoice support in LSAPP.
     * By default invoices are disabled, and Administration Area user interface does not display any controls and labels
     * related to invoices. Some modules, for example
     * {@link https://lsdomainexpired.mjman.net/docs/subscriptions_module Subscriptions Module},
     * require invoice support and this event allows them
     * to enable it.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onInvoiceSystemSupported', $this, 'process_invoice_system_supported');
     * }
     *
     * public function process_invoice_system_supported()
     * {
     *   return true;
     * }
     * </pre>
     * @event shop:onInvoiceSystemSupported
     * @return bool Returns TRUE, if the invoice support is enabled. Returns FALSE otherwise.
     * @see shop:onOrderSupportsInvoices
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onInvoiceSystemSupported()
    {
    }

    /**
     * Allows to enable the automated bulling features in LSAPP.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onAutomatedBillingSupported', $this, 'process_auto_billing_supported');
     * }
     *
     * public function process_auto_billing_supported()
     * {
     *   return true;
     * }
     * </pre>
     * @event shop:onAutomatedBillingSupported
     * @return bool Returns TRUE, if the automated billing features are enabled. Returns FALSE otherwise.
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onAutomatedBillingSupported()
    {
    }

    /**
     * Allows you to alter the checkout information or cart content before an order is
     * {@link Order::place_order() placed}.
     * This event is fired only when an order is placed from the front-end website.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderBeforeCreate', $this, 'process_order');
     * }
     *
     * public function process_order($cart_name)
     * {
     *   $price = Cart::total_price($cart_name);
     *   if ($price < 100)
     *     throw new ApplicationException('We are sorry,
     *       you cannot place orders with total order amount less than 100 USD');
     * }
     * </pre>
     * @event shop:onOrderBeforeCreate
     * @param string $cart_name Specifies the shopping cart name.
     * @author LSAPP - MJMAN
     * @see shop:onOrderError
     * @package shop.events
     */
    private function event_onOrderBeforeCreate($cart_name)
    {
    }

    /**
     * Triggered if an error occurs during the {@link Order::place_order() order placement}.
     * This event is triggered only if the order is created from the front-end store pages.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderError', $this, 'process_order_error');
     * }
     *
     * public function process_order_error($cart_name, $error_message)
     * {
     *   // Do something
     * }
     * </pre>
     * @event shop:onOrderError
     * @param string $cart_name Specifies the shopping cart name.
     * @param string $error_message Specifies the error message.
     * @see shop:onOrderBeforeCreate
     * @see shop:onNewOrder
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onOrderError($cart_name, $error_message)
    {
    }

    /**
     * Triggered before a new order record is saved to the database.
     * Unlike the {@link shop:onOrderBeforeCreate} event, this event is triggered even if the order is created
     * from the Administration Area. In case if the order is created from the front-end website,
     * this event triggers after the {@link shop:onOrderBeforeCreate}.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onBeforeOrderRecordCreate', $this, 'process_new_order_record');
     * }
     *
     * public function process_new_order_record($order, $session_key)
     * {
     *   // Load the item list
     *   $items = $order->list_related_records_deferred('items', $deferred_session_key);
     *
     *   // Perform some check
     *   foreach ($items as $item)
     *   {
     *     if ($item->quantity > 10)
     *       throw new ApplicationException('Error!');
     *   }
     * }
     * </pre>
     * @event shop:onBeforeOrderRecordCreate
     * @param Order $order Specifies the order object.
     * @param string $session_key Specifies the form session key.
     * @see shop:onOrderBeforeCreate
     * @see shop:onOrderBeforeUpdate
     * @see shop:onNewOrder
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onBeforeOrderRecordCreate($order, $session_key)
    {
    }

    /**
     * Triggered before an existing order record is saved to the database.
     * Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderBeforeUpdate', $this, 'process_order_update');
     * }
     *
     * public function process_order_update($order, $session_key)
     * {
     *   // Perform some check
     *   if ($order->total > 10000)
     *     throw new ApplicationException('Order total should not exceed $10,000');
     *
     *   $items = $order->list_related_records_deferred('items', $session_key);
     * }
     * </pre>
     * @event shop:onOrderBeforeUpdate
     * @param Order $order Specifies the order object.
     * @param string $session_key Specifies the form session key.
     * @see shop:onBeforeOrderRecordCreate
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onOrderBeforeUpdate($order, $session_key)
    {
    }

    /**
     * Triggered after a new order is placed.
     * Inside the event handler you can perform further order processing.
     * Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onNewOrder', $this, 'process_new_order');
     * }
     *
     * public function process_new_order($order_id)
     * {
     *   $order = Order::create()->find($order_id);
     *   foreach ($order->items as $item)
     *   {
     *     // Do something with order items
     *   }
     * }
     * </pre>
     * @event shop:onNewOrder
     * @param int $order_id Specifies the order identifier.
     * @author LSAPP - MJMAN
     * @see shop:onBeforeOrderRecordCreate
     * @package shop.events
     */
    private function event_onNewOrder($order_id)
    {
    }

    /**
     * Triggered after an order has been marked deleted with {@link Order::delete_order()} method.
     * Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderMarkedDeleted', $this, 'process_order_marked_deleted');
     * }
     *
     * public function process_order_marked_deleted($order)
     * {
     *   // Do something
     *   //
     * }
     * </pre>
     * @event shop:onOrderMarkedDeleted
     * @param Order $order Specifies the order object.
     * @author LSAPP - MJMAN
     * @see shop:onOrderRestored
     * @see Order::delete_order()
     * @package shop.events
     */
    private function event_onOrderMarkedDeleted($order)
    {
    }

    /**
     * Triggered after an order has been restored with {@link Order::restore_order()} method.
     * Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderMarkedDeleted', $this, 'process_order_marked_deleted');
     * }
     *
     * public function process_order_marked_deleted($order)
     * {
     *   // Do something
     *   //
     * }
     * </pre>
     * @event shop:onOrderRestored
     * @param Order $order Specifies the order object.
     * @author LSAPP - MJMAN
     * @see shop:onOrderMarkedDeleted
     * @see Order::restore_order()
     * @package shop.events
     */
    private function event_onOrderRestored($order)
    {
    }

    /**
     * Triggered after an order record has been deleted.
     * Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onOrderAfterDelete', $this, 'process_order_deletion');
     * }
     *
     * public function process_order_deletion($order)
     * {
     *   // Delete order custom data
     *   //
     * }
     * </pre>
     * @event shop:onOrderAfterDelete
     * @param Order $order Specifies the order object.
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onOrderAfterDelete($order)
    {
    }

    /**
     * Triggered after an order record is fetched from the database.
     * @event shop:onAfterOrderRecordFetch
     * @param Order $order Specifies the order object.
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onAfterOrderRecordFetch($order)
    {
    }

    /**
     * Triggered before orders RSS feed is generated.
     * The event handler can output another implementation of the feed and stop the script execution.
     * @event shop:onBeforeOrdersRssExport
     * @triggered /modules/shop/controllers/shop_orders.php
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onBeforeOrdersRssExport()
    {
    }

    /**
     * Allows to configure the Administration Area order pages before they are displayed.
     * In the event handler you can update the back-end controller properties,
     * for example add a filter to the orders list.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onConfigureOrdersPage', $this, 'add_orders_filter');
     * }
     *
     * public function add_orders_filter($controller)
     * {
     *   $controller->filter_filters['billing_state'] = array(
     *     'name'=>'Billing state',
     *     'class_name'=>'BillingStateFilter',
     *     'prompt'=>'Please choose billing states you want to include to the list.
     * Orders with other billing states will be hidden.',
     *     'added_list_title'=>'Added Billing States'
     *   );
     * }
     * </pre>
     * @event shop:onConfigureOrdersPage
     * @triggered /modules/shop/controllers/shop_orders.php
     * @param Orders $controller Specifies the controller object.
     * @see shop:onDisplayOrdersPages
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onExtendOrderPreviewToolbar
     */
    private function event_onConfigureOrdersPage($controller)
    {
    }

    /**
     * Allows to load extra CSS or JavaScript files on the Order List, Order Preview,
     * Create/Edit Order and other back-end pages related to orders.
     * The event handler should accept a single parameter - the controller object reference.
     * Call addJavaScript() and addCss() methods of the controller object to add references to JavaScript and
     * CSS files. Use paths relative to LSAPP installation URL for your resource files.
     * Example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onDisplayOrdersPage', $this, 'load_resources');
     * }
     *
     * public function load_resources($controller)
     * {
     *   $controller->addJavaScript('/modules/mymodule/resources/javascript/my.js');
     *   $controller->addCss('/modules/mymodule/resources/css/my.css');
     * }
     * </pre>
     * @event shop:onDisplayOrdersPage
     * @triggered /modules/shop/controllers/shop_orders.php
     * @param Orders $controller Specifies the controller object.
     * @see shop:onConfigureOrdersPage
     * @see shop:onExtendOrdersToolbar
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onExtendOrderPreviewToolbar
     */
    private function event_onDisplayOrdersPage($controller)
    {
    }

    /**
     * Allows to add new buttons to the toolbar above the Order Preview form.
     * The event handler accepts two parameter - the controller object, which you can use for
     * rendering a partial containing new buttons and the order object.
     *
     * The following example adds the "Subscription chart" button to the order preview toolbar.
     * Similar code used in the
     * {@link https://lsdomainexpired.mjman.net/docs/subscriptions_module Subscriptions Module}.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderPreviewToolbar', $this, 'extend_order_toolbar');
     * }
     *
     * public function extend_order_toolbar($controller, $order)
     * {
     *   $controller->renderPartial(PATH_APP.'/modules/subscriptions/partials/_order_toolbar.htm',
     *     array('order'=>$order));
     * }
     *
     * // Example of the _order_toolbar.htm partial
     *
     * <? if (Subscriptions_Engine::get()->order_has_subscriptions($order->id)): ?>
     *   <div class="separator">&nbsp;</div>
     *   <?= backend_ctr_button(
     *      'Subscription chart',
     *      'subscription_chart',
     *      url('subscriptions/chart/order/'.$order->id)
     *   ) ?>
     * <? endif ?>
     * </pre>
     * @event shop:onExtendOrderPreviewToolbar
     * @triggered /modules/shop/controllers/shop_orders/preview.htm
     * @param Orders $controller Specifies the controller object.
     * @param Order $order Specifies the order object.
     * @see shop:onExtendOrderPreviewTabs
     * @see shop:onExtendOrderPreviewHeader
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onDisplayOrdersPage
     * @see shop:onConfigureOrdersPage
     */
    private function event_onExtendOrderPreviewToolbar($controller, $order)
    {
    }

    /**
     * Allows to display custom tabs on the Order Preview page in the Administration Area.
     * The event handler should accept two parameters - the controller object and the order object.
     * The handler should return an associative array of tab titles and corresponding tab partials.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderPreviewTabs', $this, 'extend_order_tabs');
     * }
     *
     * public function extend_order_tabs($controller, $order)
     * {
     *   return array(
     *     'My tab caption' => 'modules/my_module/partials/_my_partial.htm',
     *     'Second custom tab' => 'modules/my_module/partials/_another_partial.htm'
     *   );
     * }
     * </pre>
     * @event shop:onExtendOrderPreviewTabs
     * @triggered /modules/shop/controllers/shop_orders/preview.htm
     * @param Orders $controller Specifies the controller object.
     * @param Order $order Specifies the order object.
     * @return array Returns an array of tab names and tab partial paths.
     * @see shop:onExtendOrderPreviewHeader
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onDisplayOrdersPage
     * @see shop:onConfigureOrdersPage
     * @see shop:onExtendOrderPreviewToolbar
     */
    private function event_onExtendOrderPreviewTabs($controller, $order)
    {
    }

    /**
     * Allows to add new buttons to the toolbar above the order list in the Administration Area.
     * The event handler should accept a single parameter - the controller object, which you can use to render a
     * partial containing additional buttons. Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrdersToolbar', $this, 'extend_orders_toolbar');
     * }
     *
     * public function extend_orders_toolbar($controller)
     * {
     *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_orders_toolbar.htm');
     * }
     *
     * // Example of the _orders_toolbar.htm partial
     *
     * <div class="separator">&nbsp;</div>
     * <?= backend_ctr_button('My button', 'my_button_css_class', url('mymodule/manage/')) ?>
     * </pre>
     * @event shop:onExtendOrdersToolbar
     * @triggered /modules/shop/controllers/shop_orders/_orders_control_panel.htm
     * @param Orders $controller Specifies the controller object.
     * @see shop:onConfigureOrdersPage
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onDisplayOrdersPage
     */
    private function event_onExtendOrdersToolbar($controller)
    {
    }

    /**
     * Allows to add new buttons to the toolbar above the Payment Transactions list
     * on Order Preview page in the Administration Area.
     * The event handler should accept two parameter - the controller object, which you can use to render a
     * partial containing additional buttons and the order object. Event handler example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent(
     *      'shop:onExtendOrderPaymentTransactionsToolbar',
     *      $this,
     *      'extend_transactions_toolbar'
     *   );
     * }
     *
     * public function extend_transactions_toolbar($controller, $order)
     * {
     *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_transactions_toolbar.htm');
     * }
     *
     * // Example of the _transactions_toolbar.htm partial
     *
     * <div class="separator">&nbsp;</div>
     * <?= backend_ctr_button('My button', 'my_button_css_class', url('mymodule/manage/')) ?>
     * </pre>
     * @event shop:onExtendOrderPaymentTransactionsToolbar
     * @triggered /modules/shop/controllers/shop_orders/_form_area_preview_payment_transactions.htm
     * @param Orders $controller Specifies the controller object.
     * @param Order $order Specifies the order object.
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onDisplayOrdersPage
     * @see shop:onConfigureOrdersPage
     */
    private function event_onExtendOrderPaymentTransactionsToolbar($controller, $order)
    {
    }

    /**
     * Allows to display custom partials in the header of the Order Preview page in the Administration Area.
     * The event handler should accept two parameters - the controller object and the order object.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderPreviewHeader', $this, 'extend_order_preview_header');
     * }
     *
     * public function extend_order_preview_header($controller, $order)
     * {
     *   $controller->renderPartial(PATH_APP.'/modules/mymodule/partials/_orders_preview_header.htm');
     * }
     * </pre>
     * @event shop:onExtendOrderPreviewHeader
     * @triggered /modules/shop/controllers/shop_orders/preview.htm
     * @param Orders $controller Specifies the controller object.
     * @param Order $order Specifies the order object.
     * @see shop:onExtendOrderPreviewToolbar
     * @see shop:onExtendOrderPreviewTabs
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onDisplayOrdersPage
     * @see shop:onConfigureOrdersPage
     */
    private function event_onExtendOrderPreviewHeader($controller, $order)
    {
    }

    /**
     * Allows to add new button to the toolbar above the Invoice List
     * on the Order Preview page in the Administration Area.
     * The event handler should accept a single parameter
     * - the controller object, which you can use for rendering a partial,
     * containing new buttons.
     * The following example adds the "Generate subscription invoice" button to the toolbar.
     * Similar code used in the
     * {@link https://lsdomainexpired.mjman.net/docs/subscriptions_module Subscriptions Module}.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderInvoicesToolbar', $this, 'extend_invoices_toolbar');
     * }
     *
     * public function extend_invoices_toolbar($controller)
     * {
     *   $controller->renderPartial(PATH_APP.'/modules/subscriptions/partials/_invoices_toolbar.htm');
     * }
     *
     * // Example of the _invoices_toolbar.htm partial
     *
     * <div class="separator">&nbsp;</div>
     * <?= backend_ctr_button(
     *   'Generate subscription invoice',
     *   'generate_subscription_invoice',
     *   array('href'=>'#', 'onclick'=>"
     *     new PopupForm('onCustomEvent', {
     *       closeByEsc: false,
     *       ajaxFields: {custom_event_handler: 'subscriptions:onGenerateInvoice'}
     *   }); return false;
     * ")) ?>
     * </pre>
     * @event shop:onExtendOrderInvoicesToolbar
     * @triggered /modules/shop/controllers/shop_orders/_form_area_preview_invoices.htm
     * @param Orders $controller Specifies the controller object.
     * @see shop:onConfigureOrdersPage
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onDisplayOrdersPage
     */
    private function event_onExtendOrderInvoicesToolbar($controller)
    {
    }

    /**
     * Triggered before a shipping tracking code is deleted.
     * The event handler should accept two parameters
     * - the order identifier and the OrderTrackingCode object, representing the tracking code.
     * @event shop:onBeforeDeleteShippingTrackingCode
     * @triggered /modules/shop/controllers/shop_orders.php
     * @param int $order_id Specifies the order identifier
     * @param OrderTrackingCode $code Specifies the tracking code
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onBeforeDeleteShippingTrackingCode($order_id, $code)
    {
    }

    /**
     * Triggered when apply vars to order email.
     * The event handler should return an array of var name and values to apply to the order email content
     * <pre>
     *    return array(
     *     'custom_orderid_var' => 'A2031022',
     *  );
     * </pre>
     * @event shop:event_onApplyOrderEmailVars
     * @triggered /modules/shop/models/shop_order.php
     * @param Order , the order email relates to
     * @param string $status_comment , if given on status change
     * @param OrderStatus $status current order status, if given on status change
     * @author Matt Manning (github:damanic)
     *
     * @package shop.events
     */
    private function event_onApplyOrderEmailVars($order, $status_comment, $status)
    {
    }

    /**
     * Triggered when call for order reference
     * The event handler should return the order reference string
     * @event shop:onGetOrderReference
     * @triggered /modules/shop/models/shop_order.php
     * @param Order , the order email relates to
     * @author Matt Manning (github:damanic)
     * @package shop.events
     */
    private function event_onGetOrderReference($order)
    {
    }

    /**
     * Triggered when attempt to find order using an order reference string
     * The event handler should return the Order if found
     * @event shop:onOrderFindByOrderReference
     * @triggered /modules/shop/models/shop_order.php
     * @param string , the order reference
     * @author Matt Manning (github:damanic)
     * @package shop.events
     */
    private function event_onOrderFindByOrderReference($order_ref)
    {
    }

    /**
     * Triggered when attempt to lock the order
     * @event shop:onBeforeOrderRecordLocked
     * @triggered /modules/shop/models/shop_order.php
     * @param Order , the order
     * @author Matt Manning (github:damanic)
     * @package shop.events
     */
    private function event_onBeforeOrderRecordLocked($order)
    {
    }

    /**
     * Triggered when attempt to unlock the order
     * @event shop:onBeforeOrderRecordUnlocked
     * @triggered /modules/shop/models/shop_order.php
     * @param Order , the order
     * @author Matt Manning (github:damanic)
     * @package shop.events
     */
    private function event_onBeforeOrderRecordUnlocked($order)
    {
    }

    /**
     * Triggered after order is created, updated or deleted
     * @event shop:onOrderAfterModify
     * @triggered /modules/shop/models/shop_order.php
     * @param Order $order
     * @param string $operation one of values: 'created', 'updated', 'deleted'
     * @param $session_key
     * @author Matt Manning (github:damanic)
     * @package shop.events
     */
    private function event_onOrderAfterModify($order, $operation, $session_key)
    {
    }


    /**
     * Triggered when fetching the payment amount due for an order.
     * The event handler can return an amount of the order total value
     * that should not be considered payable, and therefore discounted from the
     * payment amount due.
     *
     * @event shop:onOrderGetTotalValueNotPayable
     * @triggered /modules/shop/models/shop_order.php
     *
     * @param Order $order
     * @author Matt Manning (github:damanic)
     * @package shop.events
     *
     */
    private function event_onOrderGetTotalValueNotPayable($order)
    {
    }
}