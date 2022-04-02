<?php
namespace Phpr;

use Phpr\SystemException;
use Phpr\Router;

/**
 * Router Rule Class
 *
 * Represents a rule for mapping an URI string to the PHP Road controller and action.
 * Do not use this class directly. Use the Phpr::$router->addRule method instead.
 */
class RouterRule
{
    public $URI = null;
    public $controller = null;
    public $action = null;
    public $defaults = array();
    public $checks = array();
    public $folder = null;
    public $converts = array();

    private $params = array();

    /**
     * Creates a new rule.
     * Do not create rules directly. Use the Phpr::$router->addRule method instead.
     *
     * @param  string $URI Specifies the URI to be matched. No leading and trailing slashes. The :controller and :action names may be used. Example: :controller/:action/:id
     * @return Phpr\RouterRule
     */
    public function __construct($URI)
    {
        $this->URI = $URI;
        $this->params = Router::getURIParams(explode("/", $this->URI));
    }

    /**
     * Sets a name of the controller to be used if the requested URI matches this rule URI.
     *
     * @param  string $Controller Specifies a controller name.
     * @return Phpr\RouterRule
     */
    public function controller($Controller)
    {
        if ($this->controller !== null) {
            throw new Phpr_SystemException(
                "Invalid router rule configuration. The controller is already specified: [{$this->URI}]"
            );
        }

        if (Router::valueIsParam($Controller)) {
            if (!isset($this->params[$Controller])) {
                throw new Phpr_SystemException(
                    "Invalid router rule configuration. The parameter \"$Controller\" specified in the Controller instruction is not found in the rule URI: [{$this->URI}]"
                );
            }
        }

        $this->controller = $Controller;

        return $this;
    }

    /**
     * Sets a name of the controller action be executed if the requested URI matches this rule URI.
     *
     * @param  string $Action Specifies an action name.
     * @return Phpr\RouterRule
     */
    public function action($Action)
    {
        if ($this->action !== null) {
            throw new Phpr_SystemException(
                "Invalid router rule configuration. The action is already specified: [{$this->URI}]"
            );
        }

        if (Router::valueIsParam($Action)) {
            if (!isset($this->params[$Action])) {
                throw new Phpr_SystemException(
                    "Invalid router rule configuration. The parameter \"$Action\" specified in the Action instruction is not found in the rule URI: [{$this->URI}]"
                );
            }
        }

        $this->action = $Action;
        return $this;
    }

    /**
     * Sets a default URI parameter value. This value will be used if the URI component is ommited.
     *
     * @param  string $Param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
     * @param  mixed  $Value Specifies a parameter value.
     * @return Phpr\RouterRule
     */
    public function def($Param, $Value)
    {
        if (!isset($this->params[$Param])) {
            throw new Phpr_SystemException(
                "Invalid router rule configuration. The default parameter \"$Param\" is not found in the rule URI: [{$this->URI}]"
            );
        }

        $this->defaults[$Param] = $Value;
        return $this;
    }

    /**
     * Converts a parameter value according a specified regular expression match and replacement strings
     *
     * @param  string $Param   Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
     * @param  mixed  $Match   Specifies a regular expression match value
     * @param  mixed  $Replace Specifies a regular expression replace value
     * @return Phpr\RouterRule
     */
    public function convert($Param, $Match, $Replace)
    {
        if (!isset($this->params[$Param])) {
            throw new Phpr_SystemException(
                "Invalid router rule configuration. The convert parameter \"$Param\" is not found in the rule URI: [{$this->URI}]"
            );
        }

        $this->converts[$Param] = array($Match, $Replace);
        return $this;
    }

    /**
     * Sets the URI parameter value check.
     *
     * @param  string $Param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
     * @param  string $Check Specifies a checking value as a Perl-Compatible Regular Expression pattern, for example "/^\d{1,2}$/"
     * @return Phpr\RouterRule
     */
    public function check($Param, $Check)
    {
        if (!isset($this->params[$Param])) {
            throw new Phpr_SystemException(
                "Invalid router rule configuration. The parameter \"$Param\" specified in the Check instruction is not found in the rule URI: [{$this->URI}]"
            );
        }

        $this->checks[$Param] = $Check;
        return $this;
    }

    /**
     * Defines a path to the controller class file.
     *
     * @param  string $Folder Specifies a path to the file.
     *                        You may use parameters from URI and default parameters here.
     *                        Example: Phpr::$router->addRule("catalog/:product")->def('product', 'books')->folder('controllers/:product');
     * @return Phpr\RouterRule
     */
    public function folder($Folder)
    {
        $Folder = str_replace("\\", "/", $Folder);

        // Validate the folder path
        //
        $PathParams = Router::getURIParams(explode("/", $Folder));
        foreach ($PathParams as $Param => $Index) {
            if ($Param != Router::URL_CONTROLLER && $Param != Router::URL_ACTION && !isset($this->params[$Param])) {
                throw new Phpr_SystemException(
                    "Invalid router rule configuration. The parameter \"$Param\" specified in the Folder instruction is not found in the rule URI: [{$this->URI}]"
                );
            }
        }

        $this->folder = $Folder;
        return $this;
    }

}
