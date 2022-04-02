<?php

/***
 * @deprecated
 * @see Phpr\Locale
 */
class Phpr_LocalizationManager
{
    private $_language;
    private $_localizationPath;
    private $_stringCache;

    /**
     * Creates a new Phpr_LocalizationManager instance.
     *
     * @param string $Language            Specifies the user language.
     * @param string $LocalizationDirPath Specifies a path to the localization directory.
     */
    public function __construct($Language, $LocalizationDirPath)
    {
        if (!file_exists($LocalizationDirPath) || !is_readable($LocalizationDirPath)) {
            throw new Phpr_SystemException(sprintf('Localization directory %s is not readable.', $LocalizationDirPath));
        }

        $this->_language = strtolower($Language);
        $this->_localizationPath = $LocalizationDirPath;
        $this->_stringCache = array();
    }

    /**
     * Returns a localization string.
     *
     * @param  string $Key      Specifies a string key.
     * @param  string $Category Specifies a file category.
     * @return string
     */
    public function getString($Key, $Category = null)
    {
        // Try to load exact language value
        //
        $languageString = $this->getStringInternal($Key, $Category, $this->_language);

        if ($languageString !== null) {
            return $languageString;
        }

        // Try to fallback to a neutral culture
        //
        if (!(($pos = strpos($this->_language, '_')) === false)) {
            $language = substr($this->_language, 0, $pos);
            $languageString = $this->getStringInternal($Key, $Category, $language);
        }

        if (!is_null($languageString)) {
            return $languageString;
        }

        // Try to fallback to a default language
        //
        $languageString = $this->getStringInternal($Key, $Category, null);

        return $languageString;
    }

    /**
     * Sets the user language to use by this Localization Manager instance.
     *
     * @param string $Language Language
     */
    public function setLanguage($Language)
    {
        $this->_language = strtolower($Language);
    }

    /**
     * Returns a localization string in a specified langauge.
     *
     * @param  string $Key      Specifies a string key
     * @param  string $Category Specifies a file category
     * @param  string $Language Specifies a language
     * @return string
     */
    private function getStringInternal($Key, $Category, $Language)
    {
        if (!strlen($Language)) {
            $Language = "default";
        }

        if (!array_key_exists($Category, $this->_stringCache) || !array_key_exists(
                $Language,
                $this->_stringCache[$Category]
            )
        ) {
            $this->preloadLanguageKeys($Language, $Category);
        }

        if (is_null($this->_stringCache[$Category][$Language])) {
            return null;
        }

        if (!array_key_exists($Key, $this->_stringCache[$Category][$Language])) {
            return null;
        } else {
            return $this->_stringCache[$Category][$Language][$Key];
        }
    }

    /**
     * Preloads the language strings.
     *
     * @param  string $Language Specifies a language
     * @param  string $Category Specifies a file category
     * @return string
     */
    private function preloadLanguageKeys($Language, $Category)
    {
        $PrefixNamePart = $Category;
        if (strlen($PrefixNamePart)) {
            $PrefixNamePart = $PrefixNamePart . ".";
        }

        $fileName = $PrefixNamePart . $Language . ".res";

        $filePath = $this->_localizationPath . "/" . $fileName;

        if (!file_exists($filePath)) {
            if (!array_key_exists($Category, $this->_stringCache)) {
                $this->_stringCache[$Category] = array();
            }

            $this->_stringCache[$Category][$Language] = null;
            return;
        }

        if (!is_readable($filePath)) {
            throw new Phpr_SystemException(sprintf('Localization file %s is not readable.', $fileName));
        }

        $prevSetting = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', 1);

        $strings = file($filePath);

        $fileStrings = array();

        foreach ($strings as $string) {
            if (strlen($string) > 0 && substr($string, 0, 1) != '#') {
                $stringParts = explode("\t", $string);
                if (count($stringParts) > 1) {
                    $stringKey = $stringParts[0];
                    $stringValue = rtrim($stringParts[1], "\r\n");
                    $stringValue = str_replace("\\n", "\n", $stringValue);

                    $fileStrings[$stringKey] = $stringValue;
                }
            }
        }

        ini_set('auto_detect_line_endings', $prevSetting);

        if (!array_key_exists($Category, $this->_stringCache)) {
            $this->_stringCache[$Category] = array();
        }

        $this->_stringCache[$Category][$Language] = $fileStrings;
    }
}
