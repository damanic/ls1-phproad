<?php

namespace Phpr;

use Phpr\DriverBase;
use ReflectionProperty;

/**
 * PHPR driver manager
 *
 * Used to locate and interact with module drivers
 */
class DriverManager
{

    const DRIVERS_DIRECTORY = 'drivers';

    private static $objectCache = array();
    private static $classCache = array();

    /**
     * Returns a list of drivers.
     * @return array of driver class names
     */
    public static function getClassNames($driver_class)
    {
        if (!property_exists($driver_class, 'driverFolder')) {
            throw new Exception(
                'Please create a static definintion ' . $driver_class . '::$driverFolder which declares the drivers folder name (eg: notification_drivers)'
            );
        }

        if (!property_exists($driver_class, 'driverSuffix')) {
            throw new Exception(
                'Please create a static definintion ' . $driver_class . '::$driverSuffix which declares the drivers file name suffix (eg: _type)'
            );
        }

        $driver_folder = new ReflectionProperty($driver_class, 'driverFolder');
        $driver_folder = $driver_folder->getValue();

        $driver_suffix = new ReflectionProperty($driver_class, 'driverSuffix');
        $driver_suffix = $driver_suffix->getValue();

        if (array_key_exists($driver_class, self::$classCache)) {
            return self::$classCache[$driver_class];
        }

        $modules = ModuleManager::getModules();
        foreach ($modules as $id => $module_info) {
            $class_path = PATH_APP . "/" . PHPR_MODULES . "/" . $id . "/" . self::DRIVERS_DIRECTORY . "/" . $driver_folder;

            if (!file_exists($class_path)) {
                continue;
            }

            $iterator = new \DirectoryIterator($class_path);

            foreach ($iterator as $file) {
                if (!$file->isDir() && preg_match(
                        '/^' . $id . '_[^\.]*' . preg_quote($driver_suffix) . '.php$/i',
                        $file->getFilename()
                    )) {
                    require_once($class_path . '/' . $file->getFilename());
                }
            }
        }

        $classes = get_declared_classes();
        $driver_classes = array();
        foreach ($classes as $class_name) {
            if (get_parent_class($class_name) != $driver_class) {
                continue;
            }

            $driver_classes[] = $class_name;
        }

        return self::$classCache[$driver_class] = $driver_classes;
    }

    public static function getDrivers($driver_class)
    {
        if (array_key_exists($driver_class, self::$objectCache)) {
            return self::$objectCache[$driver_class];
        }

        $driver_objects = array();
        foreach (self::getClassNames($driver_class) as $class_name) {
            $obj = new $class_name();

            // get_info() method must exist, and return an array
            if (is_array($obj->getInfo())) {
                $driver_objects[] = $obj;
            }
        }

        return self::$objectCache[$driver_class] = $driver_objects;
    }

    /**
     * Finds a given driver
     * @param $id the id of the driver declared in get_info
     * @param $only_enabled discard non-enabled drivers from the search
     */
    public static function getDriver($driver_class, $code)
    {
        $drivers = self::getDrivers($driver_class);
        foreach ($drivers as $driver) {
            if ($driver->getCode() == $code) {
                return $driver;
            }
        }
        return new DriverBase();
    }

}