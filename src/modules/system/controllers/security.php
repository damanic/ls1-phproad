<?php
namespace System;

use Backend\Controller;
use Users\Groups as UserGroups;

class Security extends Controller
{
    protected $access_for_groups = array(UserGroups::admin);
        
    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
        $this->app_module_name = 'Security';
    }
        
    public function index()
    {
        $this->app_page_title = 'Security';
        $this->app_page = 'security';
    }
}
