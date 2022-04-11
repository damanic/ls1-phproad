<?php namespace Db;

/**
 * PHPR Data Filter
 *
 * Currently used as a base class for List Filters
 */

class DataFilter
{
    public $model_class_name;
    public $model_filters;
    public $list_columns = array();

    public function prepareListData()
    {
        $className = $this->model_class_name;
        $result = new $className();

        if ($this->model_filters) {
            $result->where($this->model_filters);
        }

        return $result;
    }

    public function applyToModel($model, $keys, $context = null)
    {
        return $model;
    }

    protected function keysToStr($keys)
    {
        return "('" . implode("','", $keys) . "')";
    }

    public function asString($keys, $context = null)
    {
        return null;
    }
}
