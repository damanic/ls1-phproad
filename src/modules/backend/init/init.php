<?php
use Core\ModuleManager;
use Backend\Events;

/*
 * Backend module events object
 */
Backend::$events = new Events();

/*
 * Load and initialize modules
 */
ModuleManager::listModules();
Backend::$events->fireEvent('core:onInitialize');
