<?php
namespace Shop;

use Phpr;
use Backend\SettingsController;
use Users\Groups as UserGroup;
use Phpr\ApplicationException;

class Configuration extends SettingsController
{
    protected $access_for_groups = array(UserGroup::admin);
    public $implement = 'Db_FormBehavior';

    public $form_edit_title = 'eCommerce Settings';
    public $form_model_class = 'Shop\ConfigurationRecord';
        
    public $form_redirect = null;
    public $form_edit_save_flash = 'eCommerce configuration has been saved.';

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
            
        $this->form_redirect = url('system/settings/');
    }

    public function index()
    {
        try {
            $record = ConfigurationRecord::get();
            if (!$record) {
                throw new ApplicationException('eCommerce configuration is not found.');
            }
                
            $this->edit($record->id);
            $this->app_page_title = $this->form_edit_title;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function index_onSave()
    {
        $record = ConfigurationRecord::get();
        $this->edit_onSave($record->id);
    }
        
    protected function index_onCancel()
    {
        $record = ConfigurationRecord::get();
        $this->edit_onCancel($record->id);
    }
        
    protected function index_onUpdateTaxInclStates()
    {
        try {
            $record = ConfigurationRecord::get();
            $record->init_columns_info();
            $record->define_form_fields();
            $classId = get_class_id('Shop_ConfigurationRecord');
            $record->set_data(post($classId, array()));
            echo ">>form_field_container_tax_inclusive_state_id$classId<<";
            $this->formRenderFieldContainer($record, 'tax_inclusive_state_id');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
