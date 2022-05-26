<?php
namespace Phpr;

class Number
{
    /**
     * Returns true if the passed value is a floating point number
     * @param mixed $value number
     * @return boolean Returns boolean
     */
    public static function isValidFloat($value)
    {
        return preg_match('/^[0-9]*?\.?[0-9]*$/', $value);
    }

    /**
     * Returns true if the passed value is an integer value
     * @param mixed $value number
     * @return boolean Returns boolean
     */
    public static function isValidInt($value)
    {
        return preg_match('/^[0-9]*$/', $value);
    }

    // Decode Identifiers YouTube style
    public static function decodeId($int)
    {
        return intval(self::base36Decode(str_rot13($int))) - 100;
    }

    // Encode Identifiers YouTube style
    public static function encodeId($int)
    {
        return str_rot13(self::base36Encode($int + 100));
    }

    public static function base36Encode($base10)
    {
        return base_convert($base10, 10, 36);
    }

    public static function base36Decode($base36)
    {
        return base_convert($base36, 36, 10);
    }

}