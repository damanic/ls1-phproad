<?php

namespace Phpr;

use ReflectionClass;
use Phpr;

/**
 * Class loader
 * This class is used by the PHP Road internally for finding and loading classes.
 * The instance of this class is available in the Phpr global object: Phpr::$classLoader.
 */
class ClassLoader
{

    private $paths;
    private $auto_init = null;
    private $cache;
    private $reservedClassAliases = array(
        'cms\object',
        'core\string',
    );

    public function __construct()
    {
        global $CONFIG;

        $paths =  array(
            PATH_APP,
            PATH_SYSTEM,
        );

        $this->cache = array();
        $this->paths = array(
            'application' => $CONFIG['APPLICATION_PATHS'] ?? $paths,
            'library' => array('controllers', 'classes', 'models'),
            'module' => array(
                'widgets',
                'classes',
                'helpers',
                'models',
                'behaviors',
                'controllers',
                'shipping_types',
                'payment_types',
            ),
        );
    }

    /**
     * Loads a class
     *
     * @param string $class Class name
     * @return bool If it loaded the class
     */
    public function load($class, $force_disabled = false)
    {
        $loaded = false;

        if (!$this->auto_init) {
            $this->auto_init = $class;
        }

        // Class already exists, no need to reload
        if (class_exists($class)) {
            $this->init_class($class);
            $loaded = true;
        }

        if (!$loaded) {
            $loaded = $this->load_local($class);
        }

        if (!$loaded) {
            $loaded = $this->load_module($class, $force_disabled);
        }

        if (!$loaded) {
            $loaded = $this->load_module_classic($class, $force_disabled);
        }

        // Prevents a failed init from breaking workflow
        if ($this->auto_init == $class) {
            $this->auto_init = null;
        }

        return $loaded;
    }

    /**
     * Look for a class locally
     *
     * @param string $class Class name
     * @return bool If the class is found
     */
    private function load_local($class)
    {
        // Local cannot use namespaces
        if (strpos($class, '\\') !== false) {
            return false;
        }

        $file_name = strtolower($class) . '.' . PHPR_EXT;

        foreach ($this->paths['library'] as $path) {
            $full_path = $path . DS . $file_name;

            if (!$this->file_exists($full_path)) {
                continue;
            }

            include $full_path;

            if (class_exists($class)) {
                $this->init_class($class);
                return true;
            }
        }

        return false;
    }

