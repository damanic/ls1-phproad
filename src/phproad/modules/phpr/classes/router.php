<?php
namespace Phpr;

use Phpr\SystemException;

/**
 * PHPR Router Class
 *
 * Router maps an URI string to the PHP Road controllers and actions.
 */
class Router
{
    private array $rules = array();
    private bool $actionIndex = false;
    private array $segments = array();

    /**
     * @var array
     * A list of URI parameters names a and values.
     * The URI "archive/:year/:month/:day" will produce 3 parameters: year, month and day.
     */
    public array $parameters = array();

    /**
     * @var string
     * Contains a current Controller name. This variable is set during the Rout method call.
     */
    public $controller = null;

    /**
     * @var string
     * Contains a current Action name. This variable is set during the Rout method call.
     */
    public $action = null;

    const URL_CONTROLLER = 'controller';
    const URL_ACTION = 'action';
    const URL_MODULE = 'module';

    /**
     * Parses an URI and finds the controller class name, action and parameters.
     *
     * @param string $URI         Specifies the URI to parse.
     * @param string &$Controller The controller name
     * @param string &$Action     The controller action name
     * @param array  &$Parameters A list of the action parameters
     * @param string &$Folder     A path to the controller folder
     */
    public function route($URI, &$Controller, &$Action, &$Parameters, &$Folder)
    {
        $Controller = null;
        $Action = null;
        $Parameters = array();

        if ($URI[0] == '/') {
            $URI = substr($URI, 1);
        }

        $this->segments = $segments = $this->segmentURI($URI);
        $segmentCount = count($segments);

        foreach ($this->rules as $Rule) {
            if (strlen($Rule->URI)) {
                $ruleSegments = explode("/", $Rule->URI);
            } else {
                $ruleSegments = array();
            }

            try {
                $ruleSegmentCount = count($ruleSegments);
                $ruleParams = $this->getURIParams($ruleSegments);

                // Check whether the number of URI segments matches
                //
                $minSegmentNum = $ruleSegmentCount - count($Rule->defaults);

                if (!($segmentCount >= $minSegmentNum && $segmentCount <= $ruleSegmentCount)) {
                    continue;
                }

                // Check whether the static segments matches
                //
                foreach ($ruleSegments as $index => $ruleSegment) {
                    if (!$this->valueIsParam($ruleSegment)) {
                        if (!isset($segments[$index]) || $segments[$index] != $ruleSegment) {
                            continue 2;
                        }
                    }
                }

                // Validate checks
                //
                foreach ($Rule->checks as $param => $pattern) {
                    $paramIndex = $ruleParams[$param];

                    // Do not check default parameter values
                    //
                    if (!isset($segments[$paramIndex])) {
                        continue;
                    }

                    // Match the parameter value
                    //
                    if (!preg_match($pattern, $segments[$paramIndex])) {
                        continue 2;
                    }
                }

                $this->actionIndex = false;

                // Evaluate the controller parameters
                //
                foreach ($ruleParams as $paramName => $paramIndex) {
                    if ($this->actionIndex === false && $paramName == self::URL_ACTION) {
                        $this->actionIndex = $paramIndex;
                    }

                    if ($paramName == self::URL_CONTROLLER || $paramName == self::URL_ACTION) {
                        continue;
                    }

                    $value = $this->evaluateParameterValue($paramName, $paramIndex, $segments, $Rule->defaults);

                    if ($paramName != self::URL_MODULE) {
                        $Parameters[] = $value;
                    }

                    $this->parameters[$paramName] = $value;
                }

                // Evaluate the controller and action values
                //
                $Controller = $this->evaluateTargetValue(self::URL_CONTROLLER, $ruleParams, $Rule, $segments);
                $Action = $this->evaluateTargetValue(self::URL_ACTION, $ruleParams, $Rule, $segments);
                if (!strlen($Action)) {
                    $Action = 'index';
                }

                $this->controller = $Controller;
                $this->action = $Action;

                // Evaluate the controller path
                //
                $Folder = $Rule->folder;

                if ($Rule->folder !== null) {
                    $FolderParams = self::getURIParams(explode("/", $Rule->folder));
                    foreach ($FolderParams as $paramName => $paramIndex) {
                        if ($paramName == self::URL_CONTROLLER) {
                            $paramValue = $Controller;
                        } elseif ($paramName == self::URL_ACTION) {
                            $paramValue = $Action;
                        } else {
                            $paramValue = $this->parameters[$paramName];
                        }

                        $Folder = strtolower(str_replace(':' . $paramName, $paramValue, $Folder));
                    }
                }

                break;
            } catch (\Exception $ex) {
                throw new SystemException("Error routing rule [{$Rule->URI}]: " . $ex->getMessage());
            }
        }
    }

    /**
     * This function takes an URI and returns its segments as array.
     *
     * @param  URI Specifies the URI to process.
     * @return array
     */
    protected function segmentURI($URI)
    {
        $result = array();

        foreach (explode("/", preg_replace("|/*(.+?)/*$|", "\\1", $URI)) as $segment) {
            $segment = trim($segment);
            if ($segment != '') {
                $result[] = $segment;
            }
        }

        return $result;
    }

