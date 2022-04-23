<?php
namespace Db;

use Phpr;
use Phpr\SystemException;
use Db\Helper as DbHelper;

/**
 * PHPR Database Structure Class
 *
 * Example usage:
 *
 *   $users = Db_Structure::table('users_table');
 *   $users->primaryKey('id');
 *   $users->column('username', db_varchar, 100)->defaults('funnyman');
 *   $users->column('email', db_varchar, 100);
 *   $users->column('group_id', db_number)->index();
 *   $users->addKey('usermail', array('username', 'email'))->unique();
 *   $users->footprints();
 *   $users->save();
 *
 * Resulting SQL:
 *
 *   CREATE TABLE `users_table` (
 *     `id` int(11) NOT NULL AUTO_INCREMENT,
 *     `username` varchar(100) DEFAULT 'funnyman',
 *     `email` varchar(100),
 *     `group_id` int(11),
 *     `created_user_id` int(11),
 *     `updated_user_id` int(11),
 *     `created_at` datetime,
 *     `updated_at` datetime,
 *     `deleted_at` datetime,
 *     UNIQUE KEY `usermail` (`username`,`email`),
 *     PRIMARY KEY (`id`),
 *     KEY `group_id` (`group_id`)
 *   ) DEFAULT CHARSET=utf8;
 *
 * Subsequent usage:
 *
 *   $users = Db_Structure::table('users_table');
 *   $users->primaryKey('id');
 *   $users->column('username', db_varchar, 125)->defaults('superman');
 *   $users->column('email', db_varchar, 100);
 *   $users->column('password', db_varchar, 100);
 *   $users->column('group_id', db_number);
 *   $users->footprints();
 *   $users->save();
 *
 * Resulting SQL:
 *
 *    ALTER TABLE `users_table`
 *      CHANGE `username` `username` varchar(125) DEFAULT 'superman',
 *      ADD `password` varchar(100);
 *
 *    ALTER TABLE `users_table` DROP INDEX `usermail`;
 *    ALTER TABLE `users_table` DROP INDEX `group_id`;
 *
 * Exending another modules structure:
 *
 * public function subscribe_events() {
 *     Phpr::$events->add_event('user:on_extend_users_table_table', $this, 'extend_users_table');
 * }
 *
 * public function extend_users_table($table) {
 *     $table->column('description', db_text);
 * }
 *
 * Manually update a module:
 *
 * Db\Update_Manager::applyDbStructure(PATH_APP, 'user');
 * Db\Structure::saveAll();
 *
 */
class Structure
{
    public const DEBUG_MODE = false;
    public const PRIMARY_KEY = 'PRIMARY';

    public static ?string $moduleId = null; // Module identifier
    public static array $modules = array(); // Module tables
    public bool $captureOnly = false; // Perform a dry run
    public bool $safeMode = false; // Only create, don't delete

    protected array $keys = array();
    protected array $columns = array();

    protected ?string $charset;
    protected ?string $engine;
    protected ?string $tableName;
    protected bool $tableExists;

    private $builtSql = '';

    public function __construct()
    {
        if (!class_exists('\Db\ActiveRecord')) {
            Phpr::$classLoader->load('\Db\ActiveRecord');
        }
        $this->reset();
    }

    public static function extendTable($moduleId, $name)
    {
        $prev_module_id = self::$moduleId;
        self::$moduleId = $moduleId;
        $table = self::table($name);
        self::$moduleId = $prev_module_id;
        return $table;
    }

    public static function saveAll()
    {
        foreach (self::$modules as $moduleId => $tables) {
            foreach ($tables as $tableName => $table) {
                self::$moduleId = $moduleId;
                $table->save();
                self::$moduleId = null;
            }
        }
    }

    public static function table($name)
    {
        if (!isset(self::$modules[self::$moduleId])) {
            self::$modules[self::$moduleId] = array();
        }

        if (!isset(self::$modules[self::$moduleId][$name])) {
            $obj = new self();
            $obj->tableName = $name;

            self::$modules[self::$moduleId][$name] = $obj;
        }

        return self::$modules[self::$moduleId][$name];
    }

    public function executeSql($sql)
    {
        $this->builtSql .= $sql = $sql . ';' . PHP_EOL;
        if (self::DEBUG_MODE) {
            Phpr::$traceLog->write($sql);
        } elseif (!$this->captureOnly) {
            DbHelper::query($sql);
        }
    }

    public function reset()
    {
        $this->charset = 'utf8';
        $this->engine = ''; //use environment default
        $this->tableName = '';
        $this->keys = array();
        $this->columns = array();
        $this->builtSql = '';
    }

    //
    // Primary Keys
    //

    public function primaryKeys($columns)
    {
        if (is_string($columns)) {
            $columns = func_get_args();
        }

        foreach ($columns as $column) {
            $this->column($column, db_number)->notNull();
        }

        // Add primary key
        return $this->addKey(null, $columns)->primary();
    }

