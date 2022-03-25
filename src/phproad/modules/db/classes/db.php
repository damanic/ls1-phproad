<?php
class Db
{
    public static $connections = array();
    public static $describeCache = array();
    public static $connection = 0;

    public static function sql()
    {
        return new Db_Sql();
    }

    public static function select()
    {
        $args = func_get_args();
        $sql = new Db_Sql();

        return call_user_func_array(array(&$sql, 'select'), $args);
    }

    public static function where()
    {
        $args = func_get_args();
        $where = new Db_Where();
        return call_user_func_array(array(&$where, 'where'), $args);
    }

    public static function setActiveConnection($connection, $id='default'){
        self::$connections[$id] = $connection;
    }

    public static function getActiveConnection($id='default'){
        return isset(self::$connections[$id]) ? self::$connections[$id] : null;
    }

}
