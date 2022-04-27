<?php
namespace Core;

use Phpr\Arr as PhprArray;

/**
 * @deprecated
 * Use : Phpr\Arr
 */
class Core_Array
{
    /**
     * @deprecated
     */
    public static function merge_recursive_distinct()
    {
        return PhprArray::mergeRecursiveDistinct();
    }

    /**
     * @deprecated
     */
    public static function filter_by_keys($list, $keys = array())
    {
        return PhprArray::filterByKeys($list, $keys);
    }

    /**
     * @deprecated
     */
    public static function sanitize_value_types($list)
    {
        return PhprArray::sanitizeValueTypes($list);
    }

    /**
     * @deprecated
     */
    public static function get_key_value($key, $list)
    {
        return PhprArray::getKeyValue($key, $list);
    }
}
