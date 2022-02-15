<?php

class Db_ActiveRecordIterator implements Iterator
{
    private $members = array();

    private $index = 0;

    public function __construct($object)
    {
        $this->members = get_object_vars($object);
        unset($this->members['auto_save_associations']);
        unset($this->members['content_columns']);
        unset($this->members['errors']);
    }

    public function current()
    {
        return current($this->members);
    }

    public function key()
    {
        return key($this->members);
    }

    public function next()
    {
        return next($this->members);
    }

    public function rewind()
    {
        reset($this->members);
    }

    public function valid()
    {
        return ($this->current() !== false);
    }
}
