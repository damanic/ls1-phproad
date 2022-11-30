<?php

namespace Users;

use Db\Helper as DbHelper;

class Permissions
{
    public $table_name = 'users_user_permissions';
    protected static $permission_cache = array();

    public static function save_user_permissions($user_id, $module_id, $permission_name, $value)
    {
        $bind = [
            'user_id' => $user_id,
            'module_id' => $module_id,
            'permission_name' => $permission_name,
            'value' => $value
        ];

        DbHelper::query(
            'delete from users_user_permissions 
                  where user_id=:user_id 
                  and module_id=:module_id 
                  and permission_name=:permission_name',
            $bind
        );

        DbHelper::query(
            'insert into users_user_permissions (user_id, module_id, permission_name, value) 
                  values(:user_id, :module_id, :permission_name, :value)',
            $bind
        );
    }

    public static function get_user_permission($user_id, $module_id, $permission_name)
    {
        if (!array_key_exists($user_id, self::$permission_cache)) {
            $permissions = DbHelper::objectArray(
                'select * from users_user_permissions where user_id=:user_id',
                ['user_id' => $user_id]
            );

            $user_permissions = array();
            foreach ($permissions as $permission) {
                if (!array_key_exists($permission->module_id, $user_permissions)) {
                    $user_permissions[$permission->module_id] = array();
                }

                $user_permissions[$permission->module_id][$permission->permission_name] = $permission->value;
            }

            self::$permission_cache[$user_id] = $user_permissions;
        }

        if (!array_key_exists($user_id, self::$permission_cache)) {
            return null;
        }

        if (!array_key_exists($module_id, self::$permission_cache[$user_id])) {
            return null;
        }

        if (!array_key_exists($permission_name, self::$permission_cache[$user_id][$module_id])) {
            return null;
        }

        return self::$permission_cache[$user_id][$module_id][$permission_name];
    }

    public static function get_user_permissions($user_id)
    {
        return DbHelper::objectArray(
            'select * from users_user_permissions where user_id=:user_id',
            ['user_id' => $user_id]
        );
    }
}
