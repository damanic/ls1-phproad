<?php
namespace Db;

use Db;
use Phpr;
use Phpr\ErrorLog;
use Phpr\SystemException;
use Phpr\DatabaseException;


/**
 * MySQLi database driver.
 * Special thanks to Alan Farquharson <alanf@magmadigital.co.uk> for his contributions
 */
class MySQLiDriver extends Driver_Base
{
    private static $localeSet = false;

    public static function create($config=array())
    {
        return new self($config);
    }

    public function connect()
    {
        if ($this->get_connection()) {
            return;
        }

        try {
            ErrorLog::$disable_db_logging = true;

            // Execute custom connection handlers
            $external_connection = Phpr::$events->fireEvent('core:onBeforeDatabaseConnect', $this);
            $external_connection_found = false;
            foreach ($external_connection as $connection) {
                if ($connection) {
                    $this->set_connection($connection);
                    $external_connection_found = true;
                    break;
                }
            }

            // Connect
            try {
                $host = $this->config['host'];
                $port = isset($this->config['port']) ? $this->config['port'] : null;

                if (Phpr::$config->get('MYSQL_PERSISTENT', true))
                    $host = 'p:'.$host;
                $this->set_connection(mysqli_connect(
                    $host,
                    $this->config['username'],
                    $this->config['password'],
                    $this->config['database'],
                    $port ? $port : ini_get("mysqli.default_port"),
                ));
            } catch (\Exception $ex) {
                throw new DatabaseException('Error connecting to the database.');
            }

            $err = 0;

            if (($this->get_connection() == null) || ($this->get_connection() === false) || ($err = mysqli_errno($this->get_connection()) != 0)) {
                throw new DatabaseException('MySQL connection error: ' . @mysqli_connect_error());
            }

            // Set charset
            if (isset($this->config['locale']) && (trim($this->config['locale']) != '')) {
                mysqli_query($this->get_connection(), "SET NAMES '" . $this->config['locale'] . "'");
                if ($err = mysqli_errno($this->get_connection()) != 0) {
                    throw new DatabaseException('MySQL error setting character set: ' . mysqli_error($this->get_connection()));
                }
            }

            // Set SQL Mode
            mysqli_query($this->get_connection(), 'SET sql_mode=""');

            ErrorLog::$disable_db_logging = false;
        } catch (\Exception $ex) {
            $exception               = new DatabaseException($ex->getMessage());
            $exception->hint_message = 'This problem could be caused by the MySQL connection configuration errors. Review the database connection parameters, and make sure that MySQL server is running.';
            throw $exception;
        }
    }

    public function reconnect()
    {

        if ($this->get_connection()) {
            mysqli_close($this->get_connection());
            $this->set_connection( null);
        }

        $this->connect();
    }


    public function execute($sql)
    {

        parent::execute($sql);
        $this->connect();

        // execute the statement
        $handle = mysqli_query($this->get_connection(), $sql);

        // If error, generate exception
        if ($err = mysqli_errno($this->get_connection()) != 0) {
            $exception               = new DatabaseException('MySQL error executing query: ' . mysqli_error($this->get_connection()));
            $exception->hint_message = 'This problem could be caused by the LSAPP MySQL connection configuration errors. Please log into the LSAPP Configuration Tool and update the database connection parameters. Also please make sure that MySQL server is running.';
            throw $exception;
        }

        return $handle;
    }

    /* Fetch methods */

    public function fetch($result, $col = null)
    {

        parent::fetch($result, $col);

        if ($row = mysqli_fetch_assoc($result)) {
            if ($err = mysqli_errno($this->get_connection()) != 0) {
                throw new DatabaseException('MySQL error fetching data: ' . mysqli_error($this->get_connection()));
            }

            if ($col !== null) {
                if (is_string($col)) {
                    return isset($row[$col]) ? $row[$col] : false;
                } else {
                    $keys = array_keys($row);
                    $col  = array_key_exists($col, $keys) ? $keys[$col] : $keys[0];

//                      $col = array_shift($keys);

                    return isset($row[$col]) ? $row[$col] : false;
                }
            } else {
                return $row;
            }
        }

        return false;
    }

