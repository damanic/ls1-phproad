<?php
namespace Db;

use Db\Sql;
use Phpr\SystemException;
use Phpr\ValidationException;
use Phpr\Inflector;
use Phpr\Strings;

class Helper
{
    protected static $driver;

    public static function listTables()
    {
        $sqlInstance = self::getSqlInstance();
        return $sqlInstance->fetchCol('show tables');
    }

    public static function tableExists($tableName)
    {
        $tables = self::listTables();
        return in_array($tableName, $tables);
    }

    public static function scalar($sql, $bind = array())
    {
        $sqlInstance = self::getSqlInstance();
        return $sqlInstance->fetchOne($sql, $bind);
    }

    public static function scalarArray($sql, $bind = array())
    {
        $values = self::queryArray($sql, $bind);

        $result = array();
        foreach ($values as $value) {
            $keys = array_keys($value);
            if ($keys) {
                $result[] = $value[$keys[0]];
            }
        }

        return $result;
    }

    public static function query($sql, $bind = array())
    {
        $sqlInstance = self::getSqlInstance();
        return $sqlInstance->query($sqlInstance->prepare($sql, $bind));
    }

    public static function fetch_next($resource)
    {
        return self::driver()->fetch($resource);
    }

    public static function free_result($resource)
    {
        self::driver()->free_query_result($resource);
    }

    public static function queryArray($sql, $bind = array())
    {
        $sqlInstance = self::getSqlInstance();
        return $sqlInstance->fetchAll($sql, $bind);
    }

    public static function objectArray($sql, $bind = array())
    {
        $recordSet = self::queryArray($sql, $bind);

        $result = array();
        foreach ($recordSet as $record) {
            $result[] = (object)$record;
        }

        return $result;
    }

    public static function object($sql, $bind = array())
    {
        $result = self::objectArray($sql, $bind);
        if (!count($result)) {
            return null;
        }

        return $result[0];
    }

    public static function getTableStruct($tableName)
    {
        $sqlInstance = self::getSqlInstance();
        $result = $sqlInstance->query($sqlInstance->prepare("SHOW CREATE TABLE `$tableName`"));
        return $sqlInstance->driver()->fetch($result, 1);
    }

    public static function getTableDump($tableName, $fp = null, $separator = ';')
    {
        $sqlInstance = self::getSqlInstance();
        $qr = $sqlInstance->query("SELECT * FROM `$tableName`");

        $result = null;
        $columnNames = null;
        while ($row = $sqlInstance->driver()->fetch($qr)) {
            if ($columnNames === null) {
                $columnNames = '`' . implode('`,`', array_keys($row)) . '`';
            }

            if (!$fp) {
                $result .= "INSERT INTO `$tableName`(" . $columnNames . ") VALUES (";
                $result .= $sqlInstance->quote(array_values($row));
                $result .= ")" . $separator . "\n";
            } else {
                fwrite($fp, "INSERT INTO `$tableName`(" . $columnNames . ") VALUES (");
                fwrite($fp, $sqlInstance->quote(array_values($row)));
                fwrite($fp, ")" . $separator . "\n");
            }
        }

        return $result;
    }

    public static function executeSqlFromFile($file_path, $separator = ';')
    {
        $file_contents = file_get_contents($file_path);
        $file_contents = str_replace("\r\n", "\n", $file_contents);
        $statements = explode($separator."\n", $file_contents);
        $sqlInstance = self::getSqlInstance();

        foreach ($statements as $statement) {
            if (strlen(trim($statement))) {
                $sqlInstance->execute($statement);
            }
        }
    }

    public static function exportSqlToFile($path, $options = array())
    {
        @set_time_limit(600);

        $tables_to_ignore = isset($options['ignore']) ? $options['ignore'] : array();
        $separator = isset($options['separator']) ? $options['separator'] : ';';

        $file_handle = @fopen($path, "w");
        if (!$file_handle) {
            throw new SystemException('Error opening file for writing: '.$path);
        }

        $sqlInstance = self::getSqlInstance();

        try {
            fwrite($file_handle, "SET NAMES utf8".$separator."\n\n");
            $tables = self::listTables();

            foreach ($tables as $table_name) {
                if (in_array($table_name, $tables_to_ignore)) {
                    continue;
                }

                fwrite($file_handle, '# TABLE '.$table_name."\n#\n");
                fwrite($file_handle, 'DROP TABLE IF EXISTS `'.$table_name."`".$separator."\n");
                fwrite($file_handle, self::getTableStruct($table_name).$separator."\n\n");
                self::geTableDump($table_name, $file_handle, $separator);
                $sqlInstance->driver()->reconnect();
            }

            @fclose($file_handle);
            @chmod($path, File::getPermissions());
        } catch (\Exception $ex) {
            @fclose($file_handle);
            throw $ex;
        }
    }


