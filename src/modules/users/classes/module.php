<?php

namespace Users;

use Phpr\ModuleInfo;
use Core\ModuleBase;

class Module extends ModuleBase
{
    /**
     * Creates the module information object
     * @return ModuleInfo
     */
    protected function createModuleInfo()
    {
        return new ModuleInfo(
            "Users",
            "LSAPP backend user management",
            "LSAPP - MJMAN"
        );
    }
}
