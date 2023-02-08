<?php
use Core\ModuleManager;
use Backend\Events;

/*
 * Backend module events object
 * Alias for legacy code
 */
Backend::$events = Phpr::$events;

/*
 * Load and initialize modules
 */
ModuleManager::listModules();
Backend::$events->fireEvent('core:onInitialize');