    /**
     * @param  array $Segments A list of URI segments
     * @return array
     * @ignore
     * Returns a list of parameters in the URI. Parameters are prefixed with the colon character.
     */
    public static function getURIParams($Segments)
    {
        $result = array();

        foreach ($Segments as $index => $val) {
            if (self::valueIsParam($val)) {
                $result[substr($val, 1)] = $index;
            }
        }

        return $result;
    }

    /**
     * Returns URL of the current controller
     *
     * @return string Controller URL
     */
    public function getControllerRootUrl()
    {
        if ($this->actionIndex === false) {
            return null;
        }

        $result = array();
        foreach ($this->segments as $index => $value) {
            if ($index < $this->actionIndex) {
                $result[] = $value;
            }
        }

        return implode('/', $result);
    }

    /**
     * @param  string $Segment Specifies the segment name to check.
     * @return boolean
     * @ignore
     * Determines whether value is parameter.
     */
    public static function valueIsParam($Segment)
    {
        return strlen($Segment) && substr($Segment, 0, 1) == ':';
    }

    /**
     * Returns a name of the controller or action.
     *
     * @param  string          $TargetType  Specifies a type of the target - controller or action.
     * @param  array           &$RuleParams List of the rule parameters.
     * @param  RouterRule &$Rule       Specifies the rule.
     * @param  array           &$Segments   A list of the URI segments.
     * @return string
     */
    protected function evaluateTargetValue($TargetName, &$RuleParams, &$Rule, &$Segments)
    {
        //$fieldName = ucfirst($TargetName);
        $fieldName = strtolower($TargetName);

        // Check whether the target value is specified explicitly in the rule target settings.
        //
        if (!isset($RuleParams[$TargetName])) {
            if (strlen($Rule->$fieldName)) {
                $targetValue = $Rule->$fieldName;

                if ($this->valueIsParam($targetValue)) {
                    $targetValue = substr($targetValue, 1);
                    return strtolower(
                        $this->evaluateParameterValue(
                            $targetValue,
                            $RuleParams[$targetValue],
                            $Segments,
                            $Rule->defaults
                        )
                    );
                } else {
                    return strtolower($targetValue);
                }
            }
        } else {
            // Extract the target value from the URI or try to find a default value
            //
            if (isset($Segments[$RuleParams[$TargetName]])) {
                return strtolower(
                    $this->evaluateConvertedValue(
                        $TargetName,
                        strtolower($Segments[$RuleParams[$TargetName]]),
                        $Segments,
                        $RuleParams,
                        $Rule->defaults,
                        $Rule->converts
                    )
                );
                // return ucfirst( strtolower( $Segments[$RuleParams[$TargetName]] ) );
            } else {
                $Value = $this->evaluateParameterValue($TargetName, $TargetName, $Segments, $Rule->defaults);
                return strtolower(
                    $this->evaluateConvertedValue(
                        $TargetName,
                        strtolower($Value),
                        $Segments,
                        $RuleParams,
                        $Rule->defaults,
                        $Rule->converts
                    )
                );
            }
        }
    }

    /**
     * Returns a specified or default value of the parameter.
     *
     * @param  string $ParamName Specifies a name of the parameter.
     * @index  int $Index Specifies the index of the parameter.
     * @param  array  &$Segments A list of the URI segments.
     * @param  array  &$Defaults Specifies the rule parameters defaults.
     * @return string
     */
    protected function evaluateParameterValue($ParamName, $Index, &$Segments, &$Defaults)
    {
        if (isset($Segments[$Index])) {
            return $Segments[$Index];
        }

        if (isset($Defaults[$ParamName])) {
            return $Defaults[$ParamName];
        }

        return null;
    }

    protected function evaluateConvertedValue($ParamName, $ParamValue, &$Segments, &$RuleParams, &$Defaults, &$Converts)
    {
        if (isset($Converts[$ParamName])) {
            $ConvertRule = $Converts[$ParamName];

            foreach ($RuleParams as $Name => $Index) {
                if (isset($Segments[$Index])) {
                    $Value = $Segments[$Index];
                } else {
                    $Value = $this->evaluateParameterValue($Name, null, $Segments, $Defaults);
                }

                $ConvertRule[1] = str_replace(":" . $Name, $Value, $ConvertRule[1]);
            }
            return preg_replace($ConvertRule[0], $ConvertRule[1], $ParamValue);
        }

        return $ParamValue;
    }

    /**
     * Adds a routing rule.
     * Use this method to define custom URI mappings to your application controllers.
     * After adding a rule use the RouterRule class methods to configure the rule. For example: AddRule("archive/:year")->controller("blog")->action("Archive")->def("year", 2006).
     *
     * @return RouterRule
     */
    public function addRule($URI)
    {
        return $this->rules[] = new RouterRule($URI);
    }

    /**
     * Returns a URI parameter by its name.
     *
     * @param  string $Name    Specifies the parameter name
     * @param  string $Default Default parameter value
     * @return string
     */
    public function param($Name, $Default = null)
    {
        return isset($this->parameters[$Name]) ? $this->parameters[$Name] : $Default;
    }

    /*
     * Returns a requested URI
     */
    public function getURI()
    {
        $Result = $this->controller . '/' . $this->action;

        foreach ($this->parameters as $ParamValue) {
            if (strlen($ParamValue)) {
                $Result .= '/' . $ParamValue;
            } else {
                break;
            }
        }

        return $Result;
    }
}