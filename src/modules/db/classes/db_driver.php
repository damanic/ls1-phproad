<?php

class Db_Driver
{
    protected $config = array();

    public function connect()
    {
        if (Db::$connection) {
            return;
        }

        // Set defaults
        if (count($this->config) == 0) {
            if (Phpr::$config->get('DB_CONFIG_MODE', 'secure') != 'secure') {
                $config_source = Phpr::$config->get('DB_CONNECTION', array());
            } else {
                $params = Db_SecureSettings::get();
                $config_source = array(
                    'host' => $params['host'],
                    'database' => $params['database'],
                    'username' => $params['user'],
                    'password' => $params['password'],
                    'locale' => 'utf8'
                );
            }

            $this->config = array_merge(
                array(
                    'host' => '',
                    'database' => '',
                    'username' => '',
                    'password' => '',
                ),
                $config_source
            );
        }
    }

    public function reconnect()
    {
    }

    public function execute($sql)
    {
        return 0;
    }

    public function fetch($result, $col = null)
    {
        return false;
    }

    public function free_query_result($resource)
    {
        return null;
    }

    public function row_count()
    {
        return 0;
    }

    public function last_insert_id($tableName = null, $primaryKey = null)
    {
        return -1;
    }

    public function describe_table($table)
    {
        return array();
    }

    public function limit($offset, $count = null)
    {
    }

    public function quote_metadata_object_name($name)
    {
        return $name;
    }

    public function escape($escape)
    {
        return $escape;
    }

    public function create_connection($host, $user, $password)
    {
        return null;
    }

    public function select_db($connection, $db)
    {
        return null;
    }

    public function get_last_error_string()
    {
        return null;
    }

    public function close_connection($connection)
    {
        return null;
    }

    public function get_last_insert_id()
    {
        return null;
    }
}
