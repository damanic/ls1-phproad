<?php
namespace Backend;

use Phpr;
use Backend\CodeEditorConfiguration;

class CodeEditorSettings extends Controller
{
    public $implement = 'Db_FormBehavior';
    public $form_model_class = 'Backend\CodeEditorConfiguration';

    public function index()
    {
        $this->app_page_title = 'Code Editor Settings';
        $this->app_module_name = 'My Settings';
        $this->override_module_name = 'Code Editor Settings';
            
        $this->viewData['form_model'] = CodeEditorConfiguration::create();
    }
        
    protected function index_onSave()
    {
        try {
            $obj = CodeEditorConfiguration::create();
            $obj->save(post($this->form_model_class, array()), $this->formGetEditSessionKey());
                
            Phpr::$session->flash['success'] = 'Code editor settings have been saved.';
            Phpr::$response->redirect(url('system/mysettings'));
        } catch (Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
