<?php
namespace Shop;

use Phpr;
use Backend;
use Core\ModuleBase;
use Core\CronManager;
use Core\ModuleManager;
use Phpr\ModuleInfo;
use Phpr\Module_Parameters as ModuleParameters;
use Phpr\DateTime as PhprDateTime;
use Phpr\ApplicationException;
use Db\Helper as DbHelper;
use Twig\TwigFunction;

class Module extends ModuleBase
{
    private $shippingTypes = null;
    private $paymentTypes = null;
    private $currencyConverters = null;
        
    private static $partials = null;
    private static $catalogVersionUpdate = false;
    
    /**
     * Creates the module information object
     * @return ModuleInfo
     */
    protected function createModuleInfo()
    {
        return new ModuleInfo(
            "Shop",
            "LSAPP shopping-cart features",
            "LSAPP - MJMAN"
        );
    }

    /**
     * Registers a list of the modules back-end GUI tabs.
     * @param \Backend\TabCollection $tabCollection A tab collection object to populate.
     * @return void
     */
    public function listTabs($tabCollection)
    {
        $user = Phpr::$security->getUser();
        $tabs = array(
            'categories'=>array('categories', 'Categories', 'manage_categories'),
            'products'=>array('products', 'Products', 'manage_products'),
            'orders'=>array('orders', 'Orders', 'manage_orders_and_customers'),
            'customers'=>array('customers', 'Customers', 'manage_orders_and_customers'),
            'taxes'=>array('tax_classes', 'Tax Classes', 'manage_shop_settings'),
            'shipping'=>array('shipping', 'Shipping Options', 'manage_shop_settings'),
            'payment'=>array('payment', 'Payment Methods', 'manage_shop_settings'),
            'catalog_rules'=>array('catalog_rules', 'Catalog Price Rules', 'manage_discounts'),
            'cart_rules'=>array('cart_rules', 'Discounts', 'manage_discounts'),
            'export'=>array('export', 'Export Products', 'manage_products'),
        );

        $first_tab = null;
        foreach ($tabs as $tab_id => $tab_info) {
            if (($tabs[$tab_id][3] = $user->get_permission('shop', $tab_info[2])) && !$first_tab) {
                $first_tab = $tab_info[0];
            }
        }

        if ($first_tab) {
            $tab = $tabCollection->tab('shop', 'Shop', $first_tab, 30);
            foreach ($tabs as $tab_id => $tab_info) {
                if ($tab_info[3]) {
                    $tab->addSecondLevel($tab_id, $tab_info[1], $tab_info[0]);
                }
            }
        }
    }
        
    /**
     * Returns notifications to be displayed in the main menu.
     * @return array Returns an array of notifications in the following format:
     * array(
     *    array(
     *      'id'=>'new-tickets',
     *      'closable'=>false,
     *      'text'=>'10 new support tickets',
     *      'icon'=>'resources/images/notification.png',
     *      'link'=>'/support/tickets'
     *    )
     * ).
     * The 'link', 'id' and 'closable' keys are optional, but id should be specified if closable is true.
     * Use the url() function to create values for the 'link' value.
     * The icon should be a PNG image of size 16x16. Icon path should be specified relative to the module
     * root directory.
     */
    public function listMenuNotifications()
    {
        $user = Phpr::$security->getUser();
        if (!$user->get_permission('shop', 'manage_orders_and_customers')) {
            return array();
        }

        return array(
            array(
                'text'=>'Subscribe to orders RSS feed',
                'icon'=>'resources/images/menu_rss_icon.png',
                'link'=>url('shop/orders/rss'),
                'closable'=>true,
                'id'=>'menu-rss-link'
            )
        );
    }

    /**
     * Builds user permissions interface
     * For drop-down and radio fields you should also add methods returning
     * options. For example, of you want to have "Access Level" drop-down:
     * public function get_access_level_options();
     * This method should return array with keys corresponding your option identifiers
     * and values corresponding its titles.
     *
     * @param $host_obj \Core\ConfigurationRecord object to add fields to
     * @return void
     */
    public function buildPermissionsUi($host_obj)
    {
        $host_obj->add_field($this, 'manage_categories', 'Manage categories', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Create, modify or delete categories and manage categories content.', 'above');

        $host_obj->add_field($this, 'manage_products', 'Manage products and groups', 'right')
            ->renderAs(frm_checkbox)
            ->comment('Create, modify or delete products and product groups.', 'above');

        $host_obj->add_field($this, 'manage_shop_settings', 'Manage shop configuration', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Manage tax classes, shipping options and payment methods.', 'above');

        $host_obj->add_field($this, 'access_reports', 'Access reports', 'right')
            ->renderAs(frm_checkbox);

        $host_obj->add_field($this, 'manage_orders_and_customers', 'Manage orders and customers', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Access order list, create and edit orders and customers.', 'above');

        $host_obj->add_field($this, 'customers_export_import', 'Export or import customers', 'right')
            ->renderAs(frm_checkbox)
            ->comment('Export or import the customer list from CSV files.', 'above');

        $host_obj->add_field($this, 'manage_discounts', 'Manage discounts', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Manage catalog-level and cart-level price rules.', 'above');

        $host_obj->add_field($this, 'lock_orders', 'Lock/Unlock Orders', 'right')
            ->renderAs(frm_checkbox)
            ->comment('Allow user to lock/unlock orders. Locking prevents an order from being edited', 'above');

        $host_obj->add_field($this, 'delete_orders', 'Delete Orders', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Allow user to permanently delete an order record', 'above');

        $host_obj->add_field($this, 'manage_countries_and_states', 'Manage countries and states', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Allow user to manage countries and states available to the shopping system', 'above');

        $host_obj->add_field($this, 'manage_shipping_settings', 'Manage shipping settings', 'right')
            ->renderAs(frm_checkbox)
            ->comment('Allow user to manage the shipping configuration', 'above');
    }


    public function listSettingsItems()
    {
        $result = array(
            array(
                'icon'=>'/modules/shop/resources/images/currency_settings.png',
                'title'=>'Currency',
                'url'=>'/shop/settings/currency',
                'description'=>'Configure the store currency. Set currency formatting parameters and ISO code.',
                'sort_id'=>50,
                'section'=>'eCommerce',
                'access_permission'=>'shop:manage_shop_currency'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/countries_settings.png',
                'title'=>'Countries and States',
                'url'=>'/shop/settings/countries',
                'description'=>'Setup the countries and states you cater to.',
                'sort_id'=>70,
                'section'=>'eCommerce',
                'access_permission'=>'shop:manage_countries_and_states'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/statuses_settings.png',
                'title'=>'Order Route',
                'url'=>'/shop/statuses',
                'description'=>'Configure order statuses, transitions and email notification rules.',
                'sort_id'=>100,
                'section'=>'eCommerce'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/roles_settings.png',
                'title'=>'Roles',
                'url'=>'/shop/roles',
                'description'=>'Configure user roles. Specify what roles can create orders and receive notifications.',
                'sort_id'=>90,
                'section'=>'eCommerce'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/shipping_settings.png',
                'title'=>'Shipping Configuration',
                'url'=>'/shop/shipping_settings',
                'description'=>'Specify a shipping origin and default location, weight and dimension units.',
                'sort_id'=>80,
                'section'=>'eCommerce',
                'access_permission'=>'shop:manage_shipping_settings'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/currency_converter_settings.png',
                'title'=>'Currency Converter',
                'url'=>'/shop/currency_converter_settings',
                'description'=>'Select and configure a currency converter used by LSAPP.',
                'sort_id'=>60,
                'section'=>'eCommerce'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/company_info.png',
                'title'=>'Company Information and Settings',
                'url'=>'/shop/company_info',
                'description'=>'Set merchant company name, address and logo. Configure invoices and packing slips.',
                'sort_id'=>110,
                'section'=>'eCommerce'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/shop_configuration.png',
                'title'=>'eCommerce Settings',
                'url'=>'/shop/configuration',
                'description'=>'Define the shopping cart behavior and configure other eCommerce parameters.',
                'sort_id'=>120,
                'section'=>'eCommerce'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/set_order_numbering.png',
                'title'=>'Set Order Numbering',
                'url'=>'/shop/order_numbering',
                'description'=>'Set or update the start order number.',
                'sort_id'=>130,
                'section'=>'eCommerce'
            ),
            array(
                'icon'=>'/modules/shop/resources/images/reviews.png',
                'title'=>'Ratings & Reviews Settings',
                'url'=>'/shop/reviews_config',
                'description'=>'Disallow duplicate reviews from a single visitor and configure other parameters.',
                'sort_id'=>140,
                'section'=>'eCommerce'
            )
        );

        if (Order::automated_billing_supported()) {
            $result[] = array(
                'icon'=>'/modules/shop/resources/images/calendar.png',
                'title'=>'Automated Billing Settings',
                'url'=>'/shop/autobilling_settings',
                'description'=>'Enable and configure the automatic invoice billing feature.',
                'sort_id'=>140,
                'section'=>'eCommerce'
            );
        }

        return $result;
    }


    /*
     * Subscribe Events
     */

    public function subscribeEvents()
    {
        Backend::$events->addEvent('onLogin', $this, 'onBackendLogin');
        Backend::$events->addEvent('onFrontEndLogin', $this, 'onFrontEndLogin');
        Backend::$events->addEvent('onDeleteEmailTemplate', $this, 'onDeleteEmailTemplate');
        
        Backend::$events->addEvent('cms:onDeletePage', $this, 'onDeletePage');
        Backend::$events->addEvent('cms:onRegisterTwigExtension', $this, 'onRegisterTwigExtension');
        Backend::$events->addEvent('core:onAfterEmailSendToCustomer', $this, 'onAfterEmailSendToCustomer');
    }

    public function onDeletePage($page)
    {
        $isInUse = DbHelper::scalar(
            'select count(*) from shop_categories where page_id=:id',
            array('id'=>$page->id)
        );

        if ($isInUse) {
            throw new ApplicationException("Unable to delete page: it is used as a category landing page.");
        }

        $isInUse = DbHelper::scalar(
            'select count(*) from shop_products where page_id=:id and (grouped is null or grouped <> 1)',
            array('id'=>$page->id)
        );

        if ($isInUse) {
            throw new ApplicationException("Unable to delete page: it is used as a product landing page.");
        }

        $isInUse = DbHelper::scalar(
            'select count(*) from shop_payment_methods where receipt_page_id=:id',
            array('id'=>$page->id)
        );

        if ($isInUse) {
            throw new ApplicationException("Unable to delete page: it is used as a payment method thank you page.");
        }

        PaymentMethod::page_deletion_check($page);
    }

    public function onDeleteEmailTemplate($template)
    {
        $shop_templates = array(
            'shop:registration_confirmation',
            'shop:password_reset',
            'shop:new_order_internal',
            'shop:order_status_update_internal'
        );

        if (in_array($template->code, $shop_templates)) {
            throw new ApplicationException("This template is used by the Shop module.");
        }

        $status = OrderStatus::create()->find_by_customer_message_template_id($template->id);
        if ($status) {
            $statusName = h($status->name);
            throw new ApplicationException(
                "This template cannot be deleted because it is used in {$statusName} order status."
            );
        }
    }

    public function onFrontEndLogin()
    {
        Cart::move_cart();
    }

    public function onBackendLogin()
    {
        CurrencyRateRecord::delete_old_records();
    }

    public function onAfterEmailSendToCustomer(
        $customer,
        $subject,
        $message_text,
        $customer_email,
        $customer_name,
        $custom_data,
        $reply_to,
        $template,
        $result
    ) {
        if (property_exists($template, 'log_notification') && $template->log_notification) {
            CustomerNotification::add($customer, $message_text, $subject, $reply_to);
        }
    }

    public function onRegisterTwigExtension($environment)
    {
        $extension = TwigExtension::create();
        $environment->addExtension($extension);

        $functions = $extension->getFunctions();
        foreach ($functions as $function) {
            $environment->addFunction(
                $function,
                new TwigFunction($function, [$extension, $function], array('is_safe' => array('html')))
            );
        }
    }


    /*
     * Subscribe Crontab
     */

    public function subscribeCrontab()
    {
        return array(
            'update_currency_rates' => array( 'method' => 'cronUpdateCurrencyRates', 'interval' => 1440 ), //24 hours
        );
    }

    public function cronUpdateCurrencyRates()
    {
        $converter = CurrencyConverter::create();
        $converter->update_all_rates($cron=true);
        return true;
    }

        
    /*
     * Register Public Access Points
     */
    public function registerAccessPoints()
    {
        return array(
            'ls_shop_apply_catalog_rules'=>'routeApplyCatalogRules',
            'ls_shop_process_catalog_rules_batch'=>'routeProcessCatalogRules',
            'ls_shop_process_catalog_rules_om_batch'=>'routeProcessCatalogRulesOm',
            'ls_shop_auto_billing'=>'routeProcessAutoBilling',
            CustomerPreferences::$access_point => 'routeApplyCustomerPreference'
        );
    }

    public function routeApplyCatalogRules()
    {
        if (CronManager::access_allowed()) {
            $processed_products = CatalogPriceRule::apply_price_rules();
            echo 'Price rules have been successfully applied to '.$processed_products.' product(s).';
        }
    }
        
    public function routeProcessCatalogRules()
    {
        try {
            CatalogPriceRule::process_products_batch(post('ids'));
            echo 'SUCCESS';
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }
        
    public function routeProcessCatalogRulesOm()
    {
        try {
            CatalogPriceRule::process_product_om_batch(post('ids'));
            echo 'SUCCESS';
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }
        
    public function routeProcessAutoBilling()
    {
        if (CronManager::access_allowed()) {
            $result = AutoBilling::create()->process();
            echo AutoBilling::format_result($result);
        }
    }

    public function routeApplyCustomerPreference()
    {
        $result = false;
        $hash = Phpr::$request->getField('h', null);
        $redirect = Phpr::$request->getField('r', null);
        if ($redirect) {
            $redirect = str_replace('|', '/', $redirect);
            $redirect = substr($redirect, 1);
        }
        $value = Phpr::$request->getField('v', null);
        $value = $value ? 1 : null;
        if (strlen($hash) == 32) {
            $result = CustomerPreferences::set_by_hash($hash, $value);
        }

        if ($result) {
            Phpr::$session->flash['success'] = 'Your preference has been successfully updated.';
        } else {
            Phpr::$session->flash['error'] = 'There was a problem processing your request.';
        }
        Phpr::$response->redirect('/'.$redirect);
    }


    /*
     * List Extensions
     */

    public function listShippingTypes()
    {
        if ($this->shippingTypes !== null) {
            return $this->shippingTypes;
        }

        $typesPath = PATH_APP."/modules/shop/shipping_types";
        $iterator = new \DirectoryIterator($typesPath);
        foreach ($iterator as $file) {
            if (!$file->isDir() && $file->getExtension() == 'php') {
                require_once($typesPath.'/'.$file->getFilename());
            }
        }
            
        $modules = ModuleManager::listModules();
        foreach ($modules as $module_id => $module_info) {
            $class_path = PATH_APP."/modules/".$module_id."/shipping_types";
            if (file_exists($class_path)) {
                $iterator = new \DirectoryIterator($class_path);

                foreach ($iterator as $file) {
                    if (!$file->isDir() && $file->getExtension() == 'php') {
                        require_once($class_path.'/'.$file->getFilename());
                    }
                }
            }
        }

        $classes = get_declared_classes();
        $this->shippingTypes = array();
        foreach ($classes as $class) {
            $parentClassName = get_parent_class($class);
            $parentClassId = get_class_id($parentClassName);
            $shippingTypeClassId = get_class_id('Shop\ShippingType');
            if ($parentClassId == $shippingTypeClassId) {
                $this->shippingTypes[] = $class;
            }
        }
            
        return $this->shippingTypes;
    }
        
    public function listCurrencyConverters()
    {
        if ($this->currencyConverters !== null) {
            return $this->currencyConverters;
        }
                
        $typesPath = PATH_APP."/modules/shop/currency_converters";
        $iterator = new \DirectoryIterator($typesPath);
        foreach ($iterator as $file) {
            if (!$file->isDir() && $file->getExtension() == 'php') {
                require_once($typesPath.'/'.$file->getFilename());
            }
        }

        $classes = get_declared_classes();
        $this->currencyConverters = array();
            
        foreach ($classes as $class) {
            $parentClassName = get_parent_class($class);
            $parentClassId = get_class_id($parentClassName);
            $shippingTypeClassId = get_class_id('Shop\CurrencyConverterBase');
            if ($parentClassId == $shippingTypeClassId) {
                $this->currencyConverters[] = $class;
            }
        }
            
        return $this->currencyConverters;
    }

    public function listPaymentTypes()
    {
        if ($this->paymentTypes !== null) {
            return $this->paymentTypes;
        }
            
        $modules = ModuleManager::listModules();
        foreach ($modules as $module_id => $module_info) {
            $class_path = PATH_APP."/modules/".$module_id."/payment_types";
            if (file_exists($class_path)) {
                $iterator = new \DirectoryIterator($class_path);
                foreach ($iterator as $file) {
                    if (!$file->isDir() && $file->getExtension() == 'php') {
                        require_once($class_path.'/'.$file->getFilename());
                    }
                }
            }
        }

        $classes = get_declared_classes();
        $this->paymentTypes = array();
        foreach ($classes as $class) {
            $parentClassName = get_parent_class($class);
            $parentClassId = get_class_id($parentClassName);
            $shippingTypeClassId = get_class_id('Shop\PaymentType');
            if ($parentClassId == $shippingTypeClassId) {
                $this->paymentTypes[] = $class;
            }
        }
            
        return $this->paymentTypes;
    }


    /**
     * Returns a list of HTML Editor configurations used by the module
     * The method must return an array of configuration codes and descriptions:
     * array('blog_post_content'=>'Blog post')
     * @return array
     */
    public function listHtmlEditorConfigs()
    {
        return array(
            'shop_products_categories'=>'Shop product and category descriptions',
            'shop_manufacturers'=>'Product manufacturer descriptions',
            'shop_printable'=>'Invoices, packing slips and other printable documents'
        );
    }

    /**
     * Returns a list of dashboard indicators in format
     * array('indicator_code'=>array('partial'=>'partial_name.htm', 'name'=>'Indicator Name')).
     * Partials must be placed to the module dashboard directory:
     * /modules/cms/dashboard
     */
    public function listDashboardIndicators()
    {
        $user = Phpr::$security->getUser();
        $indicators = array();

        if ($user->get_permission('shop', 'access_reports')) {
            $indicators = array_merge($indicators, array(
                'ordertotals'=>array('partial'=>'ordertotals_indicator.htm', 'name'=>'Order Totals'),
                'paidordertotals'=>array('partial'=>'paidordertotals_indicator.htm', 'name'=>'Paid Order Totals'),
            ));
        }

        return $indicators;
    }
        
    /**
     * Returns a list of dashboard reports in format
     * array('report_code'=>array('partial'=>'partial_name.htm', 'name'=>'Report Name')).
     * Partials must be placed to the module dashboard directory:
     * /modules/cms/dashboard
     */
    public function listDashboardReports()
    {
        $user = Phpr::$security->getUser();
        $reports = array();

        if ($user->get_permission('shop', 'manage_orders_and_customers')) {
            $reports = array_merge($reports, array(
                'recent_orders'=>array('partial'=>'recentorders_report.htm', 'name'=>'Recent Orders'),
            ));
        }

        if ($user->get_permission('shop', 'manage_products')) {
            $reports = array_merge($reports, array(
                'product_groups'=>array('partial'=>'productgroups_report.htm', 'name'=>'Custom Product Groups')
            ));
        }

        return $reports;
    }

    /*
     * Returns a list of module reports in format
     * array('report_id'=>'report_name')
     */
    public function listReports()
    {
        $user = Phpr::$security->getUser();
        if (!$user->get_permission('shop', 'access_reports')) {
            return array();
        }
            
        return array(
            'customers'=>'Customers',
            'orders'=>'Orders',
            'products'=>'Products',
            'stock'=>'Stock',
            'categories'=>'Categories',
            'custom_groups'=>'Groups',
            'product_types'=>'Product Types',
            'manufacturers'=>'Manufacturers',
            'coupons'=>'Coupon Usage',
            'taxes'=>'Taxes'
        );
    }
        
    /**
     * Returns a list of module email variable scopes
     * array('order'=>'Order')
     */
    public function listEmailScopes()
    {
        return array('order'=>'Order variables', 'customer'=>'Customer variables');
    }

    /**
     * Returns a list of email template variables provided by the module.
     * The method must return an array of section names, variable names,
     * descriptions and demo-values:
     * array('Shop variables'=>array(
     *  'order_total'=>array('Outputs order total value', '$99.99')
     * ))
     * @return array
     */
    public function listEmailVariables()
    {
        $demo_items = file_get_contents(PATH_APP.'/modules/shop/mailviews/_demo_items_value.htm');
        $demo_items = str_replace('%PRICE%', format_currency(99.99), $demo_items);

        $pay_page = Cms_Page::create()->find_by_action_reference('shop:pay');
        $pay_page_url = $pay_page ? root_url($pay_page->url, true).'/' : root_url('pay_page_url', true);

        $passwordRestorePage = Cms_Page::create()->find_by_action_reference('shop:password_restore_request');
        $passwordRestoreUrl = root_url('password_restore_page_url', true);
        if ($passwordRestorePage) {
            $passwordRestoreUrl = root_url($passwordRestorePage->url, true);
        }
        $passwordRestoreCode = '19ag812nwqg1239123n23';
        $passwordRestoreUrl.='/'.$passwordRestoreCode;

        $firstName = Phpr::$security->getUser()->firstName;
        $lastName = Phpr::$security->getUser()->lastName;
        $email = Phpr::$security->getUser()->email;

        return [
            'Customer variables'=> [
                'customer_reference'=> [
                    'Outputs customer reference',
                    '100'
                ],
                'customer_name'=> [
                    'Outputs a full customer name',
                    $firstName.' '.$lastName
                ],
                'customer_first_name'=> [
                    'Outputs a first customer name',
                    $firstName
                ],
                'customer_last_name'=> [
                    'Outputs a last customer name',
                    $lastName
                ],
                'customer_email'=> [
                    'Outputs a customer email address',
                    $email
                ],
                'customer_password'=> [
                    'Outputs a customer password. Can be used only in the registration confirmation template.',
                    '1234567'
                ],
                'customer_password_restore_hash' => [
                    'Outputs the password restore hash, which can be used in a custom password restore link.',
                    $passwordRestoreCode
                ],
                'password_restore_page_link' => [
                    'Outputs a HTML link to the password restore page (page action shop:password_restore_request)',
                    '<a href="'.$passwordRestoreUrl.'">'.$passwordRestoreUrl.'</a>'
                ],
                'password_restore_page_url' => [
                    'Outputs a plain text URL to the password restore page.',
                    $passwordRestoreUrl
                ]
            ],
            'Order variables'=> [
                'order_total'=> [
                    'Outputs order total amount',
                    format_currency(125.96)
                ],
                'order_payment_due' => [
                    'Outputs order payment due amount',
                    format_currency(125.96)
                ],
                'order_subtotal'=> [
                    'Outputs order subtotal amount',
                    format_currency(99.99)
                ],
                'order_shipping_quote'=> [
                    'Outputs order shipping quote',
                    format_currency(15.99)
                ],
                'order_shipping_tax'=> [
                    'Outputs order shipping tax',
                    format_currency(3.99)
                ],
                'order_tax'=> [
                    'Outputs order goods tax',
                    format_currency(5.99)
                ],
                'order_total_tax'=> [
                    'Outputs total order tax (sales tax + shipping tax)',
                    format_currency(9.98)
                ],
                'cart_discount'=> [
                    'Outputs a total discount amount',
                    format_currency(0)
                ],
                'order_content'=> [
                    'Outputs order items table',
                    $demo_items
                ],
                'order_id'=> [
                    'Outputs order ID',
                    '100'
                ],
                'order_reference'=> [
                    'Outputs order reference',
                    '100'
                ],
                'order_date'=> [
                    'Outputs order date',
                    PhprDateTime::now()->format('%x')
                ],
                'order_coupon'=> [
                    'Outputs a coupon code',
                    'SALES_2010'
                ],
                'order_status_comment'=> [
                    'Displays the order status comment specified in the Change Order Status form',
                    'The package has been moved to the delivery department'
                ],
                'order_previous_status'=> [
                    'Displays a previous order status name',
                    'New'
                ],
                'order_status_name'=> [
                    'Displays a current order status name',
                    'Paid'
                ],
                'customer_notes'=> [
                    'Outputs notes provided by the customer',
                    'Please deliver this order by this Friday!'
                ],
                'payment_page_link'=> [
                    'Outputs a HTML link for the Pay page',
                    '<a href="'.$pay_page_url.'">'.$pay_page_url.'</a>'
                ],
                'payment_page_url'=> [
                    'Outputs the URL for the Pay page',
                    $pay_page_url
                ],
                'tax_incl_label'=> [
                    'Outputs the "tax included" label, in accordance with the label configuration.',
                    '(inlc. GST))'
                ],
                'net_amount'=> [
                    'Outputs order net amount (total - tax).',
                    format_currency(115.97)
                ],
                'billing_customer_name'=> [
                    'Outputs a customer billing name.',
                    'John Smith'
                ],
                'billing_country'=> [
                    'Outputs a customer billing country name.',
                    'Canada'
                ],
                'billing_state'=> [
                    'Outputs a customer billing state name.',
                    'British Columbia'
                ],
                'billing_street_addr'=> [
                    'Outputs a customer billing street address.',
                    '8260 Wharton Pl.'
                ],
                'billing_city'=> [
                    'Outputs a customer billing city.',
                    'Mission'
                ],
                'billing_zip'=> [
                    'Outputs a customer billing ZIP/Postal code.',
                    'V2V 7A4'
                ],
                'shipping_customer_name'=> [
                    'Outputs a customer shipping name.',
                    'John Smith'
                ],
                'shipping_country'=> [
                    'Outputs a customer shipping country name.',
                    'Canada'
                ],
                'shipping_state'=> [
                    'Outputs a customer shipping state name.',
                    'British Columbia'
                ],
                'shipping_street_addr'=> [
                    'Outputs a customer shipping street address.',
                    '8260 Wharton Pl.'
                ],
                'shipping_city'=> [
                    'Outputs a customer shipping city.',
                    'Mission'
                ],
                'shipping_zip'=> [
                    'Outputs a customer shipping ZIP/Postal code.',
                    'V2V 7A4'
                ],
                'shipping_codes'=> [
                    'Outputs a list of order shipping tracking codes.',
                    '<ul><li>USPS: CJ1111111111US</li></ul>'
                ],
                'order_shipping_method'=> [
                    'Outputs the orders selected shipping method name.',
                    'USPS'
                ],
                'order_shipping_sub_option'=> [
                    'Outputs the orders selected shipping sub option name.',
                    'Priority Mail'
                ],
                'order_shipping_option'=> [
                    'Outputs the orders selected shipping sub option or falls back to shipping method name.',
                    'Priority Mail'
                ],
            ],
            'Product review'=> [
                'review_author_name'=> [
                    'Outputs a name of the product review author',
                    'John Smith'
                ],
                'review_author_email'=> [
                    'Outputs an email address of the product review author',
                    'john@examile.com'
                ],
                'review_product_name'=> [
                    'Outputs a name of the product the review is written for',
                    'LSAPP'
                ],
                'review_text'=> [
                    'Outputs a text of the review',
                    'Some text'
                ],
                'review_title'=> [
                    'Outputs the review title text',
                    'Some title'
                ],
                'review_rating'=> [
                    'Outputs a review rating',
                    '5'
                ],
                'review_edit_url'=> [
                    'Outputs a URL of the Edit Review page in the Aministration Area',
                    'https://example.com/backend'
                ]
            ],
            'Order note'=> [
                'order_note_author'=> [
                    'Outputs the order note author name',
                    'John Smith'
                ],
                'order_note_id'=> [
                    'Outputs the order number',
                    100
                ],
                'order_note_text'=> [
                    'Outputs the order note text',
                    'Please send this order to the Pending status!'
                ],
                'order_note_preview_url'=> [
                    'Outputs a URL of the Order Preview page in the Administration Area',
                    'https://example.com/backend'
                ]
            ],
            'Out of stock notification'=> [
                'out_of_stock_product'=> [
                    'Outputs the out of stock product name',
                    'Laptop case'
                ],
                'out_of_stock_sku'=> [
                    'Outputs the out of stock product SKU',
                    '1231'
                ],
                'out_of_stock_count'=> [
                    'Outputs the number of units in stock',
                    '100'
                ],
                'out_of_stock_url'=> [
                    'Outputs a URL of the Edit Product page in the Administration Area',
                    'https://example.com/backend'
                ]
            ],
            'Low stock notification'=> [
                'low_stock_product'=> [
                    'Outputs the low stock product name',
                    'Laptop case'
                ],
                'low_stock_sku'=> [
                    'Outputs the low stock product SKU',
                    '1231'
                ],
                'low_stock_count'=> [
                    'Outputs the number of units still in stock',
                    '100'
                ],
                'low_stock_url'=> ['Outputs a URL of the Edit Product page in the Administration Area',
                    'https://example.com/backend'
                ]
            ],
            'Automated billing'=> [
                'autobilling_report'=> [
                    'Outputs the automated billing report details',
                    'Invoices processed: 0'
                ]
            ]
        ];
    }


    /*
     * Catalog cache version management
     */
        
    public static function get_catalog_version()
    {
        return ModuleParameters::get('shop', 'catalog_version', 0);
    }
        
    public static function update_catalog_version()
    {
        if (self::$catalogVersionUpdate) {
            return;
        }

        ModuleParameters::set('shop', 'catalog_version', time());
    }
        
    public static function begin_catalog_version_update()
    {
        self::$catalogVersionUpdate = true;
    }

    public static function end_catalog_version_update()
    {
        self::$catalogVersionUpdate = false;
        self::update_catalog_version();
    }
}
