<?php
namespace Db;

class Structure_Key
{
    public bool $isPrimary = false;
    public bool $isUnique = false;
    public ?string $name = null;
    public array $keyColumns = array();
    protected ?Structure $host;

    public function __construct(?Structure $host = null)
    {
        $this->host = $host;
    }

    public function unique()
    {
        $this->isUnique = true;
        return $this;
    }

    public function primary()
    {
        $this->isPrimary = true;
        return $this;
    }

    public function addColumns($names)
    {
        foreach ($names as $name) {
            $this->addColumn($name);
        }
    }

    public function addColumn($name)
    {
        if (is_array($name)) {
            $this->addColumns($name);
            return;
        }

        $this->keyColumns[] = $name;
    }

    public function getColumns()
    {
        return $this->keyColumns;
    }

    public function buildSql()
    {
        $str = '';

        if ($this->isPrimary) {
            $str .= 'PRIMARY KEY';
        } elseif ($this->isUnique) {
            $str .= 'UNIQUE KEY `' . $this->name . '`';
        } else {
            $str .= 'KEY `' . $this->name . '`';
        }

        $str .= " (`" . implode("`,`", $this->keyColumns) . "`)";
        return $str;
    }

}