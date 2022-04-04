<?php
namespace Phpr;

use Phpr;

class Inflector
{
    protected static $cache = array();

    /**
     * Converts a word "into_it_s_underscored_version"
     *
     * Convert any "CamelCased" or "ordinary Word", or "ordinary-word" into an "underscored_word".
     * This can be really useful for creating friendly URLs.
     *
     * @param  string $word
     * @return string
     */
    public static function underscore($word)
    {
        $result = self::getCachedValue('underscore', $word);
        if ($result) {
            return $result;
        }

        $result = strtolower(
            preg_replace(
                '/[^A-Z^a-z^0-9^\/]+/',
                '_',
                preg_replace(
                    '/([a-z\d])([A-Z])/',
                    '\1_\2',
                    preg_replace(
                        '/([A-Z]+)([A-Z][a-z])/',
                        '\1_\2',
                        preg_replace(
                            '/([a-z]+)\-([a-z])/i',
                            '\1_\2',
                            preg_replace('/::/', '/', $word)
                        )
                    )
                )
            )
        );

        return self::addCache('underscore', $word, $result);
    }

    /**
     * Pluralizes English nouns.
     *
     * @param  string $word
     * @return string
     */
    public static function pluralize($word)
    {
        $result = self::getCachedValue('pluralize', $word);
        if ($result) {
            return $result;
        }

        $plural = array(
            '/(quiz)$/i' => '\1zes',
            '/^(ox)$/i' => '\1en',
            '/([m|l])ouse$/i' => '\1ice',
            '/(matr|vert|ind)ix|ex$/i' => '\1ices',
            '/(x|ch|ss|sh)$/i' => '\1es',
            '/([^aeiouy]|qu)ies$/i' => '\1y',
            '/([^aeiouy]|qu)y$/i' => '\1ies',
            '/(hive)$/i' => '\1s',
            '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '\1a',
            '/(buffal|tomat)o$/i' => '\1oes',
            '/(bu)s$/i' => '\1ses',
            '/(alias|status)/i' => '\1es',
            '/(octop|vir)us$/i' => '\1i',
            '/(ax|test)is$/i' => '\1es',
            '/s$/i' => 's',
            '/$/' => 's'
        );

        return self::addCache('pluralize', $word, self::applyRules($word, $plural));
    }

    public static function singularize($word)
    {
        $result = self::getCachedValue('singularize', $word);
        if ($result) {
            return $result;
        }

        $singular = array(
            '/(quiz)zes$/i' => '\\1',
            '/(matr)ices$/i' => '\\1ix',
            '/(vert|ind)ices$/i' => '\\1ex',
            '/^(ox)en/i' => '\\1',
            '/(alias|status)es$/i' => '\\1',
            '/([octop|vir])i$/i' => '\\1us',
            '/(cris|ax|test)es$/i' => '\\1is',
            '/(shoe)s$/i' => '\\1',
            '/(status)$/i' => '\\1',
            '/(o)es$/i' => '\\1',
            '/(bus)es$/i' => '\\1',
            '/([m|l])ice$/i' => '\\1ouse',
            '/(x|ch|ss|sh)es$/i' => '\\1',
            '/(m)ovies$/i' => '\\1ovie',
            '/(s)eries$/i' => '\\1eries',
            '/([^aeiouy]|qu)ies$/i' => '\\1y',
            '/([lr])ves$/i' => '\\1f',
            '/(tive)s$/i' => '\\1',
            '/(hive)s$/i' => '\\1',
            '/([^f])ves$/i' => '\\1fe',
            '/(^analy)ses$/i' => '\\1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\\1\\2sis',
            '/([ti])a$/i' => '\\1um',
            '/(n)ews$/i' => '\\1ews',
            '/s$/i' => ''
        );

        $result = self::applyRules($word, $singular);
        return self::addCache('singularize', $word, $result);
    }

    /**
     * Returns given word as CamelCased
     *
     * Converts a word like "send_email" to "sendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "WhoSOnline"
     *
     * @param  string $word
     * @return string
     */
    public static function camelize($word)
    {
        $result = self::getCachedValue('camelize', $word);
        if ($result) {
            return $result;
        }

        if (preg_match_all('/\/(.?)/', $word, $got)) {
            foreach ($got[1] as $k => $v) {
                $got[1][$k] = '::' . strtoupper($v);
            }

            $word = str_replace($got[0], $got[1], $word);
        }

        $result = str_replace(' ', '', ucwords(preg_replace('/[^A-Z^a-z^0-9^:]+/', ' ', $word)));
        $result[0] = strtolower($result[0]);

        return self::addCache('camelize', $word, $result);
    }

    /**
     * Returns given word as PascalCased
     *
     * Converts a word like "send_email" to "SendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "WhoSOnline"
     *
     * @param  string $word
     * @return string
     */
    public static function pascalize($word)
    {
        $result = self::getCachedValue('pascalize', $word);
        if ($result) {
            return $result;
        }

        if (preg_match_all('/\/(.?)/', $word, $got)) {
            foreach ($got[1] as $k => $v) {
                $got[1][$k] = '::' . strtoupper($v);
            }

            $word = str_replace($got[0], $got[1], $word);
        }

        $result = str_replace(' ', '', ucwords(preg_replace('/[^A-Z^a-z^0-9^:]+/', ' ', $word)));
        return self::addCache('pascalize', $word, $result);
    }