    public function primaryKey($column, $type = db_number, $size = null)
    {
        if (is_array($column)) {
            return $this->primaryKeys($column, $type, $size);
        }

        $this->addKey(null, $column)->primary();
        return $this->column($column, $type, $size)->notNull();
    }

    //
    // Regular keys
    //

    public function addKey($name, $columns)
    {
        if (!$name) {
            $name = self::PRIMARY_KEY;
        }

        if (is_string($columns)) {
            $columns = array($columns);
        }

        $existing_key = $this->findKey($name);
        if ($existing_key) {
            $existing_key->addColumns($columns);
            return $existing_key;
        } else {
            $obj = new Structure_Key($this);
            $obj->name = $name;
            $obj->addColumns($columns);
            return $this->keys[$name] = $obj;
        }
    }

    public function findKey($name)
    {
        return (isset($this->keys[$name])) ? $this->keys[$name] : false;
    }

    //
    // Columns
    //

    public function column($name, $type, $size = null)
    {
        $obj = new Structure_Column($this);
        $obj->name = $name;
        $obj->type = $type = $this->getDbType($type);

        if (is_array($size) && count($size) > 1) {
            $obj->length = $size[0];
            $obj->precision = $size[1];
        } elseif ($size !== null) {
            $obj->length = $size;
        }

        if (strpos($type, '(') && strpos($type, ')')) {
            $this->length = $this->getTypeLength($type);
            $this->precision = $this->getTypePrecision($type);
        }

        return $this->columns[$name] = $obj;
    }

    public function findColumn($name)
    {
        return (isset($this->columns[$name])) ? $this->columns[$name] : false;
    }

    //
    // Automatic Footprints
    //

    public function footprints($include_user = true)
    {
        if ($include_user) {
            $this->column('created_user_id', db_number)->index();
            $this->column('updated_user_id', db_number)->index();
        }
        $this->column('created_at', db_datetime);
        $this->column('updated_at', db_datetime);
    }

    //
    // Business Logic
    //

    protected function processKeys()
    {
        // Make single integer primary keys auto increment
        //
        if ($primary_key = $this->findKey(self::PRIMARY_KEY)) {
            $key_columns = $primary_key->getColumns();
            if (count($key_columns) == 1) {
                if ($col = $this->findColumn($key_columns[0])) {
                    if ($col->type == $this->getDbType(db_number)) {
                        $col->autoIncrement();
                    }
                }
            }
        }
    }

    public function save()
    {
        if (!strlen($this->tableName)) {
            throw new SystemException('You must specify a table name before calling commit()');
        }

        if (!count($this->columns)) {
            throw new SystemException('You must provide at least one column before calling commit()');
        }

        $moduleId = self::$moduleId ?: 'db';
        $event_name = $moduleId . ':on_extend_' . $this->tableName . '_table';
        Phpr::$events->fire_event($event_name, $this);

        $this->processKeys();

        if (DbHelper::tableExists($this->tableName)) {
            $this->commitModify();
        } else {
            $this->commitCreate();
        }
    }

    public function buildSql()
    {
        $this->captureOnly = true;
        $this->save();
        $sql = $this->builtSql;
        $this->captureOnly = false;
        return $sql;
    }

    public function commitModify()
    {
        // Column management
        //

        $col_sql = array();
        $alter_prefix = 'ALTER TABLE `' . $this->tableName . '` ' . PHP_EOL;
        $existing_columns = $this->getExistingColumns();

        // Remove columns not listed
        if (!$this->safeMode) {
            $columns_to_remove = array_diff(array_keys($existing_columns), array_keys($this->columns));
            foreach ($columns_to_remove as $column) {
                $col_sql[] = 'DROP COLUMN `' . $column . '`';
            }
        }

        // Add non-existing columns
        foreach ($this->columns as $column_name => $column) {
            if (array_key_exists($column_name, $existing_columns)) {
                $existing_column = $existing_columns[$column_name];
                $existing_column_definition = $existing_column->buildSql();
                $column_definition = $column->buildSql();

                // Debug
                if (self::DEBUG_MODE && $column_definition != $existing_column_definition) {
                    Phpr::$traceLog->write('----------VS-------------');
                    Phpr::$traceLog->write('NEW: ' . $column_definition);
                    Phpr::$traceLog->write('OLD: ' . $existing_column_definition);
                    Phpr::$traceLog->write('-------------------------');
                }

                if ($column_definition != $existing_column_definition) {
                    $col_sql[] = 'CHANGE `' . $column_name . '` ' . $column->buildSql();
                }
            } else {
                $col_sql[] = 'ADD ' . $column->buildSql();
            }
        }

        // Execute
        if (count($col_sql)) {
            $col_sql_string = $alter_prefix . implode(',' . PHP_EOL, $col_sql);
            $this->executeSql($col_sql_string);
        }

        // Index / Key management
        //

        $key_sql = array();
        $existing_index = $this->getExistingKeys();

        // Remove indexes not listed
        if (!$this->safeMode) {
            $keys_to_remove = array_diff(array_keys($existing_index), array_keys($this->keys));
            foreach ($keys_to_remove as $key_name) {
                $key_sql[] = $alter_prefix . 'DROP INDEX `' . $key_name . '`';
            }
        }

        // Add non-existing indexes
        foreach ($this->keys as $key_name => $key_obj) {
            if (array_key_exists($key_name, $existing_index)) {
                $existing_key = $existing_index[$key_name];
                $existing_key_definition = $existing_key->buildSql();
                $key_definition = $key_obj->buildSql();

                if ($key_definition != $existing_key_definition) {
                    $key_sql[] = $alter_prefix . 'DROP INDEX ' . $key_name;
                    $key_sql[] = $alter_prefix . 'ADD ' . $key_obj->buildSql();
                }
            } else {
                $key_sql[] = $alter_prefix . 'ADD ' . $key_obj->buildSql();
            }
        }

        // Execute
        foreach ($key_sql as $sql) {
            $this->executeSql($sql);
        }
    }

