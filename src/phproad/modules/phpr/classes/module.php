<?php
namespace Phpr;

/**
 * PHP Road module class
 *
 * This class assists in working with the PHP Road modules.
 */
class Module extends ModuleBase
{
    public function setModuleInfo()
    {
        return new ModuleDetail(
            "PHPR",
            "Core framework",
            "PHPRoad",
            null
        );
    }

    /**
     * Returns a module directory location.
     *
     * @param  string $Module Specifies a module name, case-sensitive.
     * @return mixed Returns a module directory path. If the module specified could not be located returns null.
     */
    public static function findModule($Module)
    {
        $Module = strtolower($Module);
        foreach (array(PATH_APP, PATH_SYSTEM) as $basePath) {
            if (file_exists("$basePath/modules/$Module")) {
                return "$basePath/modules/$Module";
            }
        }

        return null;
    }
}
