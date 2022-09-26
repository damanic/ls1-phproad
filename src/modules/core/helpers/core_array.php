<?php

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
        $args = func_get_args();
        return call_user_func_array('\Phpr\Arr::mergeRecursiveDistinct', $args);
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
