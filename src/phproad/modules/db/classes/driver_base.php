<?php namespace Db;

use Phpr;
use Db;

/**
 * PHPR Database Driver base class
 */

class Driver_Base
{
    protected $config = array();

    public function __construct($config = array())
    {
        if (empty($config)) {
            $config = $this->get_default_config();
        }
        $this->config = array_merge(
            array(
                'host'     => '',
                'port'     => '',
                'database' => '',
                'username' => '',
                'password' => '',
            ),
            $config
        );
    }

    public function connect()
    {
        if ($this->get_connection()) {
            return;
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

    protected function get_connection()
    {
        return Db::getActiveConnection($this->get_driver_id());
    }

    protected function set_connection($connection)
    {
        Db::setActiveConnection($connection, $this->get_driver_id());
    }

    protected function get_default_config()
    {
        if (Phpr::$config->get('DB_CONFIG_MODE', 'secure') != 'secure') {
            $config = Phpr::$config->get('DB_CONNECTION', array());
        } else {
            $params = SecureSettings::get();
            $config = array(
                'host' => $params['host'],
                'port' => isset($params['port']) ? $params['port'] : null,
                'database' => $params['database'],
                'username' => $params['user'],
                'password' => $params['password'],
                'locale' => 'utf8'
            );
        }
        return $config;
    }

    protected function get_driver_id()
    {
        return get_class($this).md5(serialize($this->config));
    }
}
