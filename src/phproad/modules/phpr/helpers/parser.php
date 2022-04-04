<?php
namespace Phpr;

/**
 * PHPR Parse class
 *
 * This helpful class allows text to have data parsed in.
 */
class Parser
{
    const KEY_OPEN = '{';
    const KEY_CLOSE = '}';

    private static array $options = array();

    // Services
    //

    public static function parseFile($file_path, $data, $options = array())
    {
        self::$options = $options;
        $string = file_get_contents($file_path);
        return self::processString($string, $data);
    }

    public static function parseText($string, $data, $options = array())
    {
        self::$options = $options;
        return self::processString($string, $data);
    }

    // Internals
    //

    // Internal string parse
    private static function processString($string, $data)
    {
        if (!is_string($string) || !strlen(trim($string))) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $string = self::processLoop($key, $value, $string);
            } else {
                $string = self::processKey($key, $value, $string);
            }
        }

        return $string;
    }

    // Process a single key
    private static function processKey($key, $value, $string)
    {
        if (isset(self::$options['encode_html']) && self::$options['encode_html']) {
            $value = Html::encode($value);
        }

        return str_replace(self::KEY_OPEN . $key . self::KEY_CLOSE, $value, $string);
    }

    // Search for open/close keys and process them in a nested fashion
    private static function processLoop($key, $data, $string)
    {
        $return_string = '';
        $match = self::processLoopRegex($string, $key);

        if (!$match) {
            return $string;
        }

        foreach ($data as $row) {
            $matched_text = $match[1];
            foreach ($row as $key => $value) {
                if (is_array($value)) {
                    $matched_text = self::processLoop($key, $value, $matched_text);
                } else {
                    $matched_text = self::processKey($key, $value, $matched_text);
                }
            }

            $return_string .= $matched_text;
        }

        return str_replace($match[0], $return_string, $string);
    }

    private static function processLoopRegex($string, $key)
    {
        $open = preg_quote(self::KEY_OPEN);
        $close = preg_quote(self::KEY_CLOSE);

        $regex = '|';
        $regex .= $open . $key . $close; // Open
        $regex .= '(.+?)'; // Content
        $regex .= $open . '/' . $key . $close; // Close
        $regex .= '|s';

        preg_match($regex, $string, $match);
        return ($match) ? $match : false;
    }

}