    public static function dropColumn($table_name, $column_name)
    {
        $sqlInstance = self::getSqlInstance();
        return $sqlInstance->query($sqlInstance->prepare('ALTER TABLE `'.$table_name.'` DROP `'.$column_name.'`'));
    }

    public static function renameColumn($table_name, $column_name, $new_column_name)
    {
        $sqlInstance = self::getSqlInstance();
        $table_arr = $sqlInstance->describe_table($table_name);

        if (!isset($table_arr[$column_name])) {
            return false;
        }

        $sql_type = $table_arr[$column_name]['sql_type'];
        return $sqlInstance->query($sqlInstance->prepare('ALTER TABLE `'.$table_name.'` CHANGE COLUMN `'.$column_name.'` `'.$new_column_name.'` '.$sql_type));
    }


    /**
     * Slugifys and caps a string to a safe length
     * Returns a URI code
     * @param $model
     * @param $column_name
     * @param $string
     * @param null $max_length
     * @return mixed
     */
    public static function getUniqueSlugifyValue($model, $column_name, $string, $max_length = null)
    {
        $table_name = $model->table_name;
        $slug = Inflector::slugify($string);
        if ($max_length) {
            $slug = substr($slug, 0, $max_length);
        }

        return self::getUniqueColumnValue($model, $column_name, $slug);
    }

    /**
     * Generates an unique column value
     *
     * @param Db_ActiveRecord $model          A model to generate value for
     * @param string          $column_name    A name of a column
     * @param string          $base_value     A base value of the column. The unique value will be generated
     *                                        by appending the 'copy_1', 'copy_N' string to the base value.

     * @param  bool            $case_sensitive Specifies whether function should perform a case-sensitive search
     * @return string
     */
    public static function getUniqueColumnValue($model, $column_name, $column_value, $case_sensitive = false, $separator = '-')
    {
        $counter = 1;
        $table_name = $model->table_name;
        $column_value = preg_replace('/'.preg_quote($separator).'[0-9]+$/', '', trim($column_value));
        $original_value = $column_value;

        $query = $case_sensitive
            ? "select count(*) from ".$table_name." where ".$column_name."=:value"
            : "select count(*) from ".$table_name." where lower(".$column_name.")=lower(:value)";

        while (self::scalar($query, array('value'=>$column_value))) {
            $counter++;
            $column_value = $original_value.$separator.$counter;
        }

        return $column_value;
    }


    /**
     * Generates a unique column value by adding suffix _copy_1, _copy_2, etc
     * $column_value of "Person" would generate "Person_copy_1"
     * @param $model
     * @param $column_name
     * @param $column_value
     * @param false $case_sensitive
     * @return string
     */
    public static function getUniqueCopyValue($model, $column_name, $column_value, $case_sensitive = false)
    {
        return self::getUniqueColumnValue($model, $column_name, $column_value, $case_sensitive, '_copy_');
    }



    public static function getLastInsertId()
    {
        return self::driver()->get_last_insert_id();
    }

    /**
     * Creates a SQL query string for searching specified fields for specified words or phrases
     *
     * @param  string      $query           Search query
     * @param  string|array $fields          A list of fields to search in. A single field can be specified as a string
     * @param  int         $min_word_length Allows to ignore words with length less than the specified
     * @return string Returns a string
     */
    public static function formatSearchQuery($query, $fields, $min_word_length = null)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        $words = Strings::splitToWords($query);

        $word_queries = array();
        foreach ($words as $word) {
            if (!strlen($word)) {
                continue;
            }

            if ($min_word_length && mb_strlen($word) < $min_word_length) {
                continue;
            }

            $word = trim(mb_strtolower($word));
            $word_queries[] = '%1$s like \'%2$s' . self::escape($word) . '%2$s\'';
        }

        $field_queries = array();
        foreach ($fields as $field) {
            if ($word_queries) {
                $field_queries[] = '(' . sprintf(implode(' and ', $word_queries), $field, '%') . ')';
            }
        }

        if (!$field_queries) {
            return '1=1';
        }

        return '(' . implode(' or ', $field_queries) . ')';
    }

    public static function reset_driver()
    {
        static::$driver = null;
    }

    public static function driver()
    {
        if (!static::$driver) {
            return static::$driver = self::getSqlInstance()->driver();
        }

        return static::$driver;
    }

    public static function escape($str)
    {
        return self::driver()->escape($str);
    }

    //
    // Internals
    //

    protected static function getSqlInstance(): \Db\Sql
    {
        $sqlInstance = Sql::create();
        if (static::$driver) {
            $sqlInstance->assignDriver(static::$driver);
        }
        return $sqlInstance;
    }

    /**
     * @deprecated
     */
    public static function executeSqlScript($filePath, $separator = ';')
    {
        self::executeSqlFromFile($filePath, $separator);
    }


    /**
     * @throws SystemException
     * @deprecated
     */
    public static function createDbDump($path, $options = array())
    {
        self::exportSqlToFile($path, $options);
    }
}
