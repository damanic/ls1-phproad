<?php

namespace Phpr;

use Phpr;
use Phpr\DateTime;
use Phpr\SystemException;
use Phpr\ApplicationException;

/**
 * Phpr\Locale class assists in application localization.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$locale.
 * You may set user language programmatically: Phpr::$locale->setLocale("en_US"),
 * or in the configuration file: $CONFIG["LOCALE"] = "en_US". In the configuration file
 * you may specify the "auto" value for the locale: $CONFIG["LOCALE"] = "auto". In
 * this case the language specified in the user browser configuration will be used.
 * If locale is not set the default value en_US will be used.
 */
class Locale
{
    const defaultLocaleCode = 'en_US';

    private $currency;
    private $localeCode;
    private $languageCode;
    private $countryCode;
    private $filePaths;
    private $directoryPaths;
    private $definitions;
    private $pluralizations;

    private $decSeparator;
    private $containerSeparator;

    private $currencyIsLoaded;
    private $intlCurrencySymbol;
    private $localCurrencySymbol;
    private $decimalSeparator;
    private $decimalDigits;
    private $positiveSign;
    private $negativeSign;
    private $pCsPrecedes;
    private $pSepBySpace;
    private $nCsPrecedes;
    private $nSepBySpace;
    private $pFormat;
    private $nFormat;

    public function __construct()
    {
        $this->containers = array();
        $this->currencyIsLoaded = false;
        $this->filePaths = array();
    }

    public function determineDefinition($container, $source, $placeholders = array(), $options = array())
    {
        $this->initLocale();

        $locale = $this->localeCode;
        $definition = null;

        // Load exact value
        if ($this->definitionExists($locale, $container, $source, $options['variation'])) {
            $definition = $this->getDefinition($locale, $container, $source, $options['variation']);
        } else {
            // Fallback to a neutral culture
            if ($pos = strpos($this->localeCode, '_')) {
                $language = substr($this->localeCode, 0, $pos);
                $locale = $language . '_xx';

                if ($this->definitionExists($locale, $container, $source, $options['variation'])) {
                    $definition = $this->getDefinition($locale, $container, $source, $options['variation']);
                } else {
                    $country = substr($this->localeCode, $pos + 1);
                    $locale = 'xx_' . strtolower($country);

                    if ($this->definitionExists($locale, $container, $source, $options['variation'])) {
                        $definition = $this->getDefinition($locale, $container, $source, $options['variation']);
                    }
                }
            }

            // Failed so fallback to a default language
            if ($definition === null) {
                $language = substr(self::defaultLocaleCode, 0, $pos);
                $locale = $language . '_xx';

                if ($this->definitionExists($locale, $container, $source, $options['variation'])) {
                    $definition = $this->getDefinition($locale, $container, $source, $options['variation']);
                } else {
                    $country = substr(self::defaultLocaleCode, $pos + 1);
                    $locale = 'xx_' . strtolower($country);

                    if ($this->definitionExists($locale, $container, $source, $options['variation'])) {
                        $definition = $this->getDefinition($locale, $container, $source, $options['variation']);
                    }
                }
            }
        }

        if ($definition === null) {
            throw new ApplicationException(
                "Could not find locale definition: " . $container . ", " . $source . " (" . $this->localeCode . ")."
            );
        }

        return $definition;
    }

    public function determinePluralization()
    {
        $this->initLocale();

        $locale = $this->localeCode;
        $pluralization = null;

        // Load exact locale
        if ($this->pluralizationExists($locale)) {
            $pluralization = $this->getPluralization($locale);
        } else {
            // Fallback to a neutral culture
            if ($pos = strpos($this->localeCode, '_')) {
                $language = substr($this->localeCode, 0, $pos);
                $locale = $language . '_xx';

                if ($this->pluralizationExists($locale)) {
                    $pluralization = $this->getPluralization($locale);
                } else {
                    $country = substr($this->localeCode, $pos + 1);
                    $locale = 'xx_' . $country;

                    if ($this->pluralizationExists($locale)) {
                        $pluralization = $this->getPluralization($locale);
                    }
                }
            } else {
                $locale = self::defaultLocaleCode;

                // Fallback to a default language
                if ($this->pluralizationExists($locale)) {
                    $pluralization = $this->getPluralization($locale);
                }
            }
        }

        if ($pluralization === null) {
            throw new ApplicationException("Could not find locale pluralization ({$this->localeCode}).");
        }

        return $pluralization;
    }

