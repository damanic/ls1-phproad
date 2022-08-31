<?php
namespace System;

use Backend\SettingsController;
use Users\Group as UserGroup;

class Editor_Config extends SettingsController
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior';
    public $list_model_class = 'System\HtmlEditorConfig';
    public $list_record_url = null;

    public $form_model_class = 'System\HtmlEditorConfig';
    public $form_not_found_message = 'Configuration not found';
    public $form_redirect = null;
    public $form_edit_title = 'HTML Editor Settings';

    protected $access_for_groups = array(UserGroup::ADMIN);

    public function __construct()
    {
        parent::__construct();
        $this->app_module_name = 'System';
        $this->list_record_url = url('/system/editor_config/edit/');
        $this->form_redirect = url('/system/editor_config/');
    }
        
    public function index()
    {
        $this->app_page_title = 'HTML Editor Configurations';
        HtmlEditorConfig::find_init_configs();
    }
}