    public function commitCreate()
    {
        $sql = array();
        $engine = $this->engine ? 'ENGINE=' . $this->engine : null;
        $create_tmpl = ''
            . 'CREATE TABLE `' . $this->tableName . '` (' . PHP_EOL
            . '%s' . PHP_EOL
            . ') ' . $engine . ' DEFAULT CHARSET=' . $this->charset . ';';

        foreach ($this->columns as $column) {
            $sql[] = $column->buildSql();
        }

        foreach ($this->keys as $key) {
            $sql[] = $key->buildSql();
        }

        $sql_string = sprintf($create_tmpl, implode(',' . PHP_EOL, $sql));
        $this->executeSql($sql_string);
    }

    //
    // Helpers
    //

    private function getDbType($type)
    {
        if (strpos($type, '(') && strpos($type, ')')) {
            return $this->simplifiedType($type);
        }

        return $this->columnToDbType($type);
    }

    private function columnToDbType($type)
    {
        switch ($type) {
            case db_number:
                return 'int';
            case db_bool:
                return 'tinyint';
            case db_varchar:
                return 'varchar';
            case db_datetime:
                return 'datetime';
            case db_float:
                return 'decimal';
            case db_date:
                return 'date';
            case db_time:
                return 'time';
            case db_text:
                return 'text';
            default:
                return $type;
        }
    }

    private function simplifiedType($sql_type)
    {
        $sql_type = strtolower($sql_type);

        preg_match_all('/(\w+)\((\d+)(?:,*)(\d*)\)/i', $sql_type, $matches);

        if (!isset($matches[1][0])) {
            return $sql_type;
        }

        return $matches[1][0];
    }

    private function getTypeLength($sql_type)
    {
        preg_match_all('/(\w+)\((\d+)(?:,*)(\d*)\)/i', $sql_type, $matches);

        if (!isset($matches[2][0])) {
            return null;
        }

        return $matches[2][0];
    }

    private function getTypePrecision($sql_type)
    {
        preg_match_all('/(\w+)\((\d+)(?:,*)(\d*)\)/i', $sql_type, $matches);

        if (!isset($matches[3][0])) {
            return null;
        }

        return $matches[3][0];
    }

    private function getTypeValues($sql_type)
    {
        preg_match_all('/(\w+)(\(\d\))*/i', $sql_type, $matches);
        return $matches[0];
    }

    private function getExistingKeys()
    {
        $existing_keys = array();
        $key_arr = Sql::create()->describeIndex($this->tableName);
        foreach ($key_arr as $key) {
            $obj = new Structure_Key();
            $obj->name = $name = $key['name'];
            $obj->keyColumns = $key['columns'];

            if ($key['primary']) {
                $obj->primary();
            }

            if ($key['unique']) {
                $obj->unique();
            }

            $existing_keys[$name] = $obj;
        }

        return $existing_keys;
    }

    private function getExistingColumns()
    {
        $existing_columns = array();
        $table_arr = Sql::create()->describe_table($this->tableName);
        $primary_arr = array();

        foreach ($table_arr as $col) {
            $obj = new Structure_Column($this);
            $sql_type = $col['sql_type'];
            $obj->name = $name = $col['name'];
            $obj->type = $type = $col['type'];

            if (strlen($col['default'])) {
                $obj->defaults($col['default']);
            }

            if ($col['notnull'] === true) {
                $obj->notNull();
            }

            if ($col['primary'] === true) {
                $primary_arr[] = $obj;
            }

            if ($type == 'enum') {
                $obj->enumValues(array_slice($this->getTypeValues($sql_type), 1));
            } else {
                $obj->length = $this->getTypeLength($sql_type);
                $obj->precision = $this->getTypePrecision($sql_type);
            }

            $existing_columns[$name] = $obj;
        }

        // Single PK, set auto increment
        $single_primary_key = (count($primary_arr) == 1);
        foreach ($primary_arr as $obj) {
            if ($obj->type != $this->getDbType(db_number)) {
                continue;
            }

            $obj->autoIncrement($single_primary_key);
        }

        return $existing_columns;
    }
}