    public function free_query_result($resource)
    {
        if ($resource) {
            mysqli_free_result($resource);
        }
    }

    /* Utility routines */

    public function row_count()
    {
        if (!$this->get_connection()) {
            throw new DatabaseException('MySQL count error - no connection');
        }

        return mysqli_affected_rows($this->get_connection());
    }

    public function last_insert_id($tableName = null, $primaryKey = null)
    {
        if (!$this->get_connection()) {
            throw new DatabaseException('MySQL error last_insert_id - no connection');
        }

        return mysqli_insert_id($this->get_connection());
    }

    public function limit($offset, $count = null)
    {
        if (is_null($count)) {
            return 'LIMIT ' . $offset;
        } else {
            return 'LIMIT ' . $offset . ', ' . $count;
        }
    }

    /**
     * Returns the column descriptions for a table.
     *
     * @return array
     */
    public function describe_table($table)
    {
        if (isset(Db::$describeCache[$table])) {
            return Db::$describeCache[$table];
        } else {
            $sql = 'DESCRIBE ' . $table;
            Phpr::$traceLog->write($sql, 'SQL');
            $result = $this->fetchAll($sql);
            $descr  = array();
            foreach ($result as $key => $val) {
                $descr[$val['Field']] = array(
                    'name'     => $val['Field'],
                    'sql_type' => $val['Type'],
                    'type'     => $this->simplified_type($val['Type']),
                    'notnull'  => (bool)($val['Null'] != 'YES'), // not null is NO or empty, null is YES
                    'default'  => $val['Default'],
                    'primary'  => (strtolower($val['Key']) == 'pri'),
                );
            }

            Db::$describeCache[$table] = $descr;

            return $descr;
        }
    }

    /**
     * Returns the index descriptions for a table.
     * @return array
     */
    public function describeIndex($table)
    {
        $sql = 'SHOW INDEX FROM ' . $table;
        Phpr::$traceLog->write($sql, 'SQL');
        $result = $this->fetchAll($sql);
        $result_array = array();
        foreach ($result as $key => $val)
        {
            $key_name = $val['Key_name'];
            if (array_key_exists($key_name, $result_array)) {
                $result_array[$key_name]['columns'][] = $val['Column_name'];
            } else {

                $result_array[$key_name] = array(
                    'name'     => $key_name,
                    'columns'  => array($val['Column_name']),
                    'unique'   => (bool)($val['Non_unique'] != '1'),
                    'primary'  => (bool)($key_name == 'PRIMARY')
                );
            }
        }

        return $result_array;
    }

    /* Service routines */

    protected function fetchAll($sql)
    {
        $data = array();
        $handle = $this->execute($sql);
        while ($row = $this->fetch($handle)) {
            $data[] = $row;
        }

        return $data;
    }

    protected function simplified_type($sql_type)
    {
        if (preg_match('/([\w]+)(\(\d\))*/i', $sql_type, $matches)) {
            return strtolower($matches[1]);
        }

        return strtolower($sql_type);
    }

    public function quote_metadata_object_name($name)
    {
        $name = trim($name);
        if (strpos('`', $name) === 0) {
            $name = substr($name, 0);
        }

        if (substr($name, -1) == '`') {
            $name = substr($name, 0, -1);
        }

        if (strpos($name, '`') !== false) {
            throw new Phpr_SystemException('Invalid database object name: ' . $name);
        }

        return '`' . $name . '`';
    }

    public function escape($input)
    {
        return mysqli_real_escape_string($this->get_connection(), $input);
    }

    public function create_connection($host, $user, $password)
    {
        return @mysqli_connect($host, $user, $password);
    }

    public function select_db($connection, $db)
    {
        return @mysqli_select_db($connection, $db);
    }

    public function get_last_error_string()
    {
        return mysqli_error();
    }

    public function close_connection($connection)
    {
        return @mysqli_close($connection);
    }

    public function get_last_insert_id()
    {
        return mysqli_insert_id($this->get_connection());
    }


    /**
     * @deprecated
     */
    public function describe_index($table)
    {
        return $this->describeIndex($table);
    }


}
