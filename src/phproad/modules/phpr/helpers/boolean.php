<?php
namespace Phpr;

/**
 * PHPR Boolean helper
 *
 * This class contains functions that may be useful for working with booleans.
 */
class Boolean
{
    public static function from($value)
    {
        if (is_string($value)) {
            return self::from_string($value);
        } else {
            return (boolean)$value;
        }
    }

    public static function fromString($str)
    {
        $str = trim($str);

        if ($str == true) {
            return true;
        } else {
            if ($str == 'y') {
                return true;
            } else {
                if ($str == 'yes') {
                    return true;
                } else {
                    if ($str == 'true') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}