<?php
namespace Db;

class DataFilterSwitcher
{
    public function applyToModel($model, $enabled, $context = null)
    {
        return $model;
    }

    public function asString($enabled, $context = null)
    {
        return null;
    }
}
