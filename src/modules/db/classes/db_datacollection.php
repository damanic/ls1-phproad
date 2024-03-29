<?php

/**
 * Represents a collection of ActiveRecord objects.
 * Objects of this class are returned by {@link Db_ActiveRecord::find_all()} method
 * and some other methods and {@link http://lemonstand.com/docs/creating_data_relations/ relations}.
 * @documentable
 * @author LemonStand eCommerce Inc.
 * @package core.classes
 */
class Db_DataCollection implements ArrayAccess, IteratorAggregate, Countable
{
    public $objectArray = array();

    /**
     * @var Db_ActiveRecord A reference to the parent model.
     * This field is set only for data collections created by relations.
     * @documentable.
     */
    public $parent = null;
    public $relation = '';

    /**
     * Constructor
     * Create collection from array if passed
     *
     * @param mixed[] $array
     */
    public function __construct($array = null)
    {
        if (is_array($array)) {
            $this->objectArray = $array;
        }
    }

    /**
     * These are the required iterator functions
     */

    public function offsetExists($offset)
    {
        if (isset($this->objectArray[$offset])) {
            return true;
        } else {
            return false;
        }
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->objectArray[$offset];
        } else {
            return (false);
        }
    }

    public function offsetSet($offset, $value)
    {
        if (!is_null($this->parent) && ($this->parent instanceof Db_ActiveRecord)) {
            $this->parent->bind($this->relation, $value);
        }

        if ($offset) {
            $this->objectArray[$offset] = $value;
        } else {
            $this->objectArray[] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->objectArray[$offset]);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->objectArray);
    }

    /**
     * End required iterator functions
     */

    /**
     * Returns a first element in the collection.
     * @documentable
     * @return Db_ActiveRecord Returns the model object or NULL.
     */
    public function first()
    {
        if (count($this->objectArray) > 0) {
            return $this->objectArray[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the number of records in the collection.
     * @documentable
     * @return integer
     */
    public function count()
    {
        return count($this->objectArray);
    }

    public function position(&$object)
    {
        return array_search($object, $this->objectArray);
    }

    public function limit($count)
    {
        $limit = 0;
        $limited = array();

        foreach ($this->objectArray as $item) {
            if ($limit++ >= $count) {
                break;
            }
            $limited[] = $item;
        }
        return new Db_DataCollection($limited);
    }

    public function skip($count)
    {
        $skipped = array();
        foreach ($this->objectArray as $item) {
            if ($count-- > 0) {
                continue;
            }

            $skipped[] = $item;
        }

        return new Db_DataCollection($skipped);
    }

    public function except($value, $key = 'id')
    {
        return $this->exclude(array($value), $key);
    }

    /**
     * Find a single record by ID
     * @param        $value
     * @param string $field
     *
     * @return mixed|null
     */
    public function find($value, $field = 'id')
    {
        return $this->find_by($field, $value);
    }


    /**
     * Finds first in collection that has with field equal to the given value
     *
     * @param $field
     * @param $value
     *
     * @return mixed|null
     */
    public function find_by($field, $value, $strict = false)
    {
        foreach ($this->objectArray as $object) {
            if ($strict) {
                if ($object->{$field} === $value) {
                    return $object;
                }
            } else {
                if ($object->{$field} == $value) {
                    return $object;
                }
            }
        }
        return null;
    }

    /**
     * Finds all records in collection where field equals given value
     * @param mixed $value The value to match
     * @param string $field The field to evaluate
     * @param bool $strict Strict comparison off/on
     *
     * @return array
     */
    public function find_all_by($field, $value, $strict = false)
    {
        $results = array();
        foreach ($this->objectArray as $object) {
            if ($strict) {
                if ($object->{$field} === $value) {
                    $results[] = $object;
                }
            } else {
                if ($object->{$field} == $value) {
                    $results[] = $object;
                }
            }
        }
        return $results;
    }


    /**
     * Convert the collection to an array.
     * This method can return a list of model objects or their fields, depending on the parameter values.
     * If the <em>$field</em> parameter is NULL, the method returns an array of model objects.
     * Alternatively you can specify the column name and the method will return a list of this column values.
     *
     * The <em>$key</em> field determines which values should be used as the array keys. By default it matches
     * the record index in the collection. If a field name passed, the field value is used as the array keys.
     *
     * <pre>
     * $array = $collection->as_array(); // Array of model objects
     * $array = $collection->as_array('name'); // Array of 'name' column values
     * $array = $collection->as_array('name', 'id'); // Array keys match the 'id' column values.
     * </pre>
     * @documentable
     * @param string $field Specifies a name of field which values should be used as array element values.
     * @param string $key_field Specifies a of the field which values should be used as array element keys.
     * @return array Returns an array of model objects or scalar values.
     */
    public function as_array($field = null, $key_field = null)
    {
        if ($field === null && $key_field === null) {
            return $this->objectArray;
        }

        $result = array();
        foreach ($this->objectArray as $index => $item) {
            $value = $field === null ? $item : $item->$field;
            $key = $key_field === null ? $index : $item->$key_field;

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Returns an array with keys matching records primary keys
     * @return mixed[]
     */
    public function as_mapped_array()
    {
        if (!count($this->objectArray)) {
            return $this->objectArray;
        }

        return $this->as_array(null, $this->objectArray[0]->primary_key);
    }

    /**
     * Returns this collection.
     * This method simplifies front-end coding.
     * @documentable
     * @return Db_DataCollection Returns a collection
     */
    public function collection()
    {
        return $this;
    }

    /**
     * Convert collection to associative array
     *
     * @param string|mixed[] $field optional
     * @param string $key optional
     * @param string $subkey optional
     * @return mixed[]
     */
    public function as_dict($field = '', $key = '', $subkey = '')
    {
        if ($field == '') {
            return $this->objectArray;
        }

        $result = array();
        foreach ($this->objectArray as $item) {
            $k = $key;
            if ($k == '') {
                $k = $item->primary_key;
            }

            if (is_string($field)) {
                if ($subkey != '') {
                    if (!isset($result[$item->$k])) {
                        $result[$item->$k] = array();
                    }

                    $result[$item->$k][$item->$subkey] = $item->$field;
                } else {
                    $result[$item->$k] = $item->$field;
                }
            } elseif (is_array($field)) {
                $res = array();
                foreach ($field as $model_field) {
                    $res[$model_field] = $item->$model_field;
                }

                if (!isset($result[$item->$k])) {
                    $result[$item->$k] = array();
                }

                if ($subkey == '') {
                    $result[$item->$k][] = $res;
                } else {
                    $result[$item->$k][$item->$subkey] = $res;
                }
            } else {
                continue;
            }
        }
        return $result;
    }

    public function exclude($values, $key = 'id')
    {
        $result = array();
        foreach ($this->objectArray as $item) {
            if (!in_array($item->{$key}, $values)) {
                $result[] = $item;
            }
        }

        $this->objectArray = $result;
        return $this;
    }

    public function has($value, $field)
    {
        $items = $this->as_array($field);
        return in_array($value, $items);
    }

    /**
     * Magic method: get properties from first object in collection
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        switch ($key) {
            case "first":
                return $this->first();
            case "count":
                return $this->count();
        }

        if (count($this->objectArray) > 0) {
            return @$this->objectArray[0]->$key;
        }

        return null;
    }

    /**
     * Magic method: call methods from first object in collection
     *
     * @param string $name
     * @param mixed[] $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (count($this->objectArray) > 0) {
            return call_user_func_array(array(&$this->objectArray[0], $name), $arguments);
        }

        return null;
    }

    /**
     * Adds an object to the collection.
     * This method is applicable only when the collection is created by a model's relation.
     * The model should be saved in order the relation changes to apply.
     * @documentable
     * @param Db_ActiveRecord $record Specifies a record to add.
     * @param string $deferred_session_key Optional deferred session key.
     * If the key is specified, it should be used in {@link Db_ActiveRecord::save()} method call.
     */
    public function add($record, $deferred_session_key = null)
    {
        if (is_null($this->parent) || !($this->parent instanceof Db_ActiveRecord)) {
            return;
        }
        $this->parent->bind($this->relation, $record, $deferred_session_key);
    }

    /**
     * Deletes an object from the collection
     * This method is applicable only when the collection is created by a model's relation.
     * The model should be saved in order the relation changes to apply.
     * @documentable
     * @param Db_ActiveRecord $record Specifies a record to remove.
     * @param string $deferred_session_key Optional deferred session key.
     * If the key is specified, it should be used in {@link Db_ActiveRecord::save()} method call.
     */
    public function delete($record, $deferred_session_key = null)
    {
        if (is_null($this->parent) || !($this->parent instanceof Db_ActiveRecord)) {
            return;
        }

        $this->parent->unbind($this->relation, $record, $deferred_session_key);
    }

    /**
     * Removes all objects from the collection
     * This method is applicable only when the collection is created by a model's relation.
     * The model should be saved in order the relation changes to apply.
     * @documentable
     * @param string $deferred_session_key Optional deferred session key.
     * If the key is specified, it should be used in {@link Db_ActiveRecord::save()} method call.
     */
    public function clear($deferred_session_key = null)
    {
        if (is_null($this->parent) || !($this->parent instanceof Db_ActiveRecord)) {
            return;
        }

        $this->parent->unbind_all($this->relation, $deferred_session_key);
        $this->objectArray = array();
    }

    public function item($key)
    {
        if (isset($this->objectArray[$key])) {
            return $this->objectArray[$key];
        }

        return null;
    }

    public function total()
    {
        if ($this->parent == null) {
            return count($this);
        }

        if (!isset($this->_total)) {
            $this->_total = $this->parent->count();
        }

        return $this->_total;
    }

    public function sql_count()
    {
        return (!is_null($this->parent) ? $this->parent->count() : 0);
    }
}
