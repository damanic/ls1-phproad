<?php
use Phpr\Strings;

/**
 * @deprecated
 * Use Phpr\Strings;
 */
class Core_String extends Strings
{

    /**
     * @deprecated
     */
    public static function js_encode($str)
    {
        return Strings::jsEncode($str);
    }

    /**
     * @deprecated
     */
    public static function ucfirst($str)
    {
        return Strings::ucFirst($str);
    }

    /**
     * @deprecated
     */
    public static function split_to_words($query)
    {
        return Strings::splitToWords($query);
    }
        
    /**
     * @deprecated Use {@link Phpr\Strings::transliterate() transliterate()} method instead.
     */
    public static function asciify($string, $remove_non_ascii = false)
    {
        return Strings::transliterate($string);
    }

    /**
     * @deprecated
     * Used by twig, out of scope
     */
    public static function process_ls_tags($str)
    {
        $result = str_replace('{root_url}', root_url('/', true), $str);
            
        if (strpos($result, '{tax_inc_label}') !== false) {
            $result = str_replace('{tax_inc_label}', tax_incl_label(), $result);
        }
                
        return $result;
    }

}
