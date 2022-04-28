<?php
define('frm_currency', 'frm_currency');
use Core\ModuleManager;

/*
 * Backend module events object
 */
Backend::$events = new Backend_Events();

/*
 * Load and initialize modules
 */
ModuleManager::listModules();
Backend::$events->fireEvent('core:onInitialize');
