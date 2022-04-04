<?php
namespace Phpr;

use Phpr;

/***
 * @deprecated
 */
class Closure
{
    private $function;
    private $params;

    public function __construct($function, $params)
    {
        Phpr::$deprecate->setClass('Phpr_Closure');
        $this->_function = $function;
        $this->_params = $params;
    }

    /***
     * @deprecated
     */
    public function call($params)
    {
        Phpr::$deprecate->setFunction('call');
        $methodParams = $params;
        foreach ($this->params as $param) {
            array_push($methodParams, $param);
        }

        call_user_func_array($this->function, $methodParams);
    }
}
