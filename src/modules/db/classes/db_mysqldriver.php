<?php

/**
 * @deprecated Use Db_MySQLiDriver
 */
class Db_MySQLDriver extends Db_MySQLiDriver
{
    public static function create()
    {
        throw new Phpr_DatabaseException('The MYSQL driver is no longer supported. Use MYSQLi');
    }
}
