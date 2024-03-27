<?php

class Db_Base extends Phpr_Validatable
{
    protected $_driver = null;

    public function prepare($sql, $bind = null)
    {
        // split into text and params
        $sqlSplit = preg_split(
            '/(\?|\:[a-z_0-9]+)/i',
            $sql,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (!isset($bind) || !is_array($bind)) {
            $bind = func_get_args();
            array_shift($bind);
        }

        // map params
        $index = 0;

        $sql = array();

        foreach ($sqlSplit as $val) {
            if ($val[0] == ':') {
                if (array_key_exists(substr($val, 1), $bind)) {
                    $val = $bind[substr($val, 1)];
                    $val = $this->quote($val);
                }
            } elseif ($val[0] == '?') {
                if (array_key_exists($index, $bind)) {
                    $val = $bind[$index];
                    $val = $this->quote($val);
                    $index++;
                }
            }

            $sql[] = $val;
        }

        return implode('', $sql);
    }

    public function quote($value)
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                $item = $this->quote($item);
            }

            return implode(', ', $value);
        }

        if ($value instanceof Phpr_DateTime) {
            $value = $value->toSqlDateTime();
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_null($value) || !strlen($value)) {
            return 'NULL';
        }

        $value = (string) $value;
        if ($this->_driver) {
            $value = $this->_driver->escape($value);
        } else {
            $search = ["\\", "\0", "\n", "\r", "'", "\"", "\x1a"];
   	        $replace = ["\\\\", "\\0", "\\n", "\\r", "\\'", "\\\"", "\\Z"];
	        $value = str_replace($search, $replace, $value);
        }

        return "'" . $value . "'";
    }
}
