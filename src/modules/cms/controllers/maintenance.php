<?php
namespace Cms;

use Phpr;
use Backend\SettingsController;
use Users\Group as UserGroup;

class Maintenance extends SettingsController
{
    public $implement = 'Db_FormBehavior, Cms_PageSelector';

    public $form_edit_title = 'Maintenance Configuration';
    public $form_model_class = 'Cms\MaintenanceParams';
    public $form_redirect = null;

    protected $access_for_groups = array(UserGroup::ADMIN);

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';

        $this->app_page = 'settings';
    }
        
    public function index()
    {
        try {
            $this->app_page_title = $this->form_edit_title;
            
            $obj = new MaintenanceParams();
            $this->viewData['form_model'] = $obj->load();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    protected function index_onSave()
    {
        try {
            $obj = new MaintenanceParams();
            $obj = $obj->load();
            $classId = get_class_id($this->form_model_class);
            $obj->save(post($classId, array()), $this->formGetEditSessionKey());
                
            $this->cms_page_selector_save_model_data($this, $obj);
                
            Phpr::$session->flash['success'] = 'Maintenance configuration has been successfully saved.';
            Phpr::$response->redirect(url('system/settings/'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
