<?php

// Fix for XDebug aborting threads > 100 nested
ini_set('xdebug.max_nesting_level', 300);

//
// PHP functions that should really exist
//

// Creates a unified class id
if (! function_exists('get_class_id') ) {
    function get_class_id( $obj )
    {
        if (is_string($obj) ) {
            $class_name = $obj;
        } else {
            $class_name = get_class($obj);
        }

        $class_name = str_replace('\\', '_', $class_name);
        return $class_name;
    }
}

// Obtains an object class name without namespaces
if (! function_exists('get_real_class') ) {
    function get_real_class( $obj )
    {
        if (! is_string($obj) ) {
            $class_name = get_class($obj);
        } else {
            $class_name = $obj;
        }

        if (preg_match('@\\\\([\w]+)$@', $class_name, $matches) ) {
            $class_name = $matches[1];
        }

        return $class_name;
    }
}

if (! strlen(trim($applicationRoot)) ) {
    $applicationRoot = dirname($bootstrapPath);
}

$path_app = str_replace('\\', '/', realpath($applicationRoot));

//
// Configuration
//

// Load initial configuration. This is included again below.
if ($path = realpath(PATH_APP . '/config/config.php') ) {
    include $path;
}

// Core PHPR class
require_once 'phpr.php';

//
// Initialize auto class loading
//

require_once 'classloader.php';

Phpr::$classLoader = new Phpr\ClassLoader();

/**
 * Loads a class with the specified name.
 * If the class requested is not found, the function attempts to invoke the Phpr_autoload($ClassName) function.
 * Declare the Phpr_autoload function on the application code to allow the application classes to be loaded on demand.
 *
 * @param string $class_name Specifies the class name to load
 */
function Phpr_InternalAutoload( $name )
{
    if (! Phpr::$classLoader->load($name) && function_exists('Phpr_autoload') ) {
        Phpr_autoload($name);
    }
}

spl_autoload_register('Phpr_InternalAutoload');

/*
 * Add vendor autoload
 */
require PATH_SYSTEM . '/vendor/autoload.php';

// Exception handling
require_once 'exceptions.php';

/*
 * Initialize the events object
 */

Phpr::$events = new Phpr\Events();

/*
 * Initialize the response object
 */

Phpr::$response = new Phpr\Response();

/*
 * Initialize the session object
 */

Phpr::$session = new Phpr\Session();

/*
 * Initialize the security system
 */
Phpr::$security = new Phpr\Security();

// Internal deprecation
Phpr::$deprecate = new Phpr\Deprecate();
Phpr\Deprecate::$suppressReported = true;

/*
 * Configure the application and initialize the request object
 */

if (Phpr::$router === null ) {
    Phpr::$router = new Phpr\Router();
}

// Load config for usage
if ($path = realpath(PATH_APP . '/config/config.php') ) {
    include $path;
}

// Initialize script
if ($path = Phpr::$classLoader->find_path('init/init.php') ) {
    include_once $path;
}

Phpr::$config = new Phpr\Config();

Phpr::$request = new Phpr\Request();

require PATH_SYSTEM . '/core/class_functions.php';

if (file_exists(PATH_APP . '/' . 'init/custom_helpers.php') ) {
    include PATH_APP . '/' . 'init/custom_helpers.php';
}

/*
 * Initialize the core objects
 */

if (Phpr::$errorLog === null ) {
    Phpr::$errorLog = new Phpr\ErrorLog();
}
if (Phpr::$traceLog === null ) {
    Phpr::$traceLog = new Phpr\TraceLog();
}

Phpr::$lang = new Phpr\Locale();

/*
 * Run modules initialization scripts
 */

function init_phpr_modules()
{
    $paths = Phpr::$classLoader->find_paths('modules');

    foreach ( $paths as $path ) {
        $iterator = new DirectoryIterator($path);

        foreach ( $iterator as $directory ) {
            if (! $directory->isDir() || $directory->isDot() ) {
                continue;
            }

            if (! file_exists($module_file = $directory->getPathname() . '/classes/module.php')
                && ! file_exists(
                    $module_file = $directory->getPathname() . '/classes/' . basename(
                        $directory->getPathname()
                    ) . '_module.php'
                ) 
            ) {
                continue;
            }

            if (! file_exists($init_dir = $directory->getPathname() . '/init') ) {
                continue;
            }

            $file_iterator = new DirectoryIterator($init_dir);

            foreach ( $file_iterator as $file ) {
                if (! $file->isFile() ) {
                    continue;
                }

                $info = pathinfo($file->getPathname());

                if (isset($info['extension']) && $info['extension'] == PHPR_EXT ) {
                    include $file->getPathname();
                }
            }
        }
    }
}

init_phpr_modules();


Phpr::$session->restoreDbData();
