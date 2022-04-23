<?php
namespace Db;

class Structure_Column
{
    public static $defaultLength = array(
        'int' => 11,
        'varchar' => 255,
        'decimal' => 15,
        'float' => 10,
        'tinyint' => 4
    );

    public static $defaultPrecision = array(
        'decimal' => 2,
        'float' => 6
    );

    private Structure $host;

    public string $name;
    public string $type;
    public $length;
    public $precision;
    public array $enumeration = array();
    public $defaultValue;
    public bool $isUnique = false;
    public bool $unsigned = false;
    public bool $allowNull = true;
    public bool $autoIncrement = false;

    public function __construct(Structure $host = null)
    {
        $this->host = $host;
    }

    public function primary()
    {
        if (!$this->host) {
            return false;
        }

        return $this->host->addKey(null, $this->name);
    }

    public function index(?string $name = null)
    {
        if (!$this->host) {
            return false;
        }

        if (!$name) {
            $name = $this->name;
        }

        return $this->host->addKey($name, $this->name);
    }

    public function defaults($value)
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function enumValues(array $values)
    {
        $this->enumeration = $values;
    }

    public function notNull(bool $flag = false)
    {
        $this->allowNull = $flag;
        return $this;
    }

    public function autoIncrement(bool $flag = true)
    {
        $this->autoIncrement = $flag;
        return $this;
    }

    public function buildSql()
    {
        $this->setDefaults();

        $str = '`' . $this->name . '` ' . $this->type;

        if ($this->length && $this->precision) {
            $str .= '(' . $this->length . ',' . $this->precision . ')';
        } elseif ($this->length) {
            $str .= '(' . $this->length . ')';
        }
        
        if ($this->unsigned) {
            $str .= ' UNSIGNED';
        }

        if ($this->enumeration) {
            $str .= "('" . implode("','", $this->enumeration) . "')";
        }

        if (!$this->allowNull) {
            $str .= ' NOT NULL';
        }

        if (strlen($this->defaultValue)) {
            $str .= ' DEFAULT ' . $this->prepareValue($this->defaultValue);
        }

        if ($this->autoIncrement) {
            $str .= ' AUTO_INCREMENT';
        }

        return $str;
    }

    private function setDefaults()
    {
        if (!strlen($this->precision) && isset(self::$defaultPrecision[$this->type])) {
            $this->precision = self::$defaultPrecision[$this->type];
        }

        if (!strlen($this->length) && isset(self::$defaultLength[$this->type])) {
            $this->length = self::$defaultLength[$this->type];
        }
    }

    private function prepareValue($value)
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_numeric($value)) {
                return $value;
        } else {
            return "'" . str_replace("'", "''", $value) . "'";
        }
    }

}