    /**
     * Returns an application localization string.
     * @param string $container Specifies the string container. In the form of 'modulename.subcontainer'.
     * @param string $source Specifies the string key.
     * @param array $placeholders Optional list of placeholders. Used for replacement.
     * @param array $options Optional list of options.
     * Use these parameters if need to format string like if you use the sprintf function.
     * @return string
     * Example 1:
     * Phpr::$locale->getString('shop.messages', 'add_to_cart', array(
     *    'count' => 20
     * ));
     */
    public function getString($container, $source, $placeholders = array(), $options = array())
    {
        $this->initLocale();

        $variation = 1;
        $replace_keys = array();
        $replace_values = array();

        $pluralization = $this->determinePluralization();

        foreach ($placeholders as $key => $value) {
            if (is_numeric($value)) {
                $value = (float)$value;

                if ($value === 0) {
                    $variation = 0;
                } else {
                    $result = eval($pluralization);
                    $variation = $result['current'];
                }
            }

            $replace_keys[] = ':' . $key;
            $replace_values[] = $value;
        }

        $definition = $this->determineDefinition(
            $container,
            $source,
            $placeholders,
            array_merge(array(
                'variation' => $variation
            ), $options)
        );

        // Run placeholder replacement against the definition
        $definition = str_replace($replace_keys, $replace_values, $definition);

        return trim($definition);
    }

    /**
     * Sets the user locale.
     * @param string $locale_code Specifies the user locale in format en_US.
     */
    public function setLocale($locale_code)
    {
        $this->localeCode = $locale_code;

        $this->initLocale(true);

        $this->decSeparator = null;
        $this->currencyIsLoaded = false;
    }

    /**
     * Returns the user locale.
     */
    public function getLocaleCode()
    {
        $this->initLocale();
        return $this->localeCode;
    }

    /**
     * Returns the user language.
     */
    public function getLanguageCode()
    {
        $this->initLocale();
        return $this->languageCode;
    }

    /**
     * Returns the user country.
     */
    public function getCountryCode()
    {
        $this->initLocale();
        return $this->countryCode;
    }

    /**
     * Returns a string representation of a number, corresponding a current locale numbers format.
     * @param float $number Specifies a number.
     * @param int $decimals . Optional, number of decimals.
     * @return string
     */
    public function getNumber($number, $decimals = 0)
    {
        $this->initLocale();

        if ($this->decSeparator === null) {
            $this->loadNumberFormat();
        }

        if (!strlen($number)) {
            return;
        }

        return number_format($number, $decimals, $this->decSeparator, $this->group_separator);
    }

    /**
     * Converts a string to a number, corresponding a current language numbers format.
     * If the specified value may not be converted to number, returns boolean false.
     * @param float $str Specifies a string to parse.
     * @return mixed
     */
    public function stringToNumber($str)
    {
        $this->initLocale();

        if ($this->decSeparator === null) {
            $this->loadNumberFormat();
        }

        $val = str_replace($this->decSeparator, '.', $str);
        $val = str_replace($this->group_separator, '', $val);

        if (!is_numeric($val)) {
            return false;
        }

        return $val;
    }

    /**
     * Returns a string representation of a date, corresponding a current language dates format.
     * @param Phpr\DateTime $date Specifies a date value.
     * @param string $format . Optional, output format.
     * By default the short date format used(11/6/2006 - for en_US).
     * @return string
     */
    public function date(DateTime $date, $format = "%x")
    {
        $this->initLocale();

        return $date->format($format);
    }

    /**
     * Converts a string to a Phpr\DateTime object, corresponding a current language dates format.
     * If the specified value may not be converted to date/time, returns boolean false.
     * @param float $str Specifies a string to parse.
     * @param string $format Specifies the date/time format.
     * By default the short date format(%x) used(11/6/2006 - for en_US).
     * @param DateTimeZone $timezone Optional. Specifies a time zone to assign to a new object.
     * @return mixed
     */
    public function stringToDate($str, $format = "%x", $timezone = null)
    {
        return DateTime::parse($str, $format, $timezone);
    }

    /**
     * Returns a string representation of the currency value, corresponding a current language currency format.
     * @param float $value Specifies a currency value.
     * @return string
     */
    public function getCurrency($value)
    {
        $this->initLocale();

        if (!$this->currencyIsLoaded) {
            $this->loadCurrencyFormat();
        }

        $is_negative = $value < 0;

        if ($is_negative) {
            $value *= -1;
        }

        $numeric_part = number_format($value, $this->decimalDigits, $this->decimalSeparator, $this->group_separator);

        $final_format = $is_negative ? $this->nFormat : $this->pFormat;
        $sign = $is_negative ? $this->negativeSign : $this->positiveSign;

        $currency_symbol = $this->localCurrencySymbol;

        if ($final_format == 3) {
            $currency_symbol = $sign . $currency_symbol;
        } elseif ($final_format == 4) {
            $currency_symbol = $currency_symbol . $sign;
        }

        if (!$is_negative) {
            if ($this->pCsPrecedes) {
                $num_and_cs = $this->pSepBySpace ? $currency_symbol . ' ' . $numeric_part : $currency_symbol . $numeric_part;
            } else {
                $num_and_cs = $this->pSepBySpace ? $numeric_part . ' ' . $currency_symbol : $numeric_part . $currency_symbol;
            }
        } else {
            if ($this->nCsPrecedes) {
                $num_and_cs = $this->nSepBySpace ? $currency_symbol . ' ' . $numeric_part : $currency_symbol . $numeric_part;
            } else {
                $num_and_cs = $this->nSepBySpace ? $numeric_part . ' ' . $currency_symbol : $numeric_part . $currency_symbol;
            }
        }

        switch ($final_format) {
            case 0:
                return '(' . $num_and_cs . ')';
            case 1:
                return $sign . $num_and_cs;
            case 2:
                return $num_and_cs . $sign;
            case 3:
                return $num_and_cs;
            case 4:
                return $num_and_cs;
        }
    }

    /**
     * Returns a string in a specified locale container.
     * @param string $locale Specifies a language
     * @param string $container Specifies a file category
     * @param string $source Specifies a string key
     * @return string
     */
    public function getDefinition($locale, $container, $source, $variation = 1)
    {
        $locale = trim(strtolower($locale));
        $container = trim(strtolower($container));
        $source = trim(strtolower($source));
        $variation = trim(strtolower($variation));

        if (!$this->definitionExists($locale, $container, $source, $variation)) {
            $this->load($locale);
        }

        if (!isset($this->definitions[$locale][$container])) {
            throw new ApplicationException("Could not find locale container: " . $container . " (" . $locale . ").");
        }

        if (!isset($this->definitions[$locale][$container][$source][$variation])) {
            throw new ApplicationException(
                "Could not find locale definition: " . $container . ", " . $source . ", " . $variation . " (" . $locale . ")."
            );
        }

        return $this->definitions[$locale][$container][$source][$variation];
    }

    public function definitionExists($locale, $container, $source, $variation = 1)
    {
        $locale = trim(strtolower($locale));
        $container = trim(strtolower($container));
        $source = trim(strtolower($source));
        $variation = trim(strtolower($variation));
        return isset($this->definitions[$locale][$container][$source][$variation]);
    }

    public function setDefinition($locale, $container, $source, $value, $variation = 1)
    {
        $locale = trim(strtolower($locale));
        $container = trim(strtolower($container));
        $source = trim(strtolower($source));
        $variation = trim(strtolower($variation));
        $this->definitions[$locale][$container][$source][$variation] = $value;
    }

    public function getDefinitions()
    {
        return $this->definitions;
    }

    public function getPluralizations()
    {
        return $this->pluralizations;
    }

    public function getPluralization($locale)
    {
        // attempt to load the locale if pluralization doesn't exist
        if (!$this->pluralizationExists($locale)) {
            $this->load($locale);
        }

        // does it exist yet?
        if (!$this->pluralizationExists($locale)) {
            throw new ApplicationException("Could not find locale pluralization: " . $locale);
        }

        $pluralization = $this->pluralizations[$locale];

        return $pluralization;
    }

    public function pluralizationExists($locale)
    {
        return isset($this->pluralizations[$locale]);
    }

    public function setPluralization($locale, $value)
    {
        $this->pluralizations[$locale] = $value;
    }

    public function getDirectoryPaths()
    {
        if ($this->directoryPaths !== null) {
            return $this->directoryPaths;
        }

        $this->directoryPaths = array();
        $paths = array();

        $application_paths = Phpr::$classLoader->get_application_directories();
        $module_paths = Phpr::$classLoader->find_paths('modules');

        $paths = array_merge($paths, $application_paths);

        foreach ($module_paths as $module_path) {
            $iterator = new \DirectoryIterator($module_path);

            foreach ($iterator as $directory) {
                if (!$directory->isDir() || $directory->isDot()) {
                    continue;
                }

                $paths[] = $directory->getPathname();
            }
        }

        foreach ($paths as $path) {
            if (!file_exists($directory_path = $path . '/locale')) {
                continue;
            }

            if (!is_readable($directory_path)) {
                throw new SystemException("Locale directory " . $directory_path . " is not readable.");
            }

            $this->directoryPaths[] = $directory_path;
        }

        return $this->directoryPaths;
    }

    public function getPartialLocale($locale_code)
    {
        if (!$locale_code) {
            return array();
        }

        $x1 = explode('_', strtolower($locale_code));

        if (count($x1) === 2) {
            return array(
                'locale' => $locale_code,
                'language' => $x1[0],
                'country' => $x1[1]
            );
        } else {
            return array(
                'language' => $locale_code,
                'country' => $locale_code
            );
        }
    }

    public function getFilePaths($locale_code = null, $extension = 'csv')
    {
        if (isset($this->filePaths[$locale_code . $extension])) {
            return $this->filePaths[$locale_code . $extension];
        }

        $this->filePaths[$locale_code . $extension] = array();

        $locale = $this->getPartialLocale($locale_code);
        $paths = $this->getDirectoryPaths();

        foreach ($paths as $path) {
            $iterator = new \DirectoryIterator($path);

            foreach ($iterator as $file) {
                if ($file->isDir() || !preg_match(
                    "/^([^\.]*)\.([^\.]*)\." . $extension . "$/i",
                    $file->getFilename(),
                    $m1
                )) {
                    continue;
                }

                $partial_locale = $this->getPartialLocale($m1[2]);

                // This isn't the locale you are looking for
                if ($locale && !array_intersect($partial_locale, $locale)) {
                    continue;
                }

                $file_path = $path . '/' . $file->getFilename();

                if (!is_readable($file_path)) {
                    throw new SystemException("Locale file " . $file_path . " is not readable.");
                }

                $this->filePaths[$locale_code . $extension][] = $file_path;
            }
        }

        return $this->filePaths[$locale_code . $extension];
    }

    /**
     * Loads the locale strings.
     * @param string $locale Specifies a locale
     * @return null
     */
    public function load($locale_code = null)
    {
        $results = Phpr::$events->fire_event('phpr:on_before_locale_loaded', $this, $locale_code);

        foreach ($results as $result) {
            // Hook has handled locale
            if ($result) {
                return;
            }
        }

        $this->loadDefinitions($locale_code);
        $this->loadPluralizations($locale_code);

        Phpr::$events->fire_event('phpr:on_after_locale_loaded', $this, $locale_code);
    }

    public function loadPluralizations($locale_code = null)
    {
        $file_paths = $this->getFilePaths($locale_code, 'php');

        foreach ($file_paths as $file_path) {
            $x1 = explode('.', $file_path);

            $extension = $x1[count($x1) - 1];
            $locale = $x1[count($x1) - 2];
            $handle = null;

            $x2 = explode('_', $locale);

            try {
                $data = file_get_contents($file_path);

                if (!$this->pluralizationExists($locale)) {
                    $this->setPluralization($locale, $data);
                }
            } catch (\Exception $ex) {
                if ($handle) {
                    @fclose($handle);
                }

                throw $ex;
            }
        }
    }

    public function loadDefinitions($locale_code = null)
    {
        $auto_detect_line_endings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', 1);

        $file_paths = $this->getFilePaths($locale_code, 'csv');

        foreach ($file_paths as $file_path) {
            $x1 = explode('.', $file_path);

            $extension = $x1[count($x1) - 1];
            $locale = $x1[count($x1) - 2];
            $handle = null;

            $x2 = explode('_', $locale);

            try {
                $handle = fopen($file_path, 'r');
                $delimeter = ',';
                $first_row_found = false;
                $line_number = 0;
                $has_variation = false;

                while (($row = fgetcsv($handle, 2000000, $delimeter)) !== false) {
                    ++$line_number;

                    if (\FileSystem\Csv::csvRowIsEmpty($row)) {
                        continue;
                    }

                    if (!$first_row_found) {
                        $first_row_found = true;

                        continue;
                    }

                    // Not a definition, perhaps a comment or heading?
                    if (count($row) < 3) {
                        continue;
                    }

                    if (count($row) === 4) {
                        $has_variation = true;
                    }

                    $container = $row[0];
                    $source = $row[1];

                    if ($has_variation) {
                        $variation = $row[2];

                        if ((int)$variation != $variation) {
                            throw new ApplicationException(
                                "Invalid locale definition. Most likely the definition is incompatible CSV. Please verify the variation column."
                            );
                        }

                        $destination = $row[3];
                    } else {
                        $variation = 1;
                        $destination = $row[2];
                    }

                    if (!$this->definitionExists($locale, $container, $source, $variation)) {
                        $this->setDefinition($locale, $container, $source, $destination, $variation);
                    }
                }
            } catch (\Exception $ex) {
                if ($handle) {
                    @fclose($handle);
                }
                throw $ex;
            }
        }

        ini_set('auto_detect_line_endings', $auto_detect_line_endings);
    }

    /**
     * @deprecated
     */
    public function mod($Module, $Key, $Category = null)
    {
        Phpr::$deprecate->setFunction('mod', 'getString');
        return $this->getString("$Module.$Category", $Key);
    }

    /**
     * @deprecated
     */
    public function app($Module, $Key)
    {
        Phpr::$deprecate->setFunction('app');
    }

    /**
     * @deprecated
     */
    public function setLanguage($Language)
    {
        Phpr::$deprecate->setFunction('setLanguage', 'setLocale');
        $this->setLocale($Language);
    }

    /**
     * @deprecated
     */
    public function getLanguage()
    {
        Phpr::$deprecate->setFunction('getLanguage', 'getLocaleCode');
        return $this->getLocaleCode();
    }

    /**
     * @deprecated
     */
    public function num($Number, $Decimals = 0)
    {
        Phpr::$deprecate->setFunction('num', 'getNumber');
        return $this->getNumber($Number, $Decimals);
    }

    /**
     * @deprecated
     */
    public function strToNum($Str)
    {
        Phpr::$deprecate->setFunction('strToNum', 'stringToNumber');
        return $this->stringToNumber($Str);
    }

    /**
     * @deprecated
     */
    public function strToDate($Str, $Format = "%x", $TimeZone = null)
    {
        Phpr::$deprecate->setFunction('strToDate', 'stringToDate');
        return $this->stringToDate($Str, $Format, $TimeZone);
    }

    /**
     * @deprecated
     */
    public function currency($Value)
    {
        Phpr::$deprecate->setFunction('currency', 'getCurrency');
        return $this->getCurrency($Value);
    }


    /**
     * Loads the user locale.
     */
    private function initLocale($force = false)
    {
        if (!$force && $this->localeCode !== null) {
            return;
        }

        $localeCode = Phpr::$config->get('LOCALE', null);
        if (!$localeCode) {
            //try legacy config param
            $localeCode = Phpr::$config->get('LANGUAGE', self::defaultLocaleCode);
        }

        if ($localeCode === 'auto') {
            $localeCode = Phpr::$request->getUserLanguage();
        }

        $x1 = explode('_', $localeCode);
        $this->languageCode = $x1[0];
        $this->countryCode = $x1[1];
        $this->localeCode = $localeCode;

        $this->load();
    }

    /**
     * Loads the number format preferences.
     */
    private function loadNumberFormat()
    {
        $this->decSeparator = $this->getString('phpr.numbers', 'decimalSeparator');
        $this->group_separator = $this->getString('phpr.numbers', 'group_separator');
    }

    /**
     * Loads the currency format preferences.
     */
    private function loadCurrencyFormat()
    {
        $this->intlCurrencySymbol = $this->getString('phpr.currency', 'intlCurrencySymbol');
        $this->localCurrencySymbol = $this->getString('phpr.currency', 'localCurrencySymbol');
        $this->decimalSeparator = $this->getString('phpr.currency', 'decimalSeparator');
        $this->group_separator = $this->getString('phpr.currency', 'group_separator');
        $this->decimalDigits = $this->getString('phpr.currency', 'decimalDigits');
        $this->positiveSign = $this->getString('phpr.currency', 'positiveSign');
        $this->negativeSign = $this->getString('phpr.currency', 'negativeSign');
        $this->pCsPrecedes = (int)$this->getString('phpr.currency', 'pCsPrecedes');
        $this->pSepBySpace = (int)$this->getString('phpr.currency', 'pSepBySpace');
        $this->pCsPrecedes = (int)$this->getString('phpr.currency', 'nCsPrecedes');
        $this->nSepBySpace = (int)$this->getString('phpr.currency', 'nSepBySpace');
        $this->pFormat = $this->getString('phpr.currency', 'pFormat');
        $this->nFormat = $this->getString('phpr.currency', 'nFormat');

        $this->currencyIsLoaded = true;
    }
}
