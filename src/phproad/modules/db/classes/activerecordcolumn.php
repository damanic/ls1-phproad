<?php namespace Db;


class ActiveRecordColumn
{
    public $name = '';
    public $type = 'text';
    public $length = null;
    public $calculated;
    public $custom;
    public $sql_type;

    public function __construct($columnInfo)
    {
        $options = array();

        $this->name = $columnInfo['name'];
        $this->type = isset($columnInfo['type']) ? $columnInfo['type'] : 'varchar';
        $this->calculated = isset($columnInfo['calculated']) ? $columnInfo['calculated'] : false;
        $this->custom = isset($columnInfo['custom']) ? $columnInfo['custom'] : false;

        if (isset($columnInfo['sql_type'])) {
            $this->sql_type = $columnInfo['sql_type'];
            $matches = array();
            if (preg_match('/^varchar\(([0-9]*)\)$/', $columnInfo['sql_type'], $matches)) {
                $this->length = $matches[1];
            }
        }

        switch ($this->type) {
        case 'char':
        case 'varchar':
            $this->type = db_varchar;
            break;
        case 'int':
        case 'smallint':
        case 'mediumint':
        case 'bigint':
            $this->type = db_number;
            break;
        case 'double':
        case 'decimal':
        case 'float':
            $this->type = db_float;
            break;
        case 'bool':
        case 'tinyint':
            $this->type = db_bool;
            break;
        case 'datetime':
            $this->type = db_datetime;
            break;
        case 'date':
            $this->type = db_date;
            break;
        case 'time':
            $this->type = db_time;
            break;
        }
    }
}
