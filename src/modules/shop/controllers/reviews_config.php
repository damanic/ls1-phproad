<?php
namespace Shop;

use Phpr;
use Backend\SettingsController;
use Users\Group as UserGroup;

class Reviews_Config extends SettingsController
{
    public $implement = 'Db_FormBehavior';

    public $form_edit_title = 'Ratings & Reviews Configuration';
    public $form_model_class = 'Shop\ReviewsConfiguration';
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
            
            $obj = new ReviewsConfiguration();
            $this->viewData['form_model'] = $obj->load();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    protected function index_onSave()
    {
        try {
            $obj = new ReviewsConfiguration();
            $obj = $obj->load();

            $obj->save(post($this->form_model_class, array()), $this->formGetEditSessionKey());
                
            Phpr::$session->flash['success'] = 'Ratings and Reviews configuration has been successfully saved.';
            Phpr::$response->redirect(url('system/settings/'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
