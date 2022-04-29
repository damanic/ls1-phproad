<?php
namespace System;

use Backend\Controller;
use Core\ModuleManager;

class MySettings extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }
        
    public function index()
    {
        $this->app_page_title = 'My Settings';
        $this->override_module_name = 'My Settings';
        $this->viewData['items'] = ModuleManager::listPersonalSettingsItems(true);
        $this->viewData['body_class'] = 'no_padding';
    }
}
