<?php

/**
 * MySQLi database driver.
 * Special thanks to Alan Farquharson <alanf@magmadigital.co.uk> for his contributions
 */
class Db_MySQLiDriver extends Db_Driver 
{

	private static $locale_set = false;

	public static function create() 
	{
		return new self();
	}

	public function connect()
	{
		if (Db::$connection) {
			return;
		}

		try {
			Phpr_ErrorLog::$disable_db_logging = true;

			// Load the configuration
			parent::connect();

			// Execute custom connection handlers
			$external_connection       = Backend::$events->fireEvent('core:onBeforeDatabaseConnect', $this);
			$external_connection_found = false;
			foreach ($external_connection as $connection) {
				if ($connection) {
					Db::$connection            = $connection;
					$external_connection_found = true;
					break;
				}
			}

			if (!Db::$connection) {
				// Connect
				try {
					$host = $this->config['host'];

					if (Phpr::$config->get('MYSQL_PERSISTENT', true))
						$host = 'p:'.$host;

					Db::$connection = mysqli_connect($host, $this->config['username'], $this->config['password'], $this->config['database'], isset($this->config['flags']) ? $this->config['flags'] : 0);
				} catch (Exception $ex) {
					throw new Phpr_DatabaseException('Error connecting to the database.');
				}
			}

			$err = 0;

			if ((Db::$connection == null) || (Db::$connection === false) || ($err = mysqli_errno(Db::$connection) != 0)) {
				throw new Phpr_DatabaseException('MySQL connection error: ' . @mysql_error());
			}

			// Set charset
			if (isset($this->config['locale']) && (trim($this->config['locale']) != '')) {
				mysqli_query(Db::$connection, "SET NAMES '" . $this->config['locale'] . "'");
				if ($err = mysqli_errno(Db::$connection) != 0) {
					throw new Phpr_DatabaseException('MySQL error setting character set: ' . mysqli_error(Db::$connection));
				}
			}

			// Set SQL Mode
			mysqli_query(Db::$connection, 'SET sql_mode=""');

			Phpr_ErrorLog::$disable_db_logging = false;
		} catch (Exception $ex) {
			$exception               = new Phpr_DatabaseException($ex->getMessage());
			$exception->hint_message = 'This problem could be caused by the LemonStand MySQL connection configuration errors. Please log into the LemonStand Configuration Tool and update the database connection parameters. Also please make sure that MySQL server is running.';
			throw $exception;
		}
	}

	public function reconnect()
	{

		if (Db::$connection) {
			mysqli_close(Db::$connection);
			Db::$connection = null;
		}

		$this->connect();
	}

	public function execute($sql)
	{

		parent::execute($sql);
		$this->connect();

		// execute the statement
		$handle = mysqli_query(Db::$connection, $sql);

		// If error, generate exception
		if ($err = mysqli_errno(Db::$connection) != 0) {
			$exception               = new Phpr_DatabaseException('MySQL error executing query: ' . mysqli_error(Db::$connection));
			$exception->hint_message = 'This problem could be caused by the LemonStand MySQL connection configuration errors. Please log into the LemonStand Configuration Tool and update the database connection parameters. Also please make sure that MySQL server is running.';
			throw $exception;
		}

		return $handle;
	}

	/* Fetch methods */

	public function fetch($result, $col = null)
	{

		parent::fetch($result, $col);

		if ($row = mysqli_fetch_assoc($result)) {
			if ($err = mysqli_errno(Db::$connection) != 0) {
				throw new Phpr_DatabaseException('MySQL error fetching data: ' . mysqli_error(Db::$connection));
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
		if (!Db::$connection) {
			throw new Phpr_DatabaseException('MySQL count error - no connection');
		}

		return mysqli_affected_rows(Db::$connection);
	}

	public function last_insert_id($tableName = null, $primaryKey = null)
	{
		if (!Db::$connection) {
			throw new Phpr_DatabaseException('MySQL error last_insert_id - no connection');
		}

		return mysqli_insert_id(Db::$connection);
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

	/* Service routines */

	protected function fetchAll($sql)
	{
		$data   = array();
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
		return mysqli_real_escape_string(Db::$connection, $input);
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
		return mysqli_insert_id(Db::$connection);
	}
}

?>
