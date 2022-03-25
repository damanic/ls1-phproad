<?php
namespace Phpr;

use Phpr;
use Phpr\DeprecateException;

/**
 * PHPR Deprecate class
 *
 * Used for deprecating classes, methods and arguements internally
 */
class Deprecate
{
    public function setClass($class_name, $replacement = null)
    {
        if ($replacement) {
            $message = 'Class ' . $class_name . ' is a deprecated. Please use class ' . $replacement . ' instead';
        } else {
            $message = 'Class ' . $class_name . ' is a deprecated. Sorry, there is no alternative';
        }

        try {
            throw new DeprecateException($message);
        } catch (DeprecateException $ex) {
            Phpr::$errorLog->logException($ex);
        }
    }

    public function setFunction($func_name, $replacement = null)
    {
        if ($replacement) {
            $message = 'Function ' . $func_name . ' is deprecated. Please use ' . $replacement . ' instead';
        } else {
            $message = 'Function ' . $func_name . ' is deprecated. Sorry, there is no alternative';
        }

        try {
            throw new DeprecateException($message);
        } catch (DeprecateException $ex) {
            Phpr::$errorLog->logException($ex);
        }
    }

    public function setArgument($func_name, $arg_name, $replacement = null)
    {
        if ($replacement) {
            $message = 'Function ' . $func_name . ' was called with an argument that is deprecated: ' . $arg_name . '. Please use ' . $replacement . ' instead';
        } else {
            $message = 'Function ' . $func_name . ' was called with an argument that is deprecated: ' . $arg_name . '. Sorry, there is no alternative';
        }

        try {
            throw new DeprecateException($message);
        } catch (DeprecateException $ex) {
            Phpr::$errorLog->logException($ex);
        }
    }
}