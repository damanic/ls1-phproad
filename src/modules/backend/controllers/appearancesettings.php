<?php
namespace Backend;

use Phpr;

class AppearanceSettings extends Controller
{
    public $implement = 'Db_FormBehavior';
    public $form_model_class = 'Backend\AppearanceConfiguration';

    public function index()
    {
        $this->app_page_title = 'Appearance Settings';
        $this->app_module_name = 'My Settings';
        $this->override_module_name = 'Appearance Settings';
            
        $this->viewData['form_model'] = AppearanceConfiguration::create();
    }
        
    protected function index_onSave()
    {
        try {
            $obj = AppearanceConfiguration::create();
            $classId = get_class_id($this->form_model_class);
            $obj->save(post($classId, array()), $this->formGetEditSessionKey());
                
            Phpr::$session->flash['success'] = 'Appearance settings have been saved.';
            Phpr::$response->redirect(url('system/mysettings'));
        } catch (Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
