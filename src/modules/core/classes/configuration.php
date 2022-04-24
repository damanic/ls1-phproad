<?php

namespace Core;

use Phpr;

/**
 * Returns general core configuration parameters.
 */
class Configuration
{
    public static function is_php_allowed()
    {
        return !Phpr::$config->get('CORE_DISABLE_PHP', false);
    }
}