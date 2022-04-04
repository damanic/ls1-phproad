<?php

/***
 * @deprecated
 * @see Phpr\Locale
 *
 * Phpr_Language class assists in application localization.
 *
 * The instance of this class is available in the Phpr global object: Phpr::$lang.
 * You may set user language programmatically: Phpr::$lang->setLanguage("en_EN"),
 * or in the configuration file: $CONFIG["LANGUAGE"] = "en_EN". In the configuration file
 * you may specify the "auto" value for the language: $CONFIG["LANGUAGE"] = "auto". In
 * this case the language specified in the user browser configuration will be used.
 * If language is not set the default value en_EN will be used.
 *
 */

class Phpr_Language
{
    const defaultLanguage = 'en_EN';

    private $_appLocManager;
    private $_moduleLocManagers = array();

    private $_decSeparator;
    private $_groupSeparator;

    private $_currencyLoaded;
    private $_intl_currency_symbol;
    private $_local_currency_symbol;
    private $_decimal_separator;
    private $_group_separator;
    private $_decimal_digits;
    private $_positive_sign;
    private $_negative_sign;
    private $_p_cs_precedes;
    private $_p_sep_by_space;
    private $_n_cs_precedes;
    private $_n_sep_by_space;
    private $_p_format;
    private $_n_format;

    private $_language;

    /**
     * Creates a new Phpr_Language instance.
     */
    public function __construct()
    {
        $this->_language = null;
        $this->_appLocManager = null;
        $this->_decSeparator = null;
        $this->_groupSeparator = null;
        $this->_currencyLoaded = false;
    }

    /**
     * Returns an application localization string.
     *
     * @param  string                                    $Key      Specifies the string key
     * @param  string                                    $Category Specifies the string category.
     * @param  mixed[] Optional list of string arguments.
     * Use these parameters if need to format string like if you use the sprintf function.
     * @return string
     */
    public function app($Key, $Category = null)
    {
        $this->initLanguage();

        if ($this->_appLocManager === null) {
            $this->_appLocManager = new Phpr_LocalizationManager($this->_language, PATH_APP . '/localization');
        }

        $string = $this->_appLocManager->getString($Key, $Category);

        if (func_num_args() <= 2) {
            return $string;
        }

        $args = func_get_args();
        for ($i = 1; $i <= 2; $i++) {
            array_shift($args);
        }

        return vsprintf($string, $args);
    }

    /**
     * Returns a module localization string.
     *
     * @param  string $Module   Specifies a module name
     * @param  string $Key      Specifies the string key
     * @param  string $Category Specifies the string category
     * @return string
     */
    public function mod($Module, $Key, $Category = null)
    {
        $this->initLanguage();

        $Category = strtolower($Category);

        if (!isset($this->_moduleLocManagers[$Module])) {
            $ModulePath = Phpr_Module::findModule($Module);
            if ($ModulePath === null) {
                return null;
            }

            $this->_moduleLocManagers[$Module] = new Phpr_LocalizationManager(
                $this->_language,
                $ModulePath . '/locale'
            );
        }

        $string = $this->_moduleLocManagers[$Module]->getString($Key, $Category);

        if (func_num_args() <= 3) {
            return $string;
        }

        $args = func_get_args();
        for ($i = 1; $i <= 3; $i++) {
            array_shift($args);
        }

        return vsprintf($string, $args);
    }

    /**
     * Sets the user language.
     *
     * @param string $Language Specifies the user language in format en_EN.
     */
    public function setLanguage($Language)
    {
        $this->_language = $Language;

        if ($this->_appLocManager !== null) {
            $this->_appLocManager->setLanguage($Language);
        }

        foreach ($this->_moduleLocManagers as $ModuleLocManager) {
            $ModuleLocManager->setLanguage($Language);
        }

        $this->_decSeparator = null;
        $this->_currencyLoaded = false;
    }

    /**
     * Returns the user language.
     */
    public function getLanguage()
    {
        if ($this->_language === null) {
            $this->initLanguage();
        }

        return $this->_language;
    }

    /**
     * Returns a string representation of a number, corresponding a current language numbers format.
     *
     * @param  float $Number   Specifies a number.
     * @param  int   $Decimals . Optional, number of decimals.
     * @return string
     */
    public function num($Number, $Decimals = 0)
    {
        $this->initLanguage();

        if ($this->_decSeparator === null) {
            $this->loadNumberFormat();
        }

        if (!strlen($Number)) {
            return null;
        }

        return number_format($Number, $Decimals, $this->_decSeparator, $this->_groupSeparator);
    }

    /**
     * Converts a string to a number, corresponding a current language numbers format.
     * If the specified value may not be converted to number, returns boolean false.
     *
     * @param  float $Str Specifies a string to parse.
     * @return mixed
     */
    public function strToNum($Str)
    {
        $this->initLanguage();

        if ($this->_decSeparator === null) {
            $this->loadNumberFormat();
        }

        $val = str_replace($this->_decSeparator, '.', $Str);
        $val = str_replace($this->_groupSeparator, '', $val);

        if (!is_numeric($val)) {
            return false;
        }

        return $val;
    }

    /**
     * Returns a string representation of a date, corresponding a current language dates format.
     *
     * @param Phpr_DateTime $Date   Specifies a date value.
     * @param string        $Format . Optional, output format.
     *                              By default the short date
     *                              format used (11/6/2006 -
     *                              for en_US).

     * @return string
     */
    public function date(Phpr_DateTime $Date, $Format = "%x")
    {
        $this->initLanguage();

        return $Date->format($Format);
    }

    /**
     * Converts a string to a Phpr_DateTime object, corresponding a current language dates format.
     * If the specified value may not be converted to date/time, returns boolean false.
     *
     * @param float        $Str      Specifies a string to parse.
     * @param string       $Format   Specifies the date/time format.
     *                               By default the short date
     *                               format (%x) used (11/6/2006 -
     *                               for en_US).

     * @param  DateTimeZone $TimeZone Optional. Specifies a time zone to assign to a new object.
     * @return mixed
     */
    public function strToDate($Str, $Format = "%x", $TimeZone = null)
    {
        return Phpr_DateTime::parse($Str, $Format, $TimeZone);
    }

    /**
     * Returns a string representation of the currency value, corresponding a current language currency format.
     *
     * @param  float $Value Specifies a currency value.
     * @return string
     */
    public function currency($Value)
    {
        $this->initLanguage();

        if (!$this->_currencyLoaded) {
            $this->loadCurrencyFormat();
        }

        $isNegative = $Value < 0;

        if ($isNegative) {
            $Value *= -1;
        }

        $numericPart = number_format(
            $Value,
            $this->_decimal_digits,
            $this->_decimal_separator,
            $this->_group_separator
        );

        $finalFormat = $isNegative ? $this->_n_format : $this->_p_format;
        $sign = $isNegative ? $this->_negative_sign : $this->_positive_sign;

        $currencySymbol = $this->_local_currency_symbol;
        if ($finalFormat == 3) {
            $currencySymbol = $sign . $currencySymbol;
        } elseif ($finalFormat == 4) {
            $currencySymbol = $currencySymbol . $sign;
        }

        if (!$isNegative) {
            if ($this->_p_cs_precedes) {
                $numAndCs = $this->_p_sep_by_space ? $currencySymbol . ' ' . $numericPart : $currencySymbol . $numericPart;
            } else {
                $numAndCs = $this->_p_sep_by_space ? $numericPart . ' ' . $currencySymbol : $numericPart . $currencySymbol;
            }
        } else {
            if ($this->_n_cs_precedes) {
                $numAndCs = $this->_n_sep_by_space ? $currencySymbol . ' ' . $numericPart : $currencySymbol . $numericPart;
            } else {
                $numAndCs = $this->_n_sep_by_space ? $numericPart . ' ' . $currencySymbol : $numericPart . $currencySymbol;
            }
        }

        switch ($finalFormat) {
        case 0:
            return '(' . $numAndCs . ')';
        case 1:
            return $sign . $numAndCs;
        case 2:
            return $numAndCs . $sign;
        case 3:
            return $numAndCs;
        case 4:
            return $numAndCs;
        }
    }

    /**
     * Loads the user language.
     */
    private function initLanguage()
    {
        if ($this->_language !== null) {
            return;
        }

        $ConfigValue = Phpr::$config->get("LANGUAGE");

        if ($ConfigValue === null || !strlen($ConfigValue)) {
            $this->_language = self::defaultLanguage;
        } else {
            if ($ConfigValue == 'auto') {
                $this->_language = Phpr::$request->gerUserLanguage();
            } else {
                $this->_language = $ConfigValue;
            }
        }
    }

    /**
     * Loads the number format preferences.
     */
    private function loadNumberFormat()
    {
        $this->_decSeparator = $this->mod('phpr', 'decimalSeparator', 'numbers');
        $this->_groupSeparator = $this->mod('phpr', 'group_separator', 'numbers');
    }

    /**
     * Loads the currency format preferences.
     */
    private function loadCurrencyFormat()
    {
        $this->_intl_currency_symbol = $this->mod('phpr', 'intlCurrencySymbol', 'currency');
        $this->_local_currency_symbol = $this->mod('phpr', 'localCurrencySymbol', 'currency');
        $this->_decimal_separator = $this->mod('phpr', 'decimalSeparator', 'currency');
        $this->_group_separator = $this->mod('phpr', 'group_separator', 'currency');
        $this->_decimal_digits = $this->mod('phpr', 'decimalDigits', 'currency');
        $this->_positive_sign = $this->mod('phpr', 'positiveSign', 'currency');
        $this->_negative_sign = $this->mod('phpr', 'negativeSign', 'currency');
        $this->_p_cs_precedes = (int)($this->mod('phpr', 'pCsPrecedes', 'currency'));
        $this->_p_sep_by_space = (int)($this->mod('phpr', 'pSepBySpace', 'currency'));
        $this->_p_cs_precedes = (int)($this->mod('phpr', 'nCsPrecedes', 'currency'));
        $this->_n_sep_by_space = (int)($this->mod('phpr', 'nSepBySpace', 'currency'));
        $this->_p_format = $this->mod('phpr', 'pFormat', 'currency');
        $this->_n_format = $this->mod('phpr', 'nFormat', 'currency');

        $this->_currencyLoaded = true;
    }
}
