<?php
namespace Phpr;

use DirectoryIterator;

use Phpr;

/**
 * PHPR module manager
 *
 * Used to locate and interact with modules
 */
class ModuleManager
{
    protected static $moduleObjects = null;

    /**
     * Returns an array of moduleObjects
     * @param bool $allowCaching
     * @param bool $returnDisabledOnly
     * @return array|null
     */
    public static function getModules($allowCaching = true, $returnDisabledOnly = false)
    {
        if ($allowCaching && !$returnDisabledOnly) {
            if (self::$moduleObjects !== null) {
                return self::$moduleObjects;
            }
        }

        if (!$returnDisabledOnly) {
            self::$moduleObjects = array();
        }

        $disabledModuleList = array();

        $disabledModules = Phpr::$config->get('DISABLE_MODULES', array());
        $applicationPaths = Phpr::$config->get('APPLICATION_PATHS', array(PATH_APP, PATH_SYSTEM));

        foreach ($applicationPaths as $appPath) {
            if ($appPath == PATH_SYSTEM) {
                continue;
            }

            $modulesPath = $appPath . DS . PHPR_MODULES;

            if (!file_exists($modulesPath)) {
                continue;
            }

            $iterator = new DirectoryIterator($modulesPath);
            foreach ($iterator as $dir) {
                if ($dir->isDir() && !$dir->isDot()) {
                    $dirPath = $modulesPath . DS . $dir->getFilename();
                    $moduleId = $dir->getFilename();

                    $disabled = in_array($moduleId, $disabledModules);

                    if (($disabled && !$returnDisabledOnly) || (!$disabled && $returnDisabledOnly)) {
                        continue;
                    }

                    if (isset(self::$moduleObjects[$moduleId])) {
                        continue;
                    }

                    $modulePath = $dirPath . DS . 'classes' . DS;
                    $fileName =  "module.php";
                    $LegacyFileName =  $moduleId . "_module.php";
                    if (!file_exists($modulePath.$fileName) && file_exists($modulePath.$LegacyFileName)) {
                        $fileName = $LegacyFileName;
                    }

                    if (!file_exists($modulePath.$fileName)) {
                        continue;
                    }

                    if (Phpr::$classLoader->load($className = $moduleId . "\Module", true)) {
                        if ($disabled) {
                            $disabledModuleList[$moduleId] = new $className($returnDisabledOnly);
                            $disabledModuleList[$moduleId]->dir_path = $dirPath;
                        } else {
                            self::$moduleObjects[$moduleId] = new $className($returnDisabledOnly);
                            self::$moduleObjects[$moduleId]->dir_path = $dirPath;
                            self::$moduleObjects[$moduleId]->subscribeEvents();
                        }
                    }
                }
            }
        }

        if ($returnDisabledOnly) {
            $result = $disabledModuleList;
        } else {
            $result = self::$moduleObjects;
        }

        uasort($result, array('\Phpr\ModuleManager', 'sortModules'));

        // Add sorted collection back to cache
        if (!$returnDisabledOnly && count($result)) {
            self::$moduleObjects = $result;
        }

        return $result;
    }

    /**
     * Returns the moduleObject for the given module Id
     * @param string $moduleId
     * @return object|null
     */
    public static function getModule($moduleId)
    {
        $modules = self::getModules();

        if (isset($modules[$moduleId])) {
            return $modules[$moduleId];
        }

        return null;
    }

    /**
     * Returns the module directory for the given module ID as an absolute path or null if not found
     * @param string $moduleId
     * @return string|null Absolute path to module directory
     */
    public static function getModulePath($moduleId)
    {
        $moduleId = strtolower($moduleId);

        $applicationPaths = Phpr::$config->get('APPLICATION_PATHS', array(PATH_APP, PATH_SYSTEM));

        foreach ($applicationPaths as $basePath) {
            $modulePath = $basePath . DS . PHPR_MODULES . DS . $moduleId;

            if (file_exists($modulePath)) {
                return $modulePath;
            }
        }

        return null;
    }

    /**
     * Checks the existence of a module
     * @param string $moduleId
     * @return bool
     */
    public static function moduleExists($moduleId)
    {
        return (bool) self::getModule($moduleId);
    }

    // Helper methods
    //

    /**
     * Sort function for module update order
     * @param object $a
     * @param object $b
     * @return int
     */
    private static function sortModules($a, $b)
    {
        return strcasecmp($a->getModuleInfo()->name, $b->getModuleInfo()->name);
    }


    /**
     * @deprecated
     */
    public static function findById($moduleId)
    {
        return self::getModule($moduleId);
    }

    /**
     * @deprecated
     */
    public static function findModules()
    {
        return self::getModules();
    }

}