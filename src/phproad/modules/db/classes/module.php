<?php namespace Db;

use Phpr\ModuleBase;

class Module extends ModuleBase
{
    public function createModuleInfo()
    {
        return new ModuleInfo(
            "PHPR DB",
            "Database framework",
            "PHPRoad",
            null
        );
    }
}
