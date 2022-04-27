<?php

use Phpr\Util;

/**
 * @deprecated
 * Use Phpr\Util
 */
class Core_Object
{
    /**
     * @deprecated
     */
    public static function to_array($object, $parent_key = null)
    {
        return Util::objectToArray($object);
    }

    /**
     * @deprecated
     */
    public static function to_plain_array($object)
    {
        $result = array();
            
        $array = self::to_array($object);
            
        foreach ($array as $key => $value) {
            $item = self::to_array($value);
                
            if (is_array($item)) {
                $result = array_merge($result, $item);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
