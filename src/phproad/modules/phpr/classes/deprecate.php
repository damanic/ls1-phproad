<?php

namespace Phpr;

use Phpr;
use Phpr\DeprecateException;

/**
 * PHPR Deprecate class
 *
 * Used for deprecating classes, methods and arguments internally
 */
class Deprecate
{
    public static bool $suppressReported = false;
    private static array $reported = array();

    public function __construct(){
        //@!?
        if (!class_exists('Phpr\DeprecateException')) {
            require_once PATH_APP.'/phproad/core/exceptions.php';
        }
    }

    public function setClass(string $className, string $replacement = null): void
    {
        $message = 'Class ' . $className . ' is a deprecated.';
        $message .= $replacement ? ' Use ' . $replacement . ' instead' : ' Sorry, there is no alternative';

        if (!$this->isReported($message)) {
            try {
                $this->setReported($message);
                throw new DeprecateException($message);
            } catch (DeprecateException $ex) {
                $this->handleException($ex);
            }
        }
    }

    public function setFunction(string $FuncName, string $replacement = null): void
    {
        $message = 'Function ' . $FuncName . ' is deprecated.';
        $message .= $replacement ? ' Use ' . $replacement . ' instead' : ' Sorry, there is no alternative';

        if (!$this->isReported($message)) {
            try {
                $this->setReported($message);
                throw new DeprecateException($message);
            } catch (DeprecateException $ex) {
                $this->handleException($ex);
            }
        }
    }

    public function setArgument(string $FuncName, string $argName, string $replacement = null): void
    {
        $message = 'Function ' . $FuncName . ' was called with an argument that is deprecated: ' . $argName . '.';
        $message .= $replacement ? ' Use ' . $replacement . ' instead' : ' Sorry, there is no alternative';

        if (!$this->isReported($message)) {
            try {
                $this->setReported($message);
                throw new DeprecateException($message);
            } catch (DeprecateException $ex) {
                $this->handleException($ex);
            }
        }
    }

    public function setClassProperty(string $propertyName, string $replacement = null, string $className = null): void
    {
        if (!$className) {
            list($callTo, $callFrom) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $className = $callFrom['class'] ?? $className;
        }
        $message = 'Property YO ' . $className . '::$' . $propertyName . ' is deprecated.';
        $message .= $replacement ? ' Use ' . $replacement . ' instead' : ' Sorry, there is no alternative';

        if (!$this->isReported($message)) {
            try {
                $this->setReported($message);
                throw new DeprecateException($message);
            } catch (DeprecateException $ex) {
                $this->handleException($ex);
            }
        }
    }

    private function setReported(string $msg): void
    {
        if (self::$suppressReported) {
            $key = md5($msg);
            self::$reported[$key] = true;
        }
    }

    private function isReported($msg): bool
    {
        if (count(self::$reported)) {
            $key = md5($msg);
            if (isset(self::$reported[$key])) {
                return true;
            }
        }
        return false;
    }

    private function handleException($ex)
    {
        if (isset(Phpr::$errorLog) && is_a(Phpr::$errorLog, 'Phpr\Phpr_ErrorLog')) {
            Phpr::$errorLog->logException($ex);
        }
    }
}
