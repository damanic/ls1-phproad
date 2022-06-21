<?php

namespace Shop;

use Phpr;
use Backend;
use Backend\Controller;
use Phpr\ApplicationException;
use Phpr\User_Parameters as UserParameters;
use System\EmailTemplate;
use Users\User;
use Db\Helper as DbHelper;
use Phpr\DateTime as PhprDateTime;
use Core\Number;

class Orders extends Controller
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior, Db_FilterBehavior, Cms_PageSelector';
    public $list_model_class = 'Shop\Order';
    public $list_record_url = null;
    public $list_record_onclick = null;
    public $list_options = array();

    public $form_preview_title = 'Order Preview';
    public $form_create_title = 'New Order';
    public $form_edit_title = 'Edit Order';
    public $form_model_class = 'Shop\Order';
    public $form_not_found_message = 'Order not found';
    public $form_redirect = null;
    public $form_edit_save_redirect = null;
    public $form_create_save_redirect = null;
    public $enable_concurrency_locking = true;

    public $form_edit_save_flash = 'Order has been successfully saved';
    public $form_create_save_flash = 'Order has been successfully added';
    public $form_no_flash = false;

    public $list_search_enabled = true;
    public $list_search_fields = [
        '@id',
        'concat(@billing_last_name, " ", @billing_first_name)',
        'concat(@shipping_last_name, " ", @shipping_first_name)',
        '@billing_email',
        '@billing_company',
        '@shipping_company',
        '@shipping_street_addr',
        '@billing_street_addr',
        '@billing_city',
        '@shipping_city',
        'billing_country_calculated_join.name',
        'shipping_country_calculated_join.name',
        'billing_state_calculated_join.name',
        'shipping_state_calculated_join.name'
    ];
    public $list_search_prompt = 'find orders by #, name, company or email';

    public $list_custom_head_cells = null;
    public $list_custom_body_cells = null;
    public $list_custom_prepare_func = null;
    public $list_no_setup_link = false;
    public $list_items_per_page = 20;
    public $list_name = null;
    public $list_top_partial = null;
    public $list_sidebar_panel = null;
    public $list_cell_individual_partial = ['has_notes' => 'has_notes'];

    public $list_render_filters = false;
    public $filter_list_title = 'Filter orders';

    public $csv_import_file_columns_header = 'File Columns';
    public $csv_import_db_columns_header = 'LSAPP Order Columns';
    public $csv_import_data_model_class = 'Shop\Order';
    public $csv_import_config_model_class = 'Shop\OrderCsvImportModel';
    public $csv_import_name = 'Order import';
    public $csv_import_url = null;
    public $csv_import_short_name = 'Orders';
    public $include_preview_breadcrumb = false;

    protected $refererUrl = null;
    protected $refererName = null;
    protected $refererObj = false;

    public $filter_filters = [
        'status' => [
            'name' => 'Current Order Status',
            'class_name' => 'Shop\OrderStatusFilter',
            'prompt' =>
                'Please choose order statuses you want to include to the list. 
                Orders with other statuses will be hidden.',
            'added_list_title' => 'Added Statuses'
        ],
        'products' => [
            'name' => 'Product',
            'class_name' => 'Shop\ProductFilter',
            'prompt' =>
                'Please choose products you want to include to the list. 
                Orders which do not contain selected products will be hidden.',
            'added_list_title' => 'Added Products'
        ],
        'categories' => [
            'name' => 'Category',
            'class_name' => 'Shop\CategoryFilter',
            'prompt' =>
                'Please choose product categories you want to include to the list. 
                Orders which do not contain products from selected categories will be excluded.',
            'added_list_title' => 'Added Categories'
        ],
        'groups' => [
            'name' => 'Product group',
            'class_name' => 'Shop\CustomGroupFilter',
            'cancel_if_all' => false,
            'prompt' =>
                'Please choose product groups you want to include to the list. 
                Orders which do not contain products from selected categories will be excluded.',
            'added_list_title' => 'Added Groups'
        ],
        'product_types' => [
            'name' => 'Product type',
            'class_name' => 'Shop\ProductTypeFilter',
            'prompt' =>
                'Please choose product types you want to include to the list. 
                Orders which do not contain products of selected types will be excluded.',
            'added_list_title' => 'Added Types'
        ],
        'order_deleted_status' => [
            'name' => 'Deleted status',
            'class_name' => 'Shop\DeletedFilter',
            'prompt' => 'Please choose whether you want to see only deleted or only active orders.',
            'added_list_title' => 'Added Statuses'
        ],
        'customer_group' => [
            'name' => 'Customer Group',
            'class_name' => 'Shop\CustomerGroupFilter',
            'prompt' =>
                'Please choose customer groups you want to include to the list. 
                Customers belonging to other groups will be hidden.',
            'added_list_title' => 'Added Customer Groups'
        ],
        'coupons' => [
            'name' => 'Coupon',
            'class_name' => 'Shop\CouponFilter',
            'prompt' =>
                'Please choose coupons you want to include to the list. 
                Orders with other coupons will be hidden.',
            'added_list_title' => 'Added Coupons',
            'cancel_if_all' => false
        ],
        'billing_country' => [
            'name' => 'Billing country',
            'class_name' => 'Shop\OrderBillingCountryFilter',
            'prompt' =>
                'Please choose countries you want to include to the list. 
                Orders with other billing countries will be hidden.',
            'added_list_title' => 'Added Countries'
        ],
        'shipping_country' => [
            'name' => 'Shipping country',
            'class_name' => 'Shop\OrderShippingCountryFilter',
            'prompt' =>
                'Please choose countries you want to include to the list. 
                Orders with other shipping countries will be hidden.',
            'added_list_title' => 'Added Countries'
        ]
    ];

    public $filter_switchers = array();

    public $filter_onApply = 'listReload();';
    public $filter_onRemove = 'listReload();';
    protected $processed_customer_ids = array();

    public $globalHandlers = [
        'onCopyBillingAddress',
        'onBillingCountryChange',
        'onShippingCountryChange',
        'onLoadItemForm',
        'onLoadFindProductForm',
        'onLoadFindBundleProductForm',
        'onAddProduct',
        'onDeleteItem',
        'onUpdateProductId',
        'onAddItem',
        'onUpdateItem',
        'onUpdateItemList',
        'onUpdateShippingOptions',
        'onUpdateBillingOptions',
        'onUpdateTotals',
        'onUpdateItemPriceAndDiscount',
        'onCalculateDiscounts',
        'onLoadDiscountForm',
        'onApplyDiscount',
        'onCustomEvent',
        'onUpdateBundleProductList',
        'onUnlockOrder',
        'onLockOrder',
        'onToggleOrderDoc',
        'onCopyOrder'
    ];

    protected $required_permissions = array('shop:manage_orders_and_customers');

    public function __construct()
    {
        $this->addPublicAction('rss');
        $this->addJavascript('/modules/shop/resources/javascript/print_this.js');

        if (in_array(Phpr::$router->action, ['import_csv', 'import_csv_get_config']) || post('import_csv_flag')) {
            $this->implement .= ', Backend_CsvImport';
        }

        Backend::$events->fireEvent('shop:onConfigureOrdersPage', $this);

        parent::__construct();
        $this->app_tab = 'shop';
        $this->app_page = 'orders';
        $this->app_module_name = 'Shop';

        $this->list_record_url = url('/shop/orders/preview/');
        $this->form_redirect = url('/shop/orders');
        $this->form_edit_save_redirect = url('/shop/orders/preview/%s?' . uniqid());
        $this->form_create_save_redirect = url('/shop/orders/preview/%s?' . uniqid());

        $invoice_mode = Phpr::$router->param('param1') == 'invoice';
        $parent_order_id = Phpr::$router->param('param2');

        if ($invoice_mode) {
            $this->form_redirect = url('/shop/orders/preview/' . $parent_order_id);
        }

        if (post('find_product_mode')) {
            $this->list_model_class = 'Shop\Product';
            $this->list_columns = array('name', 'sku', 'total_in_stock', 'price');

            $this->list_custom_prepare_func = 'prepare_product_list';
            $this->list_record_url = null;

            $edit_session_key = $this->formGetEditSessionKey();
            $classId = get_class_id('Shop\Order');
            $this->list_record_onclick = "
					new PopupForm('onAddProduct', 
						{
							ajaxFields: {
							    'product_id': '%s', 
							    'edit_session_key': '$edit_session_key', 
							    'customer_id': $('" . $classId . "_customer_id') ? $('" . $classId . "_customer_id').value : -1}
						});
				
					return false;
				";
            $this->list_search_enabled = true;
            $this->list_no_setup_link = true;

            $this->list_search_fields = [
                'shop_products.name',
                'shop_products.sku',
                '(select group_concat(sku) 
                    from shop_products sku_list 
                    where sku_list.product_id is not null 
                    and sku_list.product_id=shop_products.id)',
                '(select group_concat(sku) from shop_option_matrix_records where product_id=shop_products.id)'
            ];
            $this->list_search_prompt = 'find products by name or SKU';
            $this->list_items_per_page = 10;

            $this->list_custom_head_cells = false;
            $this->list_custom_body_cells = false;
            $this->list_top_partial = null;
        } else {
            $this->list_top_partial = 'order_selectors';
        }

        if (post('filter_request')) {
            $this->list_top_partial = null;
        }

        if (Order::invoice_system_supported()) {
            $this->filter_switchers = array(
                'display_invoices' => array(
                    'name' => 'Display invoices',
                    'class_name' => 'Shop\DisplayInvoicesSwitcher'
                )
            );
        }

        if (Phpr::$router->action == 'create' && Phpr::$router->param('param1') == 'for-customer') {
            $this->form_create_save_redirect = url('/shop/customers/preview/' . Phpr::$router->param('param2'));
        }

        Backend::$events->fireEvent('shop:onDisplayOrdersPage', $this);

        $this->addRss(url('/shop/orders/rss'));
    }

    public function index()
    {
        $this->app_page_title = 'Orders';
    }

    public function listGetRowClass($model)
    {
        $classes = '';
        $classes .= $model->deleted_at ? 'deleted' : 'order_active';
        $classes .= ' status_' . $model->status_id;
        $classes .= $model->parent_order_id ? ' invoice' : null;
        return $classes;
    }

    public function listPrepareData()
    {
        $obj = Order::create();

        $status_id = $this->getCurrentOrderStatus();
        if ($status_id) {
            $obj->where('status_id=?', $status_id);
            if (isset($this->filter_filters['status'])) {
                unset($this->filter_filters['status']);
            }
        }

        $this->filterApplyToModel($obj);

        return $obj;
    }

    protected function getCurrentOrderStatus()
    {
        $status_id = UserParameters::get('orderlist_status');
        if (!strlen($status_id)) {
            return null;
        }

        $status = OrderStatus::create()->find($status_id);
        if (!$status) {
            return null;
        }

        return $status_id;
    }

    protected function index_onSelectOrderStatus()
    {
        UserParameters::set('orderlist_status', post('sidebar_order_status_id'));
        $this->listResetPage();
        $this->renderPartial('orders_page_content');
    }

    protected function index_onHideStatusSelector()
    {
        UserParameters::set('orderlist_status', null);
        UserParameters::set('orderlist_stsl_visible', false);
        $this->listResetPage();
        $this->renderPartial('orders_page_content');
    }

    protected function index_onShowStatusSelector()
    {
        UserParameters::set('orderlist_stsl_visible', true);
        $this->listResetPage();
        $this->renderPartial('orders_page_content');
    }

    protected function orderStatusSelectorVisible()
    {
        return UserParameters::get('orderlist_stsl_visible', null, true);
    }

    protected function evalOrderNum()
    {
        return Order::create()->requestRowCount();
    }

    protected function index_onRefresh()
    {
        $this->renderPartial('orders_page_content');
    }

    protected function index_onResetFilters()
    {
        $this->filterReset();
        $this->listCancelSearch();
        Phpr::$response->redirect(url('shop/orders'));
    }

    protected function index_onLoadDeleteOrdersForm()
    {
        try {
            $order_ids = post('list_ids', array());

            if (!count($order_ids)) {
                throw new ApplicationException('Please select order(s) to delete.');
            }

            $this->viewData['order_count'] = count($order_ids);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('delete_orders_form');
    }

    protected function index_onDeleteSelected()
    {
        $orders_processed = 0;
        $order_ids = post('list_ids', array());
        $this->viewData['list_checked_records'] = $order_ids;

        foreach ($order_ids as $order_id) {
            $order_id = trim($order_id);
            if (!strlen($order_id)) {
                continue;
            }

            $order = null;
            try {
                $order = Order::create()->find($order_id);
                if (!$order) {
                    throw new ApplicationException('Order with identifier ' . $order_id . ' not found.');
                }

                $order->delete_order();

                $orders_processed++;
            } catch (\Exception $ex) {
                if (!$order) {
                    Phpr::$session->flash['error'] = $ex->getMessage();
                } else {
                    Phpr::$session->flash['error'] = 'Error deleting order "' . $order->id . '": ' . $ex->getMessage();
                }

                break;
            }
        }

        if ($orders_processed) {
            if ($orders_processed > 1) {
                $msg = $orders_processed . ' orders have been successfully marked as deleted.';
                Phpr::$session->flash['success'] = $msg;
            } else {
                Phpr::$session->flash['success'] = '1 order has been successfully marked as deleted.';
            }
        }

        $this->renderPartial('orders_page_content');
    }

    protected function index_onDeleteSelectedPermanently()
    {
        $orders_processed = 0;
        $order_ids = post('list_ids', array());
        $this->viewData['list_checked_records'] = $order_ids;

        foreach ($order_ids as $order_id) {
            $order_id = trim($order_id);
            if (!strlen($order_id)) {
                continue;
            }

            $order = null;
            try {
                $order = Order::create()->find($order_id);
                if ($order) {
                    $order->delete();
                }

                $orders_processed++;
            } catch (\Exception $ex) {
                if (!$order) {
                    Phpr::$session->flash['error'] = $ex->getMessage();
                } else {
                    Phpr::$session->flash['error'] = 'Error deleting order "' . $order->id . '": ' . $ex->getMessage();
                }

                break;
            }
        }

        if ($orders_processed) {
            if ($orders_processed > 1) {
                Phpr::$session->flash['success'] = $orders_processed . ' orders have been successfully deleted.';
            } else {
                Phpr::$session->flash['success'] = '1 order has been successfully deleted.';
            }
        }

        $this->renderPartial('orders_page_content');
    }

    protected function index_onRestoreSelected()
    {
        $orders_processed = 0;
        $order_ids = post('list_ids', array());
        $this->viewData['list_checked_records'] = $order_ids;

        foreach ($order_ids as $order_id) {
            $order = null;
            try {
                $order = Order::create()->find($order_id);
                if (!$order) {
                    throw new ApplicationException('Order with identifier ' . $order_id . ' not found.');
                }

                $order->restore_order();

                $orders_processed++;
            } catch (\Exception $ex) {
                if (!$order) {
                    Phpr::$session->flash['error'] = $ex->getMessage();
                } else {
                    Phpr::$session->flash['error'] = 'Error restoring order "' . $order->id . '": ' . $ex->getMessage();
                }

                break;
            }
        }

        if ($orders_processed) {
            if ($orders_processed > 1) {
                Phpr::$session->flash['success'] = $orders_processed . ' orders have been successfully restored.';
            } else {
                Phpr::$session->flash['success'] = '1 order has been successfully restored.';
            }
        }

        $this->renderPartial('orders_page_content');
    }

    protected function index_onLoadChangeStatusForm()
    {
        $order_ids = post('list_ids', array());
        $this->viewData['orders'] = array();
        $orders = array();

        try {
            foreach ($order_ids as $order_id) {
                $order = Order::create()->find($order_id);
                if (!$order) {
                    throw new ApplicationException('Order with identifier ' . $order_id . ' not found.');
                }

                $orders[] = $order;
            }

            if (!count($orders)) {
                throw new ApplicationException('No orders found.');
            }

            $from_state_ids = array();
            foreach ($orders as $order) {
                $from_state_ids[$order->status_id] = 1;
            }

            $from_state_ids = array_keys($from_state_ids);
            $end_transitions = StatusTransition::listAvailableTransitionsMulti(
                $this->currentUser->shop_role_id,
                $from_state_ids
            );

            $log_record = OrderStatusLog::create();
            $log_record->init_columns_info();
            $log_record->define_form_fields('multiorder');
            $log_record->role_id = $this->currentUser->shop_role_id;
            $log_record->status_ids = $from_state_ids;

            $this->viewData['log_record'] = $log_record;
            $this->viewData['end_transitions'] = $end_transitions;

            $log_record->set_default_email_notify_checkbox();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->viewData['orders'] = $orders;
        $this->viewData['order_ids'] = $order_ids;
        $this->renderPartial('change_status_form');
    }

    protected function index_onSetOrderStatuses()
    {
        $orders_processed = 0;

        try {
            $data = post(get_class_id('Shop\OrderStatusLog'), array());
            if (!strlen($data['status_id'])) {
                throw new ApplicationException('Please select order status.');
            }

            $order_ids = post('order_ids');
            if (!strlen($order_ids)) {
                throw new ApplicationException('Orders not found.');
            }

            $order_ids = explode(',', $order_ids);
            $this->viewData['list_checked_records'] = $order_ids;

            @set_time_limit(600);

            foreach ($order_ids as $order_id) {
                $order_id = trim($order_id);
                try {
                    $order = Order::create()->find($order_id);
                    if (!$order) {
                        throw new ApplicationException('not found');
                    }

                    if ($data['status_id'] == $order->status_id) {
                        throw new ApplicationException('new order status should not match current order status.');
                    }

                    if (!StatusTransition::listAvailableTransitions(
                        $this->currentUser->shop_role_id,
                        $order->status_id,
                        $data['status_id']
                    )->count) {
                        throw new ApplicationException('you cannot transfer the order to the selected status.');
                    }

                    OrderStatusLog::create_record(
                        $data['status_id'],
                        $order,
                        $data['comment'],
                        $data['send_notifications'],
                        $data
                    );
                    $orders_processed++;
                } catch (\Exception $ex) {
                    Phpr::$session->flash['error'] = 'Order #' . $order_id . ': ' . $ex->getMessage();
                    break;
                }
            }

            if ($orders_processed) {
                UserParameters::set('orders_email_on_status_change', $data['send_notifications']);
                if ($orders_processed > 1) {
                    Phpr::$session->flash['success'] = $orders_processed . ' orders have been successfully updated.';
                } else {
                    Phpr::$session->flash['success'] = '1 order has been successfully updated.';
                }
            }

            $this->renderPartial('orders_page_content');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function index_onPrintInvoice()
    {
        try {
            $order_ids = post('list_ids', array());

            if (!count($order_ids)) {
                throw new ApplicationException('Please select orders to print invoice for.');
            }

            $order_ids = implode('|', $order_ids);
            Phpr::$response->redirect(url('shop/orders/invoice/' . $order_ids));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function index_onPrintDocs()
    {
        try {
            $order_ids = post('list_ids', array());

            if (!count($order_ids)) {
                throw new ApplicationException('Please select orders to print documents for.');
            }

//              $order_ids = implode('|', $order_ids);
//              Phpr::$response->redirect(url('shop/orders/invoice/'.$order_ids));

            $order_id_string = implode('|', $order_ids);
            $this->orderdoc($order_id_string, null);
            $this->renderPartial('popup_print_orderdoc');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function index_onPrintPackingSlip()
    {
        try {
            $order_ids = post('list_ids', array());
            $page_breaks_rule = post('page_breaks_rule', null);

            if (!count($order_ids)) {
                throw new ApplicationException('Please select orders to print packing slip for.');
            }

            $order_ids = implode('|', $order_ids);
            Phpr::$response->redirect(url('shop/orders/packing_slip/' . $order_ids . '/' . $page_breaks_rule));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function index_onPrintShippingLabel()
    {
        try {
            $order_ids = post('list_ids', array());

            if (!count($order_ids)) {
                throw new ApplicationException('Please select orders to print shipping labels for.');
            }

            $order_ids = implode('|', $order_ids);
            Phpr::$response->redirect(url('shop/orders/shipping_label/' . $order_ids . '/'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }


    public function export_orders($format = null)
    {
        $this->list_name = 'Shop_Orders_index_list';
        $options = array();
        $options['iwork'] = $format == 'iwork';
        $this->listExportCsv('orders.csv', $options, null, true);
    }

    public function export_orders_and_products($format = null)
    {
        $this->list_name = 'Shop_Orders_index_list';
        $options = array();
        $options['iwork'] = $format == 'iwork';
        $this->listExportCsv('orders.csv', $options, null, true, array(
            'headerCallback' => array('Shop\Order', 'export_orders_and_products_header'),
            'rowCallback' => array('Shop\Order', 'export_orders_and_products_row')
        ));
    }

    public function export_customers($format = null)
    {
        $this->list_name = 'Shop_Orders_index_list';
        $this->listExportCsv('customers.csv', array(
            'iwork' => $format == 'iwork',
            'list_sorting_column' => 'billing_email',
            'list_columns' => array(
                'billing_email',
                'billing_first_name',
                'billing_last_name',
                'billing_phone',
                'billing_country',
                'billing_state',
                'billing_street_addr',
                'billing_city',
                'billing_zip'
            )
        ), array($this, 'filter_customer_records'), true);
    }

    public function import_csv()
    {
        $this->app_page_title = 'Import Orders';
    }

    public function filter_customer_records($row)
    {
        if (in_array($row->customer_id, $this->processed_customer_ids)) {
            return false;
        }

        $this->processed_customer_ids[] = $row->customer_id;
        return true;
    }

    /*
         * Preview
     */

    public function preview_formBeforeRender($order)
    {
        $referer = $this->viewData['referer'] = $this->getReferer();
        $this->app_page = $this->getRefererTab();
        $this->form_no_flash = true;
    }

    protected function preview_onLoadItemPreview()
    {
        try {
            $item = OrderItem::create()->find(post('item_id'));
            if (!$item) {
                throw new ApplicationException('Item not found');
            }

            if (!$item->product) {
                throw new ApplicationException('Item product not found');
            }

            $item->define_form_fields('preview');
            $this->viewData['item'] = $item;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('order_item_preview');
    }

    protected function preview_onPrintOrderDoc($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);
            $variant = post('variant');

            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            $orders = array($order);
            $this->orderdocAddViewData($orders, $variant);
            $this->viewData['order_id_string'] = $order_id;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('popup_print_orderdoc');
    }

    protected function preview_onLoadPaymentDetailsPreview()
    {
        try {
            $record = PaymentLogRecord::create()->find(post('record_id'));
            if (!$record) {
                throw new ApplicationException('Record not found');
            }

            $record->define_form_fields();
            $this->viewData['trackTab'] = false;
            $this->viewData['record'] = $record;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('payment_attempt_preview');
    }

    protected function preview_onLoadPaymentTransactionPreview()
    {
        try {
            $record = PaymentTransaction::create()->find(post('record_id'));
            if (!$record) {
                throw new ApplicationException('Transaction not found');
            }

            if (!$record->payment_method) {
                throw new ApplicationException('Payment method not found');
            }

            $record->define_form_fields();
            $this->viewData['trackTab'] = false;
            $this->viewData['record'] = $record;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('payment_transaction_preview');
    }

    protected function preview_onRestoreOrder($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);
            $order->restore_order();

            Phpr::$session->flash['success'] = 'Order has been restored.';
            Phpr::$response->redirect(url('/shop/orders/preview/' . $order->id . '?' . uniqid()));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function preview_onDeleteOrderPermanently($order_id)
    {
        if (!$this->currentUser->get_permission('shop', 'delete_orders')) {
            throw new ApplicationException('You do not have permission to permanently delete orders');
        }
        try {
            $order = $this->getOrderObj($order_id);
            $order->delete();

            Phpr::$session->flash['success'] = 'Order has been successfully deleted.';
            Phpr::$response->redirect(url('/shop/orders/?' . uniqid()));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function preview_onLoadMessageTemplateForm($order_id)
    {
        try {
            $emailTemplates = EmailTemplate::create()
                ->order('code')
                ->where('(is_system is null or is_system=0)')
                ->find_all();
            $this->viewData['templates'] = $emailTemplates;
            $this->viewData['order_id'] = $order_id;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('message_select_template');
    }

    protected function preview_onLoadNoteForm($order_id)
    {
        $this->viewData['note'] = OrderNote::create();
        $this->viewData['note']->define_form_fields();
        $this->viewData['users'] = User::list_users_having_permission('shop', 'manage_orders_and_customers');

        $reply_note = null;

        $reply_note_id = post('reply_note_id');
        if ($reply_note_id) {
            $reply_note = OrderNote::create()->find($reply_note_id);
        }

        $this->viewData['reply_note'] = $reply_note;
        $this->viewData['reply_user_id'] = $reply_note ? $reply_note->created_user_id : null;
        $this->renderPartial('add_note_form');
    }

    protected function preview_onSaveNote($order_id)
    {
        try {
            $note = OrderNote::create();
            $note->init_columns_info();
            $note->define_form_fields();
            $note->order_id = $order_id;
            $note->notification_users = post('notification_users', array());
            $note->save(post(get_class_id('Shop\OrderNote')), $this->formGetEditSessionKey());

            $this->viewData['form_model'] = $this->getOrderObj($order_id);
            $this->renderPartial('order_notes');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function preview_onLoadNotePreview()
    {
        try {
            $note = OrderNote::create()->find(post('note_id'));
            if (!$note) {
                throw new ApplicationException('Note not found');
            }

            $note->init_columns_info();
            $note->define_form_fields('preview');
            $this->viewData['note'] = $note;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('order_note_preview');
    }

    protected function preview_onUpdateInvoiceList($order_id)
    {
        try {
            $this->viewData['form_model'] = $this->getOrderObj($order_id);
            $this->renderPartial('order_invoices');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function preview_onDeleteOrder($order_id)
    {
        $this->edit_onDeleteOrder($order_id, url('/shop/orders/preview/' . $order_id));
    }

    /*
         * Referer support
     */

    protected function getReferer()
    {
        return Phpr::$router->param('param2');
    }

    protected function getRefererName()
    {
        if ($this->refererName != null) {
            return $this->refererName;
        }

        $referer = $this->getReferer();
        $refererObj = $this->getRefererObj($referer);
        if ($refererObj && method_exists($refererObj, 'refererName')) {
            return $this->refererName = $refererObj->refererName();
        }

        return $this->refererName = 'Order List';
    }

    protected function getRefererUrl()
    {
        if ($this->refererUrl != null) {
            return $this->refererUrl;
        }

        $referer = $this->getReferer();
        $refererObj = $this->getRefererObj($referer);
        if ($refererObj && method_exists($refererObj, 'refererUrl')) {
            return $this->refererUrl = $refererObj->refererUrl();
        }

        return $this->refererUrl = url('/shop/orders');
    }

    protected function getRefererTab()
    {
        $referer = $this->getReferer();
        if (strpos($referer, 'report') !== false) {
            return 'reports';
        }

        return $this->app_page;
    }

    protected function getRefererObj($referer)
    {
        if ($this->refererObj !== false) {
            return $this->refererObj;
        }

        $referer = strlen($referer) ? $referer : $this->getReferer();
        if (strpos($referer, 'report') !== false) {
            $className = $referer;
            if (Phpr::$classLoader->load($className)) {
                return $this->refererObj = new $className();
            }
        }

        return $this->refererObj = null;
    }

    /*
         * Invoice
     */

    public function invoice($order_id)
    {
        try {
            if (strpos($order_id, '|') !== false) {
                $order_id = explode('|', $order_id);
                $identifiers = array();
                foreach ($order_id as $id) {
                    if (strlen($id)) {
                        $identifiers[] = $id;
                    }
                }
            } else {
                $order_id = array($order_id);
            }

            if (count($order_id) == 1) {
                $this->app_page_title = 'Invoice #' . $order_id[0];
            } else {
                if (count($order_id) > 5) {
                    $this->app_page_title = 'Invoice - multiple orders';
                } else {
                    $this->app_page_title = 'Invoice #' . implode(', ', $order_id);
                }
            }

            $this->viewData['invoice_template_css'] = array();

            $orders = array();
            foreach ($order_id as $id) {
                try {
                    $order = $this->formFindModelObject($id);
                    $orders[] = $order;
                } catch (\Exception $ex) {
                }
            }

            if (!count($orders)) {
                throw new ApplicationException('No orders found');
            }

            $this->viewData['orders'] = $orders;
            $company_info = $this->viewData['company_info'] = CompanyInformation::get();
            $invoice_info = $this->viewData['invoice_template_info'] = $company_info->get_invoice_template();
            $this->viewData['template_id'] = isset($invoice_info['template_id']) ? $invoice_info['template_id'] : null;
            $this->viewData['invoice_template_css'] = isset($invoice_info['css']) ? $invoice_info['css'] : array();
            $this->viewData['display_due_date'] = strlen($company_info->invoice_due_date_interval);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    /*
         * Returns raw document output without surrounding CMS layout
     */

    public function document($order_id_string, $variant)
    {
        try {
            $this->layoutsPath = PATH_APP . '/modules/shop/layouts';
            $this->layout = 'document';
            $orders = $this->orderdocGetOrders($order_id_string);

            if (!count($orders)) {
                throw new ApplicationException('No orders found');
            }

            if (count($orders) == 1) {
                $this->app_page_title = 'Document ' . $variant . ': Order #' . $orders[0]->id;
            } else {
                $this->app_page_title = 'Document ' . $variant . ': Multiple orders';
            }
            $this->viewData['order_id_string'] = $order_id_string;
            $this->orderdocAddViewData($orders, $variant);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    /*
         * Commercial Document Viewer
     */

    public function orderdoc($order_id_string, $variant)
    {
        try {
            $orders = $this->orderdocGetOrders($order_id_string);

            if (!count($orders)) {
                throw new ApplicationException('No orders found');
            }

            if (count($orders) == 1) {
                $this->app_page_title = 'Docs: Order #' . $orders[0]->id;
            } else {
                $this->app_page_title = 'Docs: Multiple orders';
            }
            $this->viewData['order_id_string'] = $order_id_string;
            $this->orderdocAddViewData($orders, $variant);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    private function orderdocGetOrders($order_id_string)
    {
        $orders = array();
        if (strpos($order_id_string, '|') !== false) {
            $order_ids = explode('|', $order_id_string);
        } else {
            $order_ids = array($order_id_string);
        }

        $orders = array();
        foreach ($order_ids as $id) {
            if (is_numeric($id)) {
                try {
                    $order = $this->formFindModelObject($id);
                    $orders[] = $order;
                } catch (\Exception $ex) {
                }
            }
        }

        return $orders;
    }

    private function orderdocAddViewData($orders, $variant)
    {
        $this->viewData['orders'] = $orders;
        $company_info = $this->viewData['company_info'] = CompanyInformation::get();
        $this->viewData['template_info'] = $this->viewData['company_info']->get_invoice_template();
        $this->viewData['custom_render'] = $this->viewData['template_info']['custom_render'] ?? false;
        $this->viewData['active_variant'] = empty($variant) ? OrderDocsHelper::get_default_variant(
            $orders,
            $this->viewData['template_info']
        ) : $variant;
        $this->viewData['applicable_variants'] = OrderDocsHelper::get_applicable_variants(
            $orders,
            $this->viewData['template_info']
        );
        $this->viewData['display_due_date'] = strlen($company_info->invoice_due_date_interval);
        $this->viewData['auto_print'] = (count($this->viewData['applicable_variants']) < 2) ? true : false;

        $this->viewData['css'] = array();
        $this->viewData['css_files'] = array();
        foreach ($this->viewData['template_info']['css'] as $src => $media) {
            $href = $src;
            if ((strpos($src, '/') === false)) {
                $tid = $this->viewData['template_info']['template_id'];
                $href = '/modules/shop/invoice_templates/' . $tid . '/resources/css/' . $src;
            }
            $this->viewData['css'][] = array(
                'media' => $media,
                'href' => $href
            );
            $this->viewData['css_files'][] = $href;
        }
    }

    protected function onToggleOrderDoc()
    {
        $variant = post('variant');
        $order_id_string = post('order_id_string');
        $this->orderdoc($order_id_string, $variant);
        $this->renderMultiple(array(
            'orderdoc_viewer' => '@_orderdoc_viewer',
        ));
    }

    /*
         * Packing slip
     */

    public function packing_slip($order_id, $page_breaks_rule)
    {
        try {
            if (strpos($order_id, '|') !== false) {
                $order_id = explode('|', $order_id);
                $identifiers = array();
                foreach ($order_id as $id) {
                    if (strlen($id)) {
                        $identifiers[] = $id;
                    }
                }
            } else {
                $order_id = array($order_id);
            }

            if (count($order_id) == 1) {
                $this->app_page_title = 'Packing slip #' . $order_id[0];
            } else {
                if (count($order_id) > 5) {
                    $this->app_page_title = 'Packing slip - multiple orders';
                } else {
                    $this->app_page_title = 'Packing slip #' . implode(', ', $order_id);
                }
            }

            $orders = array();
            foreach ($order_id as $id) {
                try {
                    $order = $this->formFindModelObject($id);
                    $orders[] = $order;
                } catch (\Exception $ex) {
                }
            }

            if (!count($orders)) {
                throw new ApplicationException('No orders found');
            }

            $this->viewData['page_breaks_rule'] = $page_breaks_rule;
            $this->viewData['slip_template_css'] = array();
            $company_info = $this->viewData['company_info'] = CompanyInformation::get();
            $slip_info = $this->viewData['slip_template_info'] = $company_info->get_packing_slip_template();
            $this->viewData['slip_template_css'] = isset($slip_info['css']) ? $slip_info['css'] : array();
            $this->viewData['template_id'] = isset($slip_info['template_id']) ? $slip_info['template_id'] : null;
            $this->viewData['orders'] = $orders;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    /*
         * Shipping Label
     */

    public function shipping_label($order_id)
    {
        try {
            if (strpos($order_id, '|') !== false) {
                $order_id = explode('|', $order_id);
                $identifiers = array();
                foreach ($order_id as $id) {
                    if (strlen($id)) {
                        $identifiers[] = $id;
                    }
                }
            } else {
                $order_id = array($order_id);
            }

            if (count($order_id) == 1) {
                $this->app_page_title = 'Shipping Label #' . $order_id[0];
            } else {
                if (count($order_id) > 5) {
                    $this->app_page_title = 'Shipping label - multiple orders';
                } else {
                    $this->app_page_title = 'Shipping label #' . implode(', ', $order_id);
                }
            }

            $orders = array();
            foreach ($order_id as $id) {
                try {
                    $order = $this->formFindModelObject($id);
                    $orders[] = $order;
                } catch (\Exception $ex) {
                }
            }

            if (!count($orders)) {
                throw new ApplicationException('No orders found');
            }

            $this->viewData['label_template_css'] = array();
            $company_info = $this->viewData['company_info'] = CompanyInformation::get();
            $this->viewData['shipping_params'] = $shipping_params = ShippingParams::get();
            $this->viewData['origin_country'] = Country::create()->find_by_id($shipping_params->country_id);
            $this->viewData['origin_state'] = CountryState::create()->find_by_id($shipping_params->state_id);

            $label_info = $this->viewData['label_template_info'] = $company_info->get_shipping_label_template();
            $this->viewData['label_template_css'] = isset($label_info['css']) ? $label_info['css'] : array();
            $this->viewData['template_id'] = isset($label_info['template_id']) ? $label_info['template_id'] : null;
            $this->viewData['orders'] = $orders;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    /*
         * Change order status
     */

    public function change_status($order_id)
    {
        $this->viewData['order_id'] = $order_id;
        $this->app_page_title = 'Change Order Status';

        try {
            $order = Order::create()->find($order_id);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            $log_record = OrderStatusLog::create();
            $log_record->init_columns_info();
            $log_record->define_form_fields();
            $log_record->role_id = $this->currentUser->shop_role_id;
            $log_record->status_id = $order->status_id;

            $this->viewData['log_record'] = $log_record;
            $log_record->set_default_email_notify_checkbox();

            $this->viewData['order'] = $order;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function change_status_onSave($order_id)
    {
        try {
            $order = Order::create()->find($order_id);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            $data = post(get_class_id('Shop_OrderStatusLog'), array());
            if (!strlen($data['status_id'])) {
                throw new ApplicationException('Please select order status.');
            }

            if ($data['status_id'] == $order->status_id) {
                throw new ApplicationException('New order status should not match current order status.');
            }

            if (!StatusTransition::listAvailableTransitions(
                $this->currentUser->shop_role_id,
                $order->status_id,
                $data['status_id']
            )->count) {
                throw new ApplicationException('You cannot transfer the order to the selected status.');
            }

            OrderStatusLog::create_record(
                $data['status_id'],
                $order,
                $data['comment'],
                $data['send_notifications'],
                $data
            );
            UserParameters::set('orders_email_on_status_change', $data['send_notifications']);

            Phpr::$session->flash['success'] = 'Order status has been successfully changed';
            Phpr::$response->redirect(url('/shop/orders/preview/' . $order_id));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    /*
         * Update order transaction status
     */

    public function update_transaction_status($order_id)
    {
        $this->viewData['order_id'] = $order_id;
        $this->app_page_title = 'Update Transaction Status';

        try {
            $order = Order::create()->find($order_id);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            //fetch latest transaction statuses
            $unique_transactions = PaymentTransaction::get_unique_transactions($order);
            PaymentTransaction::request_transactions_update($order, $unique_transactions);

            $current_transaction = $order->payment_transactions[0];
            if (!$current_transaction) {
                throw new ApplicationException('Current order transaction status not found');
            }

            $order_transitions = StatusTransition::listAvailableTransitions(
                $this->currentUser->shop_role_id,
                $order->status_id
            );

            $this->viewData['current_transaction'] = $current_transaction;
            $this->viewData['unique_transactions'] = $unique_transactions;
            $this->viewData['order'] = $order;
            $this->viewData['order_transitions'] = $order_transitions;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function update_transaction_status_onUpdate($order_id)
    {
        try {
            $order = Order::create()->find($order_id);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            $transaction_id = post('transaction_record_id', false);
            if (!is_numeric($transaction_id)) {
                throw new ApplicationException('Please select a transaction');
            }

            $transaction = PaymentTransaction::create()->find($transaction_id);
            if (!$transaction) {
                throw new ApplicationException('Selected transaction not found');
            }

            $new_transaction_status = post('new_transaction_status', false);
            if (!$transaction) {
                throw new ApplicationException('Please select a transaction status');
            }

            $transaction->update_transaction_status(
                $order,
                $new_transaction_status,
                post('new_order_status'),
                post('user_note')
            );

            Phpr::$session->flash['success'] = 'Transaction status has been successfully changed';
            Phpr::$response->redirect(url('/shop/orders/preview/' . $order_id) . '#tab_5');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function update_transaction_status_onTransactionChange($order_id)
    {
        try {
            $order = Order::create()->find($order_id);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            $transaction_id = post('transaction_record_id', false);
            if (!is_numeric($transaction_id)) {
                throw new ApplicationException('Please select a transaction');
            }

            $transaction = PaymentTransaction::create()->find($transaction_id);
            if (!$transaction) {
                throw new ApplicationException('Selected transaction not found');
            }

            $order_transitions = StatusTransition::listAvailableTransitions(
                $this->currentUser->shop_role_id,
                $order->status_id
            );

            $this->viewData['order_id'] = $order_id;
            $this->viewData['current_transaction'] = $transaction;
            $this->viewData['order'] = $order;
            $this->viewData['order_transitions'] = $order_transitions;
            $this->renderMultiple(array(
                'transaction_update_fields' => '@_form_area_update_transaction_status',
            ));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function preview_onRequestTransactionStatus($order_id)
    {
        try {
            $order = Order::create()->find($order_id);
            if (!$order) {
                throw new ApplicationException('Order not found');
            }

            $has_transactions = $order->payment_transactions[0];
            if (!$has_transactions) {
                throw new ApplicationException('No transactions found');
            }

            PaymentTransaction::request_transactions_update($order);

            $order = Order::create()->find($order_id);
            $this->viewData['form_model'] = $order;
            $this->renderMultiple(array(
                'payment_transaction_list' => '@_payment_transaction_list',
                'order_payment_status' => '@_order_payment_status'
            ));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    /*
         * Create order
     */

    protected function create_onCustomerChanged()
    {
        try {
            $form_model = $this->formCreateModelObject();
            $data = post(get_class_Id('Shop\Order'), array());
            $form_model->set_form_data($data);
            if (strlen($data['customer_id'])) {
                $customer = Customer::create()->find($data['customer_id']);

                if (!$customer) {
                    throw new ApplicationException('Customer not found');
                }

                $customer->copy_to_order($form_model);

                if ($form_model->is_new_record()) {
                    echo ">>tab_3<<";
                    $this->formRenderFormTab($form_model, 2);
                    echo '<div class="clear"></div>';
                    echo ">>tab_4<<";
                    $this->formRenderFormTab($form_model, 3);
                    echo '<div class="clear"></div>';
                } else {
                    echo ">>tab_2<<";
                    $this->formRenderFormTab($form_model, 1);
                    echo '<div class="clear"></div>';
                    echo ">>tab_3<<";
                    $this->formRenderFormTab($form_model, 2);
                    echo '<div class="clear"></div>';
                }
            }

            $this->renderOrderTotals($form_model);
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    public function create_formBeforeRender($model)
    {
        //PERMISSION PROTECTION
        if (!$this->currentUser->role->can_create_orders) {
            throw new ApplicationException('You have no rights to create orders.');
        }

        //DEFAULT COUNTRY SELECTION
        $countries = DbHelper::objectArray(
            'select * from shop_countries where enabled_in_backend=1 order by name limit 0,1'
        );
        if (count($countries)) {
            $firstCountry = $countries[0];
            $model->billing_country_id = $firstCountry->id;
            $model->shipping_country_id = $firstCountry->id;
            $states = CountryState::find_by_country_id($firstCountry->id);
            if ($states->count) {
                $model->billing_state_id = $states[0]->id;
                $model->shipping_state_id = $states[0]->id;
            }
        }

        //SET ORDER CURRENCY
        if ($currency_id = filter_input(INPUT_GET, 'currency_id')) {
            $currencies = new CurrencySettings();
            $currency = $currencies->find($currency_id);
            if ($currency) {
                $model->set_currency($currency->code);
            }
        }

        //ASSIGN CUSTOMER TO ORDER BY URL ENTRY POINT
        if (Phpr::$router->action == 'create' && Phpr::$router->param('param1') == 'for-customer') {
            $model->customer_id = Phpr::$router->param('param2');

            $customer = Customer::create()->find($model->customer_id);
            if ($customer) {
                $customer->copy_to_order($model);
            }
        }

        //DETECT SUB ORDER
        $invoice_mode = Phpr::$router->param('param1') == 'invoice';
        $parent_order_id = Phpr::$router->param('param2');
        if ($invoice_mode && $parent_order_id) {
            $parent_order = Order::create()->find($parent_order_id);
            if (!$parent_order) {
                throw new ApplicationException('Parent order not found.');
            }

            $items = array();
            foreach ($parent_order->items as $item) {
                $new_item = OrderItem::create()->copy_from($item);
                $result = Backend::$events->fireEvent('shop:onNewInvoiceItemCopy', $new_item, $item);

                foreach ($result as $result_value) {
                    if ($result_value === false) {
                        continue 2;
                    }
                }

                $new_item->save();
                $items[] = $new_item;
            }

            $parent_order->create_sub_order($items, false, $this->formGetEditSessionKey(), $model);
        }
    }

    /*
         * Edit order
     */

    public function edit_formBeforeRender($model)
    {
        //setup manual shipping override
        $model->shipping_sub_option_id = $model->shipping_method_id . '_' . md5($model->shipping_sub_option);
        if (!$model->has_shipping_quote_override()) {
            $model->manual_shipping_quote = $model->shipping_quote;
        }
    }

    protected function onCopyBillingAddress($order_id)
    {
        try {
            $form_model = $this->getOrderObj($order_id);
            $form_model->copy_billing_address(post(get_class_id('Shop\Order')));

            if ($form_model->is_new_record()) {
                echo ">>tab_4<<";
                $this->formRenderFormTab($form_model, 3);
            } else {
                echo ">>tab_3<<";
                $this->formRenderFormTab($form_model, 2);
            }
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onBillingCountryChange()
    {
        $form_model = $this->formCreateModelObject();

        $classId = get_class_id('Shop\Order');
        $data = post($classId);
        $form_model->billing_country_id = $data['billing_country_id'];
        echo ">>form_field_container_billing_state_id$classId<<";
        $this->formRenderFieldContainer($form_model, 'billing_state');
    }

    protected function onShippingCountryChange()
    {
        $form_model = $this->formCreateModelObject();
        $classId = get_class_id('Shop\Order');
        $data = post($classId);
        $form_model->shipping_country_id = $data['shipping_country_id'];
        echo ">>form_field_container_shipping_state_id$classId<<";
        $this->formRenderFieldContainer($form_model, 'shipping_state');
    }

    protected function onLoadItemForm()
    {
        try {
            $item = OrderItem::create()->find(post('item_id'));
            if (!$item) {
                throw new ApplicationException('Item not found');
            }

            if (!$item->product) {
                throw new ApplicationException('Item product not found');
            }

            OrderHelper::apply_single_item_discount($item, post('applied_discounts_data'));
            $item->define_form_fields();
            $this->viewData['item'] = $item;
            $this->addBundleViewData($item);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->viewData['edit_session_key'] = post('edit_session_key');
        $this->viewData['edit_mode'] = true;
        $this->renderPartial('edit_order_item');
    }

    protected function onUpdateItemPriceAndDiscount($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);
            $item = OrderItem::create()->find(post('item_id'));
            if ($item && post('auto_discount_price_eval')) {
                if (!post('shop_product_id')) {
                    $product = $item->product;
                } else {
                    $product = Product::create()->find(post('shop_product_id'));
                    if (!$product) {
                        return;
                    }
                }

                $customer = OrderHelper::find_customer($order);
                $customer_group_id = OrderHelper::find_customer_group_id($order);

                $item->quantity = post('quantity');
                $product_options = post('product_options', array());
                $product_options = $product->normalize_posted_options($product_options);

                $item->product = $product;

                $effective_quantity = $item->quantity;
                if ($product->tier_prices_per_customer && $customer) {
                    $effective_quantity += $customer->get_purchased_item_quantity($product);
                }

                $item->auto_discount_price_eval = 1;

                $bundle_offer_item_id = post('bundle_offer_item_id');
                if (!$bundle_offer_item_id) {
                    $om_record = OptionMatrixRecord::find_record($product_options, $product, true);
                    if (!$om_record) {
                        $price = max(
                            $product->price_no_tax(
                                $effective_quantity,
                                $customer_group_id
                            ) - $product->get_discount($effective_quantity, $customer_group_id),
                            0
                        );
                    } else {
                        $price = $om_record->get_sale_price($product, $effective_quantity, $customer_group_id, true);
                    }
                } else {
                    $bundle_offer_item = ProductBundleOfferItem::create()->find($bundle_offer_item_id);
                    if (!$bundle_offer_item) {
                        throw new ApplicationException('Bundle item product not found.');
                    }

                    $price = max($bundle_offer_item->get_price_no_tax(
                            $product,
                            $effective_quantity,
                            $customer_group_id,
                            $product_options
                        ) - $product->get_discount($effective_quantity, $customer_group_id), 0);
                }

                $item->price = $price;

                $this->viewData['item'] = $item;

                $this->renderMultiple(array(
                    'item_price_and_discount' => '@_item_price_and_discount',
                    'item_description' => '@_item_description',
                    'item_in_stock_indicator' => '@_item_in_stock_indicator'
                ));
            }
        } catch (\Exception $ex) {
        }
    }

    public function prepare_product_list()
    {
        return Product::create()
            ->where('shop_products.grouped is null')
            ->where('shop_products.disable_completely is null or shop_products.disable_completely=0');
    }

    protected function onAddProduct($order_id)
    {
        try {
            $order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
            $this->addBundleViewData();

            $product = Product::create()->find(post('product_id'));
            if (!$product) {
                throw new ApplicationException('Product not found');
            }

            $customer = OrderHelper::find_customer($order);
            $customer_group_id = OrderHelper::find_customer_group_id($order);

            $grouped_products = $product->grouped_products;
            if ($grouped_products->count) {
                $product = $grouped_products->first;
            }

            $item_obj = OrderItem::create()->init_empty_item(
                $product,
                $customer_group_id,
                $customer,
                post('bundle_offer_item_id')
            );
            $item_obj->save();

            $this->viewData['item'] = $item_obj;
            $item_obj->define_form_fields();
            $this->viewData['edit_session_key'] = post('edit_session_key');

            $this->viewData['edit_mode'] = false;

            $this->renderPartial('edit_order_item');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onAddItem($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);

            $item = OrderItem::create()->find(post('item_id'));
            if (!$item) {
                throw new ApplicationException('Item not found');
            }

            if (!$item->product) {
                throw new ApplicationException('Item product not found');
            }

            $item->disable_column_cache();
            $item->set_from_post($this->formGetEditSessionKey());

            $items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
            $same_item = $item->find_same_item($items, $this->formGetEditSessionKey());
            if ($same_item) {
                $same_item->quantity += $item->quantity;

                if ($same_item->auto_discount_price_eval) {
                    $customer_group_id = OrderHelper::find_customer_group_id($order);
                    $customer = OrderHelper::find_customer($order);

                    $effective_quantity = $same_item->quantity;
                    if ($item->product->tier_prices_per_customer && $customer) {
                        $effective_quantity += $customer->get_purchased_item_quantity($item->product);
                    }

                    $same_item->price = round($same_item->product->price_no_tax(
                        $effective_quantity,
                        $customer_group_id
                    ), 2);
                    $same_item->discount = round($same_item->product->get_discount(
                        $effective_quantity,
                        $customer_group_id
                    ), 2);
                }

                $same_item->save(null, $this->formGetEditSessionKey());
                $item->delete();
            } else {
                $order->items->add($item, post('edit_session_key'));
            }

            echo ">>data_placeholder<<";
            echo "no_data";
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }


    protected function onUpdateItem($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);

            $item = OrderItem::create()->find(post('item_id'));
            if (!$item) {
                throw new ApplicationException('Item not found');
            }

            if (!$item->product) {
                throw new ApplicationException('Item product not found');
            }

            $item->disable_column_cache();
            $item->set_from_post($this->formGetEditSessionKey());
            $item_id = $item->id;

            $items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());

            $same_item = $item->find_same_item($items, $this->formGetEditSessionKey());

            if ($same_item) {
                $same_item->quantity += $item->quantity;

                if ($same_item->auto_discount_price_eval) {
                    $customer_group_id = OrderHelper::find_customer_group_id($order);
                    $customer = OrderHelper::find_customer($order);

                    $effective_quantity = $same_item->quantity;
                    if ($item->product->tier_prices_per_customer && $customer) {
                        $effective_quantity += $customer->get_purchased_item_quantity($item->product);
                    }

                    $same_item->price = round($same_item->product->price_no_tax(
                        $effective_quantity,
                        $customer_group_id
                    ), 2);
                    $same_item->discount = round($same_item->product->get_discount(
                        $effective_quantity,
                        $customer_group_id
                    ), 2);
                }

                $same_item->save(null, $this->formGetEditSessionKey());
                $item_id = $same_item->id;

                $item->delete();
            }

            // $order->items->add($item, post('edit_session_key'));

            $item->update_bundle_item_quantities($items);

            $this->remove_cart_discount($item_id);
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onUpdateItemList($order_id)
    {
        try {
            $order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
            $order->set_form_data();

            echo ">>item_list<<";
            $this->renderPartial('item_list');

            $this->renderOrderTotals($order);
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onDeleteItem($order_id)
    {
        try {
            $order = $this->viewData['form_model'] = $this->getOrderObj($order_id);

            $item = OrderItem::create()->find(post('item_id'));
            if ($item) {
                $order->items->delete($item, $this->formGetEditSessionKey());

                $items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
                foreach ($items as $sub_item) {
                    if ($sub_item->bundle_master_order_item_id == $item->id) {
                        $order->items->delete($sub_item, $this->formGetEditSessionKey());
                    }
                }
            }

            $order->set_form_data();

            echo ">>item_list<<";
            $this->renderPartial('item_list');

            $this->renderOrderTotals($order);
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onUpdateProductId($order_id)
    {
        try {
            $item = OrderItem::create()->find(post('item_id'));
            if (!$item) {
                throw new ApplicationException('Item not found');
            }

            $item->product = Product::create()->find(post('shop_product_id'));
            if (!$item->product) {
                throw new ApplicationException('Product not found');
            }

            $order = $this->getOrderObj($order_id);

            $customer_group_id = OrderHelper::find_customer_group_id($order);
            $customer = OrderHelper::find_customer($order);
            $product_options = post('product_options', array());
            $product_options = $item->product->normalize_posted_options($product_options);

            $effective_quantity = $item->quantity;
            if ($item->product->tier_prices_per_customer && $customer) {
                $effective_quantity += $customer->get_purchased_item_quantity($item->product);
            }

            $bundle_offer_item_id = post('bundle_offer_item_id');
            if (!$bundle_offer_item_id) {
                $om_record = OptionMatrixRecord::find_record($product_options, $item->product, true);
                if (!$om_record) {
                    $price = max(round(
                            $item->product->price_no_tax($effective_quantity, $customer_group_id),
                            2
                        ) - $item->product->get_discount($effective_quantity, $customer_group_id), 0);
                } else {
                    $price = $om_record->get_sale_price($item->product, $effective_quantity, $customer_group_id, true);
                }
            } else {
                $bundle_offer_item = ProductBundleOfferItem::create()->find($bundle_offer_item_id);
                if (!$bundle_offer_item) {
                    throw new ApplicationException('Bundle item product not found.');
                }

                $price = max(round($bundle_offer_item->get_price_no_tax(
                        $item->product,
                        $effective_quantity,
                        $customer_group_id,
                        $product_options
                    ), 2) - $item->product->get_discount(
                        $effective_quantity,
                        $customer_group_id
                    ), 0);
            }

            $item->price = $price;
            $item->discount = 0;

            $item->define_form_fields();

            $item_data = post(get_class_id('Shop\OrderItem'), array());
            foreach ($item_data as $key => $value) {
                $column = $item->find_column_definition($key);
                if ($column && $column->type == db_date) {
                    $value = trim($value);
                    if (strlen($value)) {
                        $item->$key = PhprDateTime::parse($value, '%x');
                        if (!$item->$key) {
                            throw new ApplicationException(sprintf(
                                'Invalid value in the %s field.',
                                $column->displayName
                            ));
                        }
                    }
                } else {
                    $item->$key = $value;
                }
            }

            $this->viewData['item'] = $item;

            $this->renderPartial('item_form');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onLoadFindProductForm()
    {
        try {
            $this->viewData['edit_session_key'] = post('edit_session_key');
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('find_product_form');
    }

    protected function onUpdateBundleProductList()
    {
        try {
            $offer = ProductBundleOffer::create()->find(post('bundle_offer_id'));
            if (!$offer) {
                throw new ApplicationException('Bundle offer not found.');
            }

            $this->viewData['items'] = $offer->items;
            $this->renderPartial('bundle_item_products');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onLoadFindBundleProductForm()
    {
        try {
            $parent_item = OrderItem::create()->find(post('bundle_parent'));
            if (!$parent_item) {
                throw new ApplicationException('Parent order item not found.');
            }

            if (!$parent_item->product->bundle_offers->count) {
                throw new ApplicationException('Selected product has no bundle offers.');
            }

            $this->viewData['edit_session_key'] = post('edit_session_key');
            $this->viewData['bundle_offers'] = $parent_item->product->bundle_offers;
            $this->viewData['bundle_master_order_item_id'] = post('bundle_parent');
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('find_bundle_product_form');
    }

    protected function onUnlockOrder($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);
            if (!$this->currentUser->get_permission('shop', 'lock_orders')) {
                throw new ApplicationException('You do not have permission to unlock orders');
            }
            $order->unlock_order();
            $order->save();
            OrderLockLog::add_log($order, 'Unlocked by user');
            Phpr::$response->redirect(Phpr::$request->getReferer(post('url')));
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function onLockOrder($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);
            if (!$this->currentUser->get_permission('shop', 'lock_orders')) {
                throw new ApplicationException('You do not have permission to lock orders');
            }
            $order->lock_order();
            $order->save();
            OrderLockLog::add_log($order, 'Locked by user');
            Phpr::$response->redirect(Phpr::$request->getReferer(post('url')));
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function onCopyOrder($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);
            $session_key = $this->formGetEditSessionKey();
            $order_copy = $order->create_order_copy();
            Phpr::$response->redirect(url('/shop/orders/edit/' . $order_copy->id));
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }


    public function formBeforeCreateSave($model, $session_key)
    {
        //Audit Log
        $audit_enabled = Phpr::$config->get('ENABLE_ORDER_AUDIT_LOGS', false);
        if ($audit_enabled) {
            $model->modelLogDisable(); //do not add audit logs on create
        }
    }

    public function formBeforeSave($order, $session_key)
    {
        $classId = get_class_id('Shop\Order');
        $orderData = post($classId);
        $deferred_session_key = $this->formGetEditSessionKey();

        $order->set_shipping_address($orderData);
        $order->coupon_id = array_key_exists('coupon_id', $orderData) ? $orderData['coupon_id'] : null;
        $order->shipping_method_id = array_key_exists(
            'shipping_method_id',
            $orderData
        ) ? $orderData['shipping_method_id'] : null;
        $order->payment_method_id = array_key_exists(
            'payment_method_id',
            $orderData
        ) ? $orderData['payment_method_id'] : null;
        $order->billing_country_id = array_key_exists(
            'billing_country_id',
            $orderData
        ) ? $orderData['billing_country_id'] : null;
        $order->shipping_quote = array_key_exists(
            'shipping_quote',
            $orderData
        ) ? $orderData['shipping_quote'] : $order->shipping_quote;
        $order->override_shipping_quote = array_key_exists(
            'override_shipping_quote',
            $orderData
        ) ? $orderData['override_shipping_quote'] : null;


        /*
             * Validate shipping parameters
         */

        $shipping_method_id = $order->shipping_method_id;
        if (strpos($shipping_method_id, '_') !== false) {
            $parts = explode('_', $order->shipping_method_id);
            $order->shipping_sub_option_id = $shipping_method_id;
            $order->shipping_method_id = $parts[0];
            $shipping_sub_option = array_key_exists(
                'shipping_sub_option',
                $orderData
            ) ? $orderData['shipping_sub_option'] : false;
            if ($shipping_sub_option !== false) {
                $order->shipping_sub_option = $shipping_sub_option;
            }
        } else {
            $order->shipping_sub_option = null;
        }

        if ($order->override_shipping_quote) {
            $manual_shipping_quote = trim(post_array_item($classId, 'manual_shipping_quote'));

            if (!Number::isValidFloat($manual_shipping_quote)) {
                throw new ApplicationException(
                    'Please enter a valid shipping quote or disable the "Override shipping quote" option'
                );
            }
        } else {
            if (!$order->shipping_method_id) {
                throw new ApplicationException('Please select shipping method');
            }
        }

        /*
             * Validate payment method
         */
        $payment_methods = $this->getAvailablePaymentMethods($order);

        $payment_method_found = false;
        foreach ($payment_methods as $method) {
            if ($method->id == $order->payment_method_id) {
                $payment_method_found = true;
                break;
            }
        }

        if (!$payment_method_found) {
            throw new ApplicationException('Please select payment method');
        }


        /*
             * Update items tax and discount value
         */
        $items = $this->evalOrderTotals($order, null); //recalculates item tax and applied discount data
        foreach ($items as $item) {
            $item->save();
        }
    }

    public function formAfterSave($model, $session_key)
    {
        //Audit Log
        $audit_enabled = Phpr::$config->get('ENABLE_ORDER_AUDIT_LOGS', false);
        if ($audit_enabled) {
            $model->modelLogOnModelUpdated();
        }

        try {
            $applied_rules = post('order_applied_discount_list');
            if ($applied_rules) {
                $applied_rules = unserialize($applied_rules);
                $model->set_applied_cart_rules($applied_rules);
            }

            if (Phpr::$router->action == 'create' && Phpr::$router->param('param1') == 'for-customer') {
                $this->form_create_save_redirect = url('/shop/customers/preview/' . $model->customer_id);
            }
        } catch (\Exception $ex) {
        }
    }

    protected function onUpdateShippingOptions($order_id)
    {
        $order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
        $order->set_form_data();

        if (strpos($order->shipping_method_id, '_') !== false) {
            $parts = explode('_', $order->shipping_method_id);
            $order->shipping_sub_option_id = $order->shipping_method_id;
            $order->shipping_method_id = $parts[0];
        }

        if ($order->is_new_record()) {
            echo ">>tab_5<<";
            $this->formRenderFormTab($order, 4);
        } else {
            echo ">>tab_4<<";
            $this->formRenderFormTab($order, 3);
        }
    }

    protected function onUpdateBillingOptions($order_id)
    {
        $order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
        $orderData = post(get_class_id('Shop\Order'));
        $order->billing_country_id = $orderData['billing_country_id'];
        $order->payment_method_id = array_key_exists(
            'payment_method_id',
            $orderData
        ) ? $orderData['payment_method_id'] : null;

        if ($order->is_new_record()) {
            echo ">>tab_6<<";
            $this->formRenderFormTab($order, 5);
        } else {
            echo ">>tab_5<<";
            $this->formRenderFormTab($order, 4);
        }
    }

    private function getOrderObj($id)
    {
        $find = (strlen($id) && $id != 'invoice' && $id != 'for-customer');
        return $find ? $this->formFindModelObject($id) : $this->formCreateModelObject();
    }


    private function evalOrderTotals($order, $items = null)
    {
        $options = array(
            'recalculate_shipping' => false
        );
        return OrderHelper::evalOrderTotals(
            $order,
            $items,
            $this->formGetEditSessionKey(),
            post('applied_discounts_data', false),
            $options
        );
    }

    private function renderOrderTotals($order, $items = null)
    {
        echo ">>order_totals<<";
        $this->evalOrderTotals($order, $items);
        $this->viewData['form_model'] = $order;

        $this->renderPartial('order_totals');
    }

    protected function onUpdateTotals($order_id)
    {
        $orderPostData = post(get_class_id('Shop\Order'), array());

        $order = $this->viewData['form_model'] = $this->getOrderObj($order_id);
        $order->set_form_data($orderPostData);

        $updateShippingQuote = isset($orderPostData['shipping_quote']) ? $orderPostData['shipping_quote'] : false;
        if ($updateShippingQuote !== false) {
            $order->shipping_quote = $updateShippingQuote;
        }

        TaxClass::set_tax_exempt($order->tax_exempt);
        TaxClass::set_customer_context(OrderHelper::find_customer($order, true));

        $this->renderOrderTotals($order);
    }

    protected function edit_onDeleteOrder($order_id, $redirect_url = null)
    {
        try {
            $order = $this->getOrderObj($order_id);
            $order->cancelDeferredBindings($this->formGetEditSessionKey());
            $order->delete_order();
            Phpr::$session->flash['success'] = 'Order has been marked as deleted.';

            $redirect_url = $redirect_url ? $redirect_url : url('/shop/orders');

            Phpr::$response->redirect($redirect_url);
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function edit_onDeleteOrderPermanently($order_id)
    {
        if (!$this->currentUser->get_permission('shop', 'delete_orders')) {
            throw new ApplicationException('You do not have permission to permanently delete orders');
        }
        try {
            $order = $this->getOrderObj($order_id);
            $order->cancelDeferredBindings($this->formGetEditSessionKey());
            $order->delete();
            Phpr::$session->flash['success'] = 'Order has been successfully deleted.';

            Phpr::$response->redirect(url('/shop/orders'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onCalculateDiscounts($order_id)
    {
        $cart_items = null;
        $items = null;
        $discount_info = null;

        try {
            $deferred_session_key = $this->formGetEditSessionKey();
            $classId = get_class_id('Shop\Order');
            $orderData = post($classId);

            $order = $this->getOrderObj($order_id);
            $order->set_form_data($orderData);

            $order->validate_data($orderData, $deferred_session_key);

            $results = OrderHelper::recalc_order_discounts($order, $deferred_session_key);
            extract($results);

            echo ">>form_field_container_free_shipping$classId<<";
            $this->formRenderFieldContainer($order, 'free_shipping');

            $this->update_cart_discounts($cart_items);
            $this->renderOrderTotals($order, $items);

            echo ">>order_applied_discount_list<<";
            $this->renderPartial(
                'applied_discounts_list',
                array('order_applied_discount_list' => $discount_info->applied_rules)
            );

            echo ">>item_list<<";
            $this->renderPartial('item_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onLoadDiscountForm($order_id)
    {
        try {
            $orderData = post(get_class_id('Shop\Order'));

            $order = $this->getOrderObj($order_id);
            $order->set_form_data($orderData);
            $order->validate_data($orderData, $this->formGetEditSessionKey());

            $subtotal = 0;
            $items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
            foreach ($items as $item) {
                $subtotal += $item->single_price * $item->quantity;
            }

            $this->viewData['order'] = $order;
            $this->viewData['subtotal'] = $subtotal;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->viewData['edit_session_key'] = post('edit_session_key');
        $this->renderPartial('order_discount_form');
    }

    protected function onApplyDiscount($order_id)
    {
        try {
            /*
                 * Load the order and calculate the subtotal
             */

            $classId = get_class_id('Shop\Order');
            $orderData = post($classId);
            $order = $this->getOrderObj($order_id);
            $order->set_form_data($orderData);
            $order->validate_data($orderData, $this->formGetEditSessionKey());

            $subtotal = 0;
            $items = $order->list_related_records_deferred('items', $this->formGetEditSessionKey());
            foreach ($items as $item) {
                $subtotal += $item->single_price * $item->quantity;
            }

            /*
                 * Validate the specified discount value
             */

            $value = trim(post('discount_value'));
            if (!strlen($value)) {
                throw new ApplicationException('Please enter a discount value');
            }

            if (!preg_match('/^([0-9]+\.[0-9]+%|[0-9]+%?|[0-9]+\.[0-9]+%?)$/', $value)) {
                throw new ApplicationException('Invalid discount value. Please specify a number or percentage value.');
            }

            if ($value < 0) {
                throw new ApplicationException('Discount value cannot be negative.');
            }

            $is_percentage = substr($value, -1) == '%';
            if ($is_percentage) {
                $value = substr($value, 0, -1);
                if ($value > 100) {
                    throw new ApplicationException('The discount value cannot exceed 100%.');
                }

                $value = $subtotal * $value / 100;
            } else {
                if ($value > $subtotal) {
                    $subtotalFormatted = $order->format_currency($subtotal);
                    $msg = 'The discount value cannot exceed the order subtotal (' . $subtotalFormatted . ').';
                    throw new ApplicationException($msg);
                }
            }

            /*
                 * Prepare the cart items and execute the fixed cart discount action
             */

            $cart_items = OrderHelper::items_to_cart_items_array($items);
            foreach ($cart_items as $cart_item) {
                $cart_item->applied_discount = 0;
                $cart_item->ignore_product_discount = true;
            }

            $item_discount_map = array();
            foreach ($cart_items as $cart_item) {
                $item_discount_map[$cart_item->key] = 0;
                $item_discount_tax_incl_map[$cart_item->key] = 0;
            }

            $discount_action = new CartFixed_Action();

            $params = array('cart_items' => $cart_items, 'no_tax_include' => true);
            $action_params = array('discount_amount' => $value);
            $discount = $discount_action->eval_discount(
                $params,
                (object)$action_params,
                $item_discount_map,
                $item_discount_tax_incl_map,
                null
            );

            foreach ($item_discount_map as $key => &$value) {
                $value = max(0, $value);
            }

            /**
             * Apply discounts to cart items
             */

            $total_discount = 0;
            foreach ($cart_items as $cart_item) {
                $cart_item->applied_discount = $item_discount_map[$cart_item->key];
                $cart_item->order_item->discount = $cart_item->total_discount_no_tax();
                $total_discount += $cart_item->total_discount_no_tax() * $cart_item->quantity;
            }

            $order->discount = $total_discount;

            $this->update_cart_discounts($cart_items);
            $this->renderOrderTotals($order, $items);

            $order->coupon = null;
            echo ">>form_field_container_coupon_id$classId<<";
            $this->formRenderFieldContainer($order, 'coupon');

            echo ">>order_applied_discount_list<<";
            $this->renderPartial('applied_discounts_list', array('order_applied_discount_list' => array()));

            echo ">>item_list<<";
            $this->renderPartial('item_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function update_cart_discounts($items)
    {
        $discounts = array();
        foreach ($items as $item) {
            $discounts[$item->order_item->id] = $item->total_discount_no_tax();
        }

        echo ">>order_applied_discounts_data<<";
        $this->renderPartial('applied_discounts_data', array('applied_discount_data' => $discounts));
        $_POST['applied_discounts_data'] = serialize($discounts);
    }

    protected function remove_cart_discount($item_id)
    {
        $data = post('applied_discounts_data');
        if (strlen($data)) {
            try {
                $data = unserialize($data);
                if (array_key_exists($item_id, $data)) {
                    unset($data[$item_id]);
                }

                $_POST['applied_discounts_data'] = serialize($data);

                echo ">>order_applied_discounts_data<<";
                $this->renderPartial('applied_discounts_data', array('applied_discount_data' => $data));
            } catch (\Exception $ex) {
            }
        }

        echo ">>data_placeholder<<";
        echo "no_data";
    }


    protected function apply_item_discounts(&$items)
    {
        OrderHelper::apply_item_discounts($items, post('applied_discounts_data'));
    }


    protected function apply_single_item_discount($item)
    {
        OrderHelper::apply_item_discounts($item, post('applied_discounts_data'));
    }

    /*
         * Payment page
     */

    public function pay($order_id)
    {
        try {
            $this->app_page_title = 'Pay';
            $this->viewData['form_record_id'] = $order_id;
            $this->viewData['order'] = $order = $this->formFindModelObject($order_id);

            $payment_method = $order->payment_method();
            if (!$payment_method) {
                throw new ApplicationException('Payment method not found.');
            }

            $payment_method->order = $order;

            $payment_method->define_form_fields('backend_payment_form');
            $this->viewData['payment_method'] = $payment_method;
            $this->viewData['payment_method_obj'] = $payment_method->get_paymenttype_object();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    public function pay_onSubmit($order_id)
    {
        try {
            $order = $this->formFindModelObject($order_id);
            $payment_method = $order->payment_method();
            if (!$payment_method) {
                throw new ApplicationException('Payment method not found.');
            }

            $form_data = post(get_class_id('Shop\PaymentMethod'), array());
            $payment_method->define_form_fields('backend_payment_form');

            $pay_from_profile = post('pay_from_profile');
            if (!$pay_from_profile) {
                $payment_method->validate_data($form_data);
            }

            $payment_method->define_form_fields();
            $payment_method_obj = $payment_method->get_paymenttype_object();

            if (!$pay_from_profile) {
                $payment_method_obj->process_payment_form($form_data, $payment_method, $order, true);
            } else {
                $payment_method_obj->pay_from_profile($payment_method, $order, true);
            }

            Phpr::$response->redirect(url('/shop/orders/payment_accepted/' . $order->id . '?' . uniqid()));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function pay_onPayFromProfile($order_id)
    {
        try {
            $order = $this->formFindModelObject($order_id);
            $payment_method = $order->payment_method();
            if (!$payment_method) {
                throw new ApplicationException('Payment method not found.');
            }

            $form_data = post(get_class_id('Shop\PaymentMethod'), array());
            $payment_method->define_form_fields('backend_payment_form');

            $payment_method->define_form_fields();
            $payment_method_obj = $payment_method->get_paymenttype_object();

            $payment_method_obj->pay_from_profile($payment_method, $order, true);

            Phpr::$response->redirect(url('/shop/orders/payment_accepted/' . $order->id . '?' . uniqid()));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    public function payment_accepted($order_id)
    {
        try {
            $this->app_page_title = 'Payment Accepted';
            $this->viewData['form_record_id'] = $order_id;
            $this->viewData['order'] = $order = $this->formFindModelObject($order_id);
//              Phpr::$response->redirect(url('/shop/orders/preview/'.$order->id.'?'.uniqid()));
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    public function rss()
    {
        $this->suppressView();

        $user = Phpr::$security->http_authentication(
            'LSAPP Orders Rss',
            'You must enter a valid login name and password to access the RSS channel.'
        );

        Backend::$events->fireEvent('shop:onBeforeOrdersRssExport');

        if (!$user->get_permission('shop', 'manage_orders_and_customers')) {
            echo "You have no rights to access the orders RSS channel";
        } else {
            echo Order::get_rss(20);
        }
    }

    /*
         * Shipping labels and tracking codes
     */

    protected function preview_onLoadShippingLabelForm($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);

            $shipping_method = $order->shipping_method;
            if (!$shipping_method) {
                throw new ApplicationException('Shipping method not found.');
            }

            $shipping_method->order = $order;

            $shipping_method->define_form_fields('print_label');
            $this->viewData['shipping_method'] = $shipping_method;
            $this->viewData['shipping_method_obj'] = $shipping_method->get_shippingtype_object();
            $this->viewData['tracking_code'] = OrderTrackingCode::find_by_order_and_method(
                $order,
                $order->shipping_method
            );
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('print_label_form');
    }

    protected function preview_onGenerateLabels($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);

            $shipping_method = $order->shipping_method;
            if (!$shipping_method) {
                throw new ApplicationException('Shipping method not found.');
            }

            $shipping_method->order = $order;
            $labels = $shipping_method->generate_shipping_labels(
                $order,
                post(get_class_id('Shop\ShippingOption'), array())
            );
            $this->viewData['labels'] = $labels;
            $this->viewData['form_model'] = $order;

            $this->renderMultiple(array(
                'shipping_label_list' => '@_shipping_label_links',
                'tracking_code_list' => '@_tracking_code_list'
            ));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    public function shippinglabel($link)
    {
        try {
            ShippingLabel::output_label($link);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function preview_onDeleteTrackingCode($order_id)
    {
        try {
            $code = OrderTrackingCode::create()->find(post('code_id'));
            if (!$code) {
                throw new ApplicationException('Tracking code not found.');
            }

            Backend::$events->fireEvent('shop:onBeforeDeleteShippingTrackingCode', $order_id, $code);

            $code->delete();

            $this->viewData['form_model'] = $this->getOrderObj($order_id);
            $this->renderPartial('tracking_code_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function preview_onLoadShippingCodeForm($order_id)
    {
        try {
            $order = $this->getOrderObj($order_id);

            $shipping_method = $order->shipping_method;
            if (!$shipping_method) {
                throw new ApplicationException('Shipping method not found.');
            }

            $model = new OrderTrackingCode();
            $model->shipping_method_id = $order->shipping_method_id;

            $model->init_columns_info();
            $model->define_form_fields();

            $this->viewData['tracking_code'] = $model;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('tracking_code_form');
    }

    protected function preview_onSaveTrackingNumber($order_id)
    {
        try {
            $model = new OrderTrackingCode();
            $model->init_columns_info();
            $model->define_form_fields();

            $order = $this->getOrderObj($order_id);

            $model->order_id = $order_id;
            $model->save(post(get_class_id('Shop\OrderTrackingCode'), array()));

            $this->viewData['form_model'] = $order;
            $this->renderPartial('tracking_code_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }


    protected function addBundleViewData($item = null)
    {
        $bundle_offer = null;
        $bundle_offer_item = null;
        $bundle_master_order_item_id = null;
        $bundle_offer_id = $this->viewData['bundle_offer_id'] = post('bundle_offer_id');

        if ($bundle_offer_id) {
            $bundle_offer = ProductBundleOffer::create()->find($bundle_offer_id);
            if ($bundle_offer) {
                $this->viewData['bundle_offer_name'] = $bundle_offer->name;
            }
        }

        if (post('bundle_offer_item_id')) {
            $bundle_offer_item = ProductBundleOfferItem::create()->find(post('bundle_offer_item_id'));
            if (!$bundle_offer_item) {
                throw new ApplicationException('Bundle offer item not found');
            }
        } elseif ($bundle_offer && $item) {
            $bundle_offer_item = $bundle_offer->get_item_product($item->product);
            $bundle_master_order_item_id = $item->bundle_master_order_item_id;
        }

        if ($bundle_offer_item) {
            $_POST['product_id'] = $bundle_offer_item->product->id;
        }

        $this->viewData['bundle_offer_item_id'] = $bundle_offer_item ? $bundle_offer_item->id : null;
        $this->viewData['bundle_master_order_item_id'] = post(
            'bundle_master_order_item_id',
            $bundle_master_order_item_id
        );
        $this->viewData['bundle_offer_item'] = $bundle_offer_item;
    }

    /*
         * Custom events
     */

    protected function onCustomEvent($id = null)
    {
        $order = null;
        $edit = Phpr::$router->action == 'edit';
        $create = Phpr::$router->action == 'create';
        $preview = Phpr::$router->action == 'preview';
        if ($edit || $create || $preview) {
            $order = $this->getOrderObj($id);
        }

        Backend::$events->fireEvent(post('custom_event_handler'), $this, $order);
    }


    /*
         * Order Helper Functions
     */

    protected function getAvailablePaymentMethods($order)
    {
        $order->set_form_data();
        return OrderHelper::getAvailablePaymentMethods($order, $this->formGetEditSessionKey());
    }

    /**
     * @deprecated since v1.3
     */
    protected function getAvailableShippingMethods($order)
    {
        return $order->list_available_shipping_options($this->formGetEditSessionKey(), false);
    }


    /**
     * @deprecated since v1.3
     */
    protected function findLastOrder()
    {
        return OrderHelper::findLastOrder();
    }

    /**
     * @deprecated since v1.3
     */
    private function find_customer($order, $check_order_data = false)
    {
        return OrderHelper::find_customer($order, $check_order_data);
    }

    /**
     * @deprecated since v1.3
     */
    private function find_customer_group_id($order)
    {
        $customer = OrderHelper::find_customer($order);

        if ($customer) {
            return $customer->customer_group_id;
        } else {
            return CustomerGroup::get_guest_group()->id;
        }
    }
}
