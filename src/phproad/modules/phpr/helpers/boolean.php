<?php
namespace Phpr;

/**
 * PHPR Boolean helper
 *
 * This class contains functions that may be useful for working with boolean strings.
 */
class Boolean
{
    /**
     * Converts any given value to a BOOL value
     * @param string $value
     * @return bool
     */
    public static function from($value)
    {
        if (is_string($value)) {
            return self::fromString($value);
        } else {
            return (bool)$value;
        }
    }

    /**
     * Convert a string to BOOL
     * @param string $str
     * @return bool
     */
    public static function fromString(string $str)
    {
        if ($str == true) {
            return true;
        } else {
            $trueValues = array(
                '1',
                'enabled',
                'y',
                'yes',
                'active',
                'true'
            );
            $str = trim($str);
            if (in_array($str, $trueValues)) {
                return true;
            }
        }
        return false;
    }
}