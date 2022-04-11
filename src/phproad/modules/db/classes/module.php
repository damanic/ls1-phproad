<?php namespace Db;

use Phpr\ModuleBase;

class Module extends ModuleBase
{
    public function setModuleInfo()
    {
        return new ModuleDetail(
            "PHPR DB",
            "Database framework",
            "PHPRoad",
            null
        );
    }
}
