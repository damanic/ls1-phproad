<?php
namespace Core;

use Phpr\Boolean;
    
/**
 * @deprecated
 */
class CsvHelper
{
    /**
     * @deprecated
     * Use Phpr\Boolean
     */
    public static function boolean($value)
    {
        return Boolean::fromString($value);
    }
}
