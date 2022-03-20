<?php
/*
 * PHP Road application bootstrap script
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
ini_set('display_errors', true);

/*
 * This variable contains a path to this file.
 */

$bootstrapPath = __FILE__;

/*
 * Specify the application directory root
 *
 * Leave this variable blank if application root directory matches the site root directory.
 * Otherwise specify an absolute path to the application root, for example:
 * $applicationRoot = realpath( dirname($bootstrapPath)."/../app" );
 *
 */

$applicationRoot = '';

/*
 * Include the configuration script
 */

require 'config/config.php';

if (isset($CONFIG['BOOT_INCLUDE']) && is_array($CONFIG['BOOT_INCLUDE']) ) {
    foreach ( $CONFIG['BOOT_INCLUDE'] as $inc ) {
        include $inc;
    }
}


/*
 * Detect CLI
 */

$ls_cli_update_flag  = false;
$ls_cli_force_update = false;
$ls_cli_mode         = false;

$sapi = php_sapi_name();
if ($sapi == 'cli' || (! array_key_exists('DOCUMENT_ROOT', $_SERVER) || ! strlen($_SERVER['DOCUMENT_ROOT']) )) {
    $ls_cli_mode = true;
}

if ($ls_cli_mode ) {
    if (isset($_SERVER['argv']) ) {
        foreach ( $_SERVER['argv'] as $argument ) {
            if ($argument == '--update' ) {
                $ls_cli_update_flag = true;
            }

            if ($argument == '--force' ) {
                $ls_cli_force_update = true;
            }
        }
    }
}

if ($ls_cli_mode ) {
    global $Phpr_NoSession;
    global $Phpr_InitOnly;

    $Phpr_NoSession = true;
    $Phpr_InitOnly  = true;

    $APP_CONF                      = array();
    $APP_CONF['ERROR_LOG_FILE']    = dirname(__FILE__) . '/logs/cli_errors.txt';
    $APP_CONF['NO_TRACELOG_CHECK'] = true;
}

/*
 * Include the PHP Road library
 *
 * You may need to specify a full path to the phproad.php script,
 * in case if the PHP Road root directory is not specified in the PHP includes path.
 *
 */
require_once 'phproad/boot.php';

if ($ls_cli_update_flag ) {
    Core_Cli::authenticate();
    Core_UpdateManager::create()->cli_update($ls_cli_force_update);
}
