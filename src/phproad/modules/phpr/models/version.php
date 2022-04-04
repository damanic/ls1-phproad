<?php

namespace Phpr;

use Db\ActiveRecord;
use Db\Helper as Db_Helper;

/**
 * Data object for Module versions
 */
class Version extends ActiveRecord
{
    public $table_name = 'phpr_module_versions';

    protected static $build_cache = null;
    protected static $version_cache = null;
    protected static $db_version_string = null;

    public static function create()
    {
        return new self();
    }

    public static function getModuleVersion($module_id)
    {
        $module_id = strtolower($module_id);

        $version = self::create()->find_by_module_id($module_id);
        if ($version) {
            return $version->version_str;
        }

        return '1.0.0';
    }

    public static function getModuleVersionCached($module_id)
    {
        if (self::$version_cache != null) {
            return array_key_exists($module_id, self::$version_cache) ? self::$version_cache[$module_id] : 0;
        }

        self::$version_cache = array();
        $versions = Db_Helper::object_array('select * from phpr_module_versions');
        foreach ($versions as $version) {
            if (!isset($version->module_id)) {
                continue;
            }

            self::$version_cache[$version->module_id] = $version->version_str;
        }

        return array_key_exists($module_id, self::$version_cache) ? self::$version_cache[$module_id] : 0;
    }

    public static function getModuleBuild($module_id)
    {
        $module_id = strtolower($module_id);

        $version = self::create()->find_by_module_id($module_id);
        if ($version) {
            return $version->version;
        }

        return 0;
    }

    public static function getModuleBuildCached($module_id)
    {
        if (self::$build_cache != null) {
            return array_key_exists($module_id, self::$build_cache) ? self::$build_cache[$module_id] : 0;
        }

        self::$build_cache = array();
        $versions = Db_Helper::objectArray('select * from phpr_module_versions');
        foreach ($versions as $version) {
            if (!isset($version->module_id)) {
                continue;
            }

            self::$build_cache[$version->module_id] = $version->version;
        }

        return array_key_exists($module_id, self::$build_cache) ? self::$build_cache[$module_id] : 0;
    }

    public static function getModuleBuildsString()
    {
        if (self::$db_version_string === null) {
            self::$db_version_string = Db_DbHelper::scalar(
                "select group_concat(version order by module_id separator '|') from phpr_module_versions"
            );
        }

        return self::$db_version_string;
    }
}