    /**
     * Same as camelize but first char is lowercased
     *
     * Converts a word like "send_email" to "sendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "whoSOnline"
     *
     * @param string $word Word to lowerCamelCase
     * @return string Returns a lowerCamelCasedWord
     */
    public static function variablize($word)
    {
        $word = self::camelize($word);
        return strtolower($word[0]) . substr($word, 1);
    }

    /**
     * Converts a class name to its table name according to rails naming conventions.
     *
     * Converts "CustomerSubscription" to "customer_subscription"
     *
     * @param  string $class_name
     * @return string
     */
    public static function tableize($class_name)
    {
        return self::pluralize(self::underscore($class_name));
    }

    /**
     * Converts a table name to its class name according to rails naming conventions.
     *
     * Converts "customer_subscription" to "CustomerSubscription"
     *
     * @param  string $table_name
     * @return string
     */
    public static function classify($table_name)
    {
        return self::pascalize(self::singularize($table_name));
    }


    /**
     * Creates a URI slug from a given string.
     *
     * Converts "Home Page" to "home-page"
     *
     * @param string $text
     * @param string $separator
     * @return string
     */
    public static function slugify($string, $separator = '-')
    {
        return strtolower(preg_replace(array('/[^-a-zA-Z0-9\s]/', '/[\s]/'), array('', $separator), $string));
    }

    /**
     * Returns $class_name in underscored form, with "_id" tacked on at the end.
     * This is for use in dealing with the database.
     *
     * @param  string $class_name
     * @param  bool   $separate_class_name_and_id_with_underscore
     * @return string
     */
    public static function foreignKey(
        $class_name,
        $primary_key = 'id',
        $separate_class_name_and_id_with_underscore = true
    ) {
        return self::underscore(
            self::singularize($class_name)
        ) . (($separate_class_name_and_id_with_underscore ? '_' : '') . $primary_key);
    }


    /**
     * Returns a human-readable string from $word
     *
     * Returns a human-readable string from $word, by replacing
     * underscores with a space, and by upper-casing the initial
     * character by default.
     *
     * If you need to uppercase all the words you just have to
     * pass 'all' as a second parameter.
     *
     * @param  string $word
     * @param  string $uppercase
     * @return string
     */
    public static function humanize($word, $uppercase = '')
    {
        $uppercase = ($uppercase == 'all') ? 'ucwords' : 'ucfirst';
        $result = self::getCachedValue('humanize' . $uppercase, $word);
        if ($result) {
            return $result;
        }

        $result = $uppercase(str_replace('_', ' ', preg_replace('/_id$/', '', $word)));

        return self::addCache('humanize' . $uppercase, $word, $result);
    }

    /**
     * Convert Classname to Filename
     *
     * @param  string $classname
     * @return string
     */
    public static function classFilename($classname)
    {
        $result = self::getCachedValue('classFilename', $classname);
        if ($result) {
            return $result;
        }

        if (($classname != 'Controller') && Util::ends_with($classname, 'Controller')) {
            $classname = substr($classname, 0, -10);
        }

        if (($classname != 'Behavior') && Util::ends_with($classname, 'Behavior')) {
            $classname = substr($classname, 0, -8);
        }

        $result = strtolower($classname);
        return self::addCache('classFilename', $word, $result);
    }

    /**
     * Convert class name to folder name
     *
     * @param  string $classname
     * @return string
     */
    public static function demodulize($classname)
    {
        $result = self::getCachedValue('classFilename', $classname);
        if ($result) {
            return $result;
        }

        if (($classname != 'Controller') && Util::ends_with($classname, 'Controller')) {
            $classname = substr($classname, 0, -10);
        }

        if (($classname != 'Behavior') && Util::ends_with($classname, 'Behavior')) {
            $classname = substr($classname, 0, -8);
        }

        $result = strtolower($classname);
        return self::addCache('classFilename', $word, $result);
    }


    /**
     * Apply language rules specified in $rules
     *
     * @param  string  $word
     * @param  mixed[] $rules
     * @return string
     */
    protected static function applyRules($word, $rules)
    {
        $uncountable = array('equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep', 'sms');

        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves'
        );

        $lowercased_word = strtolower($word);

        foreach ($uncountable as $_uncountable) {
            if (substr($lowercased_word, (-1 * strlen($_uncountable))) == $_uncountable) {
                return $word;
            }
        }

        foreach ($irregular as $_plural => $_singular) {
            if (preg_match('/(' . $_plural . ')$/i', $word, $arr)) {
                return preg_replace('/(' . $_plural . ')$/i', substr($arr[0], 0, 1) . substr($_singular, 1), $word);
            }
        }

        foreach ($rules as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }

        return $word;
    }

    protected static function getCachedValue($function, $argument)
    {
        if (!array_key_exists($function, self::$cache)) {
            return null;
        }

        if (!array_key_exists($argument, self::$cache[$function])) {
            return null;
        }

        return self::$cache[$function][$argument];
    }

    protected static function addCache($function, $argument, $value)
    {
        if (!array_key_exists($function, self::$cache)) {
            self::$cache[$function] = array();
        }

        return self::$cache[$function][$argument] = $value;
    }



    /**
     * @deprecated
     *
     */
    public static function foreign_key(
        $class_name,
        $primary_key = 'id',
        $separate_class_name_and_id_with_underscore = true
    ) {
        Phpr::$deprecate->setFunction('foreign_key','foreignKey');
       return self::foreignKey($class_name,$primary_key,$separate_class_name_and_id_with_underscore);
    }

}
