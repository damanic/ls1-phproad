<?php
namespace Shop;

use Phpr;
use Phpr\ApplicationException;
use Backend\SettingsController;
use Users\Group as UserGroup;

class Order_Numbering extends SettingsController
{
    public $implement = 'Db_FormBehavior';
        
    protected $access_for_groups = array(UserGroup::ADMIN);

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
    }

    public function index()
    {
        try {
            $this->app_page_title = 'Order Numbering';
            $this->viewData['last_used_number'] = Order::get_last_used_order_id();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function index_onSave()
    {
        try {
            $number = trim(post('new_number'));
            if (!strlen($number)) {
                throw new ApplicationException('Please specify new order number');
            }
                    
            Order::set_next_order_id($number);
                
            Phpr::$session->flash['success'] = 'New order number has been successfully saved.';
            Phpr::$response->redirect(url('system/settings/'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