    /**
     * Looks for a class located within a module
     *
     * @param string $class Class name
     * @return bool If the class is found
     */
    private function load_module($class, $force_disabled = false)
    {
        global $CONFIG;

        $disabled_modules = isset($CONFIG['DISABLE_MODULES']) ? $CONFIG['DISABLE_MODULES'] : array();

        $class_alias = null;
        $namespace_pos = strpos($class, '\\');

        //
        // Requested class contains no namespace, so spoof one
        // Phpr_Class_Name -> Phpr\Class_Name
        //
        if ($namespace_pos === false) {
            $class_alias = $class;
            $class = $this->convertLegacyClassName($class);
            $namespace_pos = strpos($class, '\\');

            //
            // If we are calling an alias, check the spoofed class
            // hasn't already been loaded
            //
            if (class_exists($class) && !class_exists($class_alias)) {
                class_alias($class, $class_alias);
                return true;
            }
        }

        if (class_exists($class)) {
            return true;
        }


        //
        // Proceed with loading
        //

        $file_name = strtolower(substr($class, $namespace_pos + 1)) . '.' . PHPR_EXT;
        $module_name = strtolower(substr($class, 0, $namespace_pos));

        // Is disabled?
        if (in_array($module_name, $disabled_modules) && !$force_disabled) {
            return false;
        }

        foreach ($this->paths['application'] as $module_path) {
            foreach ($this->paths['module'] as $path) {
                $full_path = $module_path . DS
                    . PHPR_MODULES . DS
                    . $module_name . DS
                    . $path . DS
                    . $file_name;

                if (!$this->file_exists($full_path)) {
                    continue;
                }

                include_once $full_path;

                if (class_exists($class)) {
                    $this->init_class($class);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Looks for a class located within a module
     *
     * @param string $class Class name
     * @return bool If the class is found
     */
    private function load_module_classic($class, $force_disabled = false)
    {
        global $CONFIG;
        $disabled_modules = isset($CONFIG['DISABLE_MODULES']) ? $CONFIG['DISABLE_MODULES'] : array();

        $classic_class_name = str_replace('\\', '_', $class);
        $file_name = strtolower($classic_class_name) . '.' . PHPR_EXT;
        $underscore_pos = strpos($classic_class_name, '_');
        $module_name = strtolower(
            ($underscore_pos)
                ? substr($classic_class_name, 0, $underscore_pos)
                : $class
        );

        // Is disabled?
        if (in_array($module_name, $disabled_modules) && !$force_disabled) {
            return false;
        }

        foreach ($this->paths['application'] as $module_path) {
            foreach ($this->paths['module'] as $path) {
                $full_path = $module_path . DS
                    . PHPR_MODULES . DS
                    . $module_name . DS
                    . $path . DS
                    . $file_name;

                if (!$this->file_exists($full_path)) {
                    continue;
                }

                include_once $full_path;

                if (class_exists($classic_class_name)) {
                    // Create a class alias for namespace compatibility
                    if (!class_exists($class)) {
                        if (!in_array(strtolower($class), $this->reservedClassAliases)) {
                            class_alias($classic_class_name, $class);
                        }
                    }

                    $this->init_class($classic_class_name);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Loads an application controller by the class name and returns the controller instance.
     *
     * @param string $className Specifies a name of the controller to load.
     * @param string $controller_path Specifies a path to the controller directory.
     * @return Phpr\Controller The controller instance or null.
     */
    public function load_controller($className, $controllerDirectory = null)
    {
        foreach ($this->paths['application'] as $path) {
            $controllerDirectory = $controllerDirectory ? $controllerDirectory : 'controllers';
            $controllerPath = realpath($path . DS . $controllerDirectory);

            if (!strlen($controllerPath)) {
                continue;
            }

            if (!class_exists($className)) {
                $className = $this->convertLegacyClassName($className);
                if (!class_exists($className)) {
                    continue;
                }
            }

            // Make sure the class requested is in the application controllers directory
            $class_info = new ReflectionClass($className);
            $classDir = realpath(dirname($class_info->getFileName()));
            if ($classDir !== $controllerPath) {
                continue;
            }

            Controller::$current = new $className();
            Phpr::$events->fire_event(
                'phpr:on_configure_' . Inflector::underscore($className) . '_controller',
                Controller::$current
            );
            return Controller::$current;
        }
    }

    /**
     * Registers a class library directory.
     * Use this method to register a directory containing your application classes.
     *
     * @param string $path Specifies a full path to the directory. No trailing slash.
     */
    public function add_library_directory($path)
    {
        array_unshift($this->paths['library'], $path);
    }

    /**
     * Registers a application directory.
     * Use this method to register a directory containing your application classes.
     *
     * @param string $path Specifies a full path to the directory. No trailing slash.
     */
    public function add_application_directory($path)
    {
        array_unshift($this->paths['application'], $path);
    }

    /**
     * Registers a module directory.
     * Use this method to register a directory containing your module classes.
     *
     * @param string $path Specifies a full path to the directory. No trailing slash.
     */
    public function add_module_directory($path)
    {
        array_unshift($this->paths['module'], $path);
    }

    public function get_library_directories()
    {
        return $this->paths['library'];
    }

    public function get_application_directories()
    {
        return $this->paths['application'];
    }

    public function get_module_directories()
    {
        return $this->paths['module'];
    }

    /**
     * Looks up a specific file path located in an application directory
     *
     * @param string $path File to locate
     * @return  string
     * @example 1
     * Look up file init.php
     * $path = Phpr::$classLoader->find_path('init/init.php');
     */
    public function find_path($path)
    {
        global $CONFIG;

        foreach ($this->paths['application'] as $application_path) {
            $real_path = realpath($application_path . DS . $path);

            if ($real_path && file_exists($real_path)) {
                return $real_path;
            }
        }
    }

    /**
     * Returns all application paths for a given folder
     *
     * @param string $path Folder name
     * @return  array
     * @example 1
     * Find all module directories
     * $dirs = Phpr::$classLoader->find_paths('modules');
     */
    public function find_paths($path)
    {
        global $CONFIG;

        $paths = array();

        foreach ($this->paths['application'] as $application_path) {
            $real_path = realpath($application_path . DS . $path);

            if ($real_path && file_exists($real_path)) {
                $paths[] = $real_path;
            }
        }

        return $paths;
    }

    /**
     * Check the existence of a file, whilst caching directories
     *
     * @param string $path Absolute path to file
     * @return bool If file exists
     */
    private function file_exists($path)
    {
        try {
            $dir = dirname($path);
            $base = basename($path);

            if (!isset($this->cache[$dir])) {
                $this->cache[$dir] = (is_dir($dir))
                    ? scandir($dir)
                    : array();
            }
        } catch (exception $ex) {
            // Debug
            echo $path . ' ' . $ex->getMessage();
        }

        return in_array($base, $this->cache[$dir]);
    }

    /**
     * Checks to see if the given class has a static init() method.
     * If so then it calls it.
     *
     * @param string class name
     */
    protected function init_class($class)
    {
        if ($this->auto_init === $class) {
            $this->auto_init = null;

            if (method_exists($class, 'init') && is_callable($class . '::init')) {
                call_user_func($class . '::init');
            }
        }
    }

    private function convertLegacyClassName($className)
    {
        $namespaced = strpos($className, '\\');
        if (!$namespaced) {
            $className = preg_replace('/\\_/', '\\', $className, 1);
        }
        return $className;
    }

    /**
     * @deprecated
     */
    public function addDirectory($path)
    {
        return $this->add_library_directory($path);
    }

    /**
     * @deprecated
     */
    public function loadController($class_name, $controller_path = null)
    {
        return $this->load_controller($class_name, $controller_path);
    }
}
