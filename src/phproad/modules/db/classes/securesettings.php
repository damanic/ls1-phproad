<?php
namespace Db;

use Phpr\SecurityFramework;

class SecureSettings
{
    public static function get()
    {
        $framework = SecurityFramework::create();
        $config_content = $framework->get_config_content();
        $db_secure_settings = array(
            'host' => null,
            'database' => null,
            'user' => null,
            'port' => null,
            'password' => null
        );

        $keys = ['db_secure_settings', 'mysql_params'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $config_content)) {
                $db_secure_settings = $config_content[$key];
                break;
            }
        }

        return $db_secure_settings;
    }

    public static function set($parameters)
    {
        $framework = SecurityFramework::create();

        $config_content = $framework->get_config_content();
        $config_content['mysql_params'] = $parameters;
        $framework->set_config_content($config_content);
    }
}
