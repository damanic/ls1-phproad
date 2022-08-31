<?php

/*
 * Override the Security object
 */

if (! isset($Phpr_NoSession) || ! $Phpr_NoSession ) {
    Phpr::$security          = new Core_Security();
    Phpr::$frontend_security = new Core_FrontEndSecurity();
}

/*
 * Begin the session
 */

if (! isset($Phpr_NoSession) || ! $Phpr_NoSession ) {
    $session_name = null;
    if (isset($CONFIG) && isset($CONFIG['SESSION_COOKIE_NAME']) ) {
        $session_name = $CONFIG['SESSION_COOKIE_NAME'];
    }
    Phpr::$session->start($session_name);
}


/*
 * Admin routes
 */

if (isset($CONFIG) && isset($CONFIG['BACKEND_URL']) ) {
    $backend_url = $CONFIG['BACKEND_URL'];
} else {
    $backend_url = '/backend';
}

if (substr($backend_url, 0, 1) == '/' ) {
    $backend_url = substr($backend_url, 1);
}

/*
 * Backend Files
 */
$route = Phpr::$router->addRule('backend_file_get/:param1/:param2/:param3/:param4');
$route->folder('modules/backend/controllers');
$route->controller('backend_files');
$route->action('get');
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);

/*
 * Backend Reports
 */
$route = Phpr::$router->addRule($backend_url . '/backend/reports');
$route->folder('modules/backend/controllers');
$route->controller('backend_reportscontroller');
$route->action('index');


/*
 * Backend Session (login)
 */
$route = Phpr::$router->addRule( $backend_url . "/session/:param1" );
$route->folder( 'modules/backend/controllers' );
$route->controller( 'backend_session' );
$route->def( 'param1', null );
$route->check('param1', '/^$/ ');

$route = Phpr::$router->addRule( $backend_url . "/session/handle/:action/:param1" );
$route->folder( 'modules/backend/controllers' );
$route->controller( 'backend_session' );
$route->def( 'action', 'index' );
$route->def( 'param1', null );

/*
 * Backend Module Controllers
 */
$route = Phpr::$router->addRule($backend_url . '/:module/:controller/:action/:param1/:param2/:param3/:param4');
$route->folder('modules/:module/controllers');
$route->def('module', 'backend');
$route->def('controller', 'index');
$route->def('action', 'index');
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->convert('controller', '/^.*$/', ':module_$0');

/*
 * Configuration Tool
 */

if (isset($CONFIG) && isset($CONFIG['CONFIG_URL']) ) {
    $config_url = $CONFIG['CONFIG_URL'];
} else {
    $config_url = '/config_tool';
}

if (substr($config_url, 0, 1) == '/' ) {
    $config_url = substr($config_url, 1);
}

$route = Phpr::$router->addRule($config_url . '/:action/:param1/:param2/:param3/:param4');
$route->folder('modules/core/controllers');
$route->def('action', 'index');
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->controller('LemonStand_ConfigController');


/*
 * Public routes
 */
$route = Phpr::$router->addRule('download_product_file/:param1/:param2/:param3/:param4/:param5');
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->def('param5', null);
$route->controller('application');
$route->action('download_product_file');

$route = Phpr::$router->addRule('backend_theme_styles_hidden_url');
$route->controller('application');
$route->action('backend_theme_styles_hidden_url');

$route = Phpr::$router->addRule('/:param1/:param2/:param3/:param4/:param5/:param6');
$route->def('param1', null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->def('param5', null);
$route->def('param6', null);
$route->controller('application');
$route->action('index');

/*
 * Send the no-cache headers
 */


header('Pragma: public');
header('Expires: 0');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: pre-check=0, post-check=0, max-age=0', false);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

header('Content-type: text/html; charset=utf-8');

/*
 * Init multibyte strings encoding
 */

mb_internal_encoding('UTF-8');

/*
 * Other configuration options
 */

ini_set('auto_detect_line_endings', true);

if (! isset($APP_CONF) ) {
    $APP_CONF = array();
}

$APP_CONF['UPDATE_SEQUENCE'] = array( 'net','filesystem','db','phpr','core', 'system', 'users', 'cms', 'shop' );
$APP_CONF['DB_CONFIG_MODE']  = 'secure';
$APP_CONF['UPDATE_CENTER']   = 'v1.lsdomainexpired.mjman.net/lsapp_update_gateway';


