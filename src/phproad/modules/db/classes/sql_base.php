<?php


namespace Db;

use Phpr\ValidateExtension;
use Phpr\DateTime as PhprDateTime;

class Sql_Base extends ValidateExtension
{
    /**
     * Wraps supplied value(s) with quotes.
     * @param mixed $value Single or array of values to wrap
     * @return string
     */
    public function quote($value)
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                $item = $this->quote($item);
            }

            return implode(', ', $value);
        }

        if ($value instanceof PhprDateTime) {
            $value = $value->toSqlDateTime();
        }

        if (!strlen($value)) {
            return 'null';
        }

        $result = str_replace("\\", '\\\\', $value);
        $result = str_replace("'", "\'", $result);
        return "'" . $result . "'";
    }

    /**
     * Passes parameters to an SQL query. Parameters use a colon character
     * before the name. Example :user_id
     * @param string $sql Query with parameters
     * @param array $params Key and values to parse in to query
     * @return string
     */

    public function prepare($sql, $params = null)
    {
        if (!is_string($sql)) {
            throw new \Exception('First parameter of prepare() must be a string, ' . gettype($sql) . ' was passed instead.');
        }

        // Attempt to build parameters from method arguments
        if (!isset($params) || !is_array($params)) {
            $params = func_get_args();
            array_shift($params);
        }

        // Capture query and parameters
        $sqlSplit = preg_split(
            '/(\?|\:[a-z_0-9]+)/i',
            $sql,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );


        // Parse in parameters
        $index = 0;
        $sql = array();

        foreach ($sqlSplit as $val) {
            if ($val[0] == ':') {
                if (array_key_exists(substr($val, 1), $params)) {
                    $val = $params[substr($val, 1)];
                    $val = $this->quote($val);
                }
            } elseif ($val[0] == '?') {
                if (array_key_exists($index, $params)) {
                    $val = $params[$index];
                    $val = $this->quote($val);
                    $index++;
                }
            }

            $sql[] = $val;
        }

        return implode('', $sql);
    }
}
