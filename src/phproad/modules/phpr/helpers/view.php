<?php
namespace Phpr;

use Phpr;
use Phpr\SystemException;

class View
{
    private static $blockStack = array();
    private static $blocks = array();

    private static $errorBlocks = array();

    /**
     * Returns the JavaScript inclusion tag for the PHP Road script file.
     * The PHP Road javascript files are situated in the PHP Road javascript folder.
     * By default this function creates a link to the application bootstrap file
     * that outputs the requested script. You may speed up the resource request
     * by providing a direct URL to the PHP Road javascript folder in the
     * configuration file:
     * $CONFIG['JAVASCRIPT_URL'] = 'www.my_company_com/phproad/javascript';
     *
     * @param  mixed $Name Specifies a name of the script file to include.
     *                     Use the 'defaults' name to include the minimal required PHP Road script set.
     *                     If this parameter is omitted the 'defaults' value is used.
     *                     Also you may specify a list of script names as array.
     * @return string
     */
    public static function includeJavaScript($Name = 'defaults', $version_mark = null)
    {
        if (!is_array($Name)) {
            $Name = array($Name);
        }

        $result = null;
        foreach ($Name as $ScriptName) {
            $ScriptName = urlencode($ScriptName);

            if ($ScriptName == 'defaults') {
                foreach (Response::$defaultJsScripts as $DefaultScript) {
                    $result .= "<script type=\"text/javascript\" src=\"phproad/javascript/$DefaultScript?$version_mark\"></script>\n";
                }
            } else {
                $result .= "<script type=\"text/javascript\" src=\"phproad/javascript/" . $ScriptName . "\"></script>\n";
            }
        }

        return $result;
    }

    /**
     * Begins the layout block.
     *
     * @param string $Name Specifies the block name.
     */
    public static function beginBlock($name)
    {
        array_push(self::$blockStack, $name);
        ob_start();
    }


    /**
     * Closes the layout block.
     *
     * @param boolean $append Indicates that the new content should be appended to the existing block content.
     */
    public static function endBlock($append = false)
    {
        if (!count(self::$blockStack)) {
            throw new SystemException("Invalid layout blocks nesting");
        }

        $Name = array_pop(self::$blockStack);
        $Contents = ob_get_clean();

        if (!isset(self::$blocks[$Name])) {
            self::$blocks[$Name] = $Contents;
        } else {
            if ($append) {
                self::$blocks[$Name] .= $Contents;
            }
        }

        if (!count(self::$blockStack) && (ob_get_length() > 0)) {
            ob_end_clean();
        }
    }

    /**
     * Sets a content of the layout block.
     *
     * @param string $Name    Specifies the block name.
     * @param string $Content Specifies the block content.
     */
    public static function setBlock($Name, $Content)
    {
        self::beginBlock($Name);
        echo $Content;
        self::endBlock();
    }

    /**
     * Appends a content of the layout block.
     *
     * @param string $Name    Specifies the block name.
     * @param string $Content Specifies the block content.
     */
    public static function appendBlock($Name, $Content)
    {
        if (!isset(self::$blocks[$Name])) {
            self::$blocks[$Name] = null;
        }

        self::$blocks[$Name] .= $Content;
    }

    /**
     * Returns the layout block contents and deletes the block from memory.
     *
     * @param  string $Name    Specifies the block name.
     * @param  string $Default Specifies a default block value to use if the block requested is not exists.
     * @return string
     */
    public static function block($Name, $Default = null)
    {
        $Result = self::getBlock($Name, $Default);

        unset(self::$blocks[$Name]);

        return $Result;
    }

    /**
     * Returns the layout block contents but not deletes the block from memory.
     *
     * @param  string $Name    Specifies the block name.
     * @param  string $Default Specifies a default block value to use if the block requested is not exists.
     * @return string
     */
    public static function getBlock($Name, $Default = null)
    {
        if (!isset(self::$blocks[$Name])) {
            return $Default;
        }

        $Result = self::$blocks[$Name];

        return $Result;
    }

    /**
     * Returns an error message.
     *
     * @param  string $Message Specifies the error message. If this parameter is omitted, the common
     *                         validation message will be returned.
     * @return string
     */
    public static function showError($Message = null)
    {
        if ($Message === null) {
            $Controller = self::getCurrentController();
            if (is_null($Controller)) {
                return null;
            }

            $Message = Html::encode($Controller->validation->errorMessage);
        }

        if (strlen($Message)) {
            return $Message;
        }
    }

    /**
     * Returns a current controller
     *
     * @return Phpr_ControllerBase
     */
    private static function getCurrentController()
    {
        if (Phpr_Component::$current !== null) {
            return Phpr_Component::$current;
        }

        if (Controller::$current !== null) {
            return Controller::$current;
        }
    }


    /**
     * @deprecated
     */
    public static function begin_block($name)
    {
        Phpr::$deprecate->setFunction('begin_block', 'beginBlock');
        return self::beginBlock($name);
    }

    /**
     * @deprecated
     */
    public static function end_block($append = false)
    {
        Phpr::$deprecate->setFunction('end_block', 'endBlock');
        return self::endBlock($append);
    }
}
