<?php

namespace Phpr;

use Phpr;
use Phpr\Html;

/**
 * PHP Road Exception base class
 *
 * Phpr\Exception is a base class for all PHP Road exceptions.
 * Phpr\Exception automatically adds a record to the error log.
 * Use this class as a base class for your application exceptions hierarchy
 * if you want to achieve the automatic logging. Do not forget to call
 * the parent constructor in the inherited exceptions.
 */
class Exception extends \Exception
{

    public $hint_message;
    public $log_id;
    public $log_status;

    /**
     * Creates a new Phpr\Exception instance.
     *
     * @param string $Message Message of exception.
     * @param int $Code Code of exception.
     */
    public function __construct($Message = null, $Code = 0)
    {
        // Call the parent constructor
        //
        parent::__construct($Message, $Code);

        // Log the exception
        //
        if (Phpr::$errorLog !== null) {
            try {
                $result = Phpr::$errorLog->logException($this);
                if (is_array($result)) {
                    $this->log_id = $result['id'] ?? null;
                    $this->log_status = $result['status'] ?? null;
                }
            } catch (\Exception $ex) {
                // Prevent the looping
            }
        }
    }

    /**
     * Formats a trace string for display in HTML or text format.
     *
     * @param Exception $Exception Specifies an exception to format the trace for.
     * @param boolean @Html Indicates whether the result must be in HTML format.
     * @return string
     */
    public static function formatTrace($Exception, $Html = true)
    {
        $result = null;

        $TraceInfo = $Exception->getTrace();

        $lastIndex = count($TraceInfo) - 1;

        // Begin the event list
        //
        if ($Html) {
            $result .= "<ol>\n<li>";
        }

        $newLineChar = $Html ? "</li>\n<li>" : "\n";

        foreach ($TraceInfo as $index => $Event) {
            $functionName = (isset($Event['class']) && strlen($Event['class'])) ? $Event['class'] . '->' . $Event['function'] : $Event['function'];

            // Do not include the handlers to the trace
            //
            if ($functionName == 'Phpr\SysErrorHandler' || $functionName == 'Phpr\SysExceptionHandler') {
                continue;
            }

            $file = isset($Event['file']) ? basename($Event['file']) : null;
            $line = isset($Event['line']) ? $Event['line'] : null;

            // Prepare the argument list
            //
            $args = null;
            if (isset($Event['args']) && count($Event['args'])) {
                $args = self::_formatTraceArguments($Event['args'], $Html);
            }

            if (!is_null($file)) {
                $result .= sprintf('%s(%s) in %s, line %s', $functionName, $args, $file, $line);
            } else {
                $result .= $functionName . "($args)";
            }

            if ($index < $lastIndex) {
                $result .= $newLineChar;
            }
        }

        // End the event list
        //
        if ($Html) {
            $result .= "</li></ol>\n";
        }

        return $result;
    }

    /**
     * Prepares a function or method argument list for display in HTML or text format
     *
     * @param array                                                             &$arguments A list of the function or method arguments
     * @param boolean @Html Indicates whether the result must be in HTML format.
     * @return string
     */
    public static function _formatTraceArguments(&$arguments, $Html = true)
    {
        $argsArray = array();
        foreach ($arguments as $argument) {
            $arg = null;

            if (is_array($argument)) {
                $items = array();

                foreach ($argument as $k => $v) {
                    if (is_array($v)) {
                        $value = 'array(' . count($v) . ')';
                    } elseif (is_object($v)) {
                        $value = 'object(' . get_class($v) . ')';
                    } elseif ($v === null) {
                        $value = 'null';
                    } elseif (is_integer($argument)) {
                        $value = $v;
                    } else {
                        $value = "'" . ($Html ? Html::encode($v) : $v) . "'";
                    }

                    $items[] = $k . ' => ' . $value;
                }

                if (count($items)) {
                    $arg = 'array(' . count($argument) . ') [' . implode(', ', $items) . ']';
                } else {
                    $arg = 'array(0)';
                }
            } elseif (is_object($argument)) {
                $arg = 'object(' . get_class($argument) . ')';
            } elseif ($argument === null) {
                $arg = 'null';
            } elseif (is_integer($argument)) {
                $arg = $argument;
            } else {
                $arg = "'" . ($Html ? Html::encode($argument) : $argument) . "'";
            }

            if ($Html) {
                $arg = '<span style="color: #398999">' . $arg . '</span>';
            }

            $argsArray[] = $arg;
        }

        return implode(', ', $argsArray);
    }
}

/**
 * PHP Road System Exception base class
 *
 * Phpr\SystemException is a base class for system exceptions.
 */
class SystemException extends Exception
{

}

/**
 * PHP Road Application Exception base class
 *
 * Phpr\ApplicationException is a base class for application exceptions.
 */
class ApplicationException extends Exception
{

}


/**
 * PHP Road Database Exception base class
 *
 * Phpr\DatabaseException is a base class for database exceptions.
 */
class DatabaseException extends Exception
{

}

/**
 * PHPR Deprecate Exception base class
 *
 * Phpr\DeprecateException is a base class for database exceptions.
 */
class DeprecateException extends Exception
{
    public $prev_trace;
    public $class_name;
    public $code_line;
    public $code_file;

    public function __construct($message = null, $code = 0)
    {
        $trace = $this->getTrace();

        if (!isset($trace[1])) {
            return;
        }

        $this->prev_trace = $prev_trace = (object)$trace[1];
        $this->class_name = isset($prev_trace->class) ? $prev_trace->class : null;
        $this->code_file = isset($prev_trace->file) ? $prev_trace->file : null;
        $this->code_line = isset($prev_trace->line) ? $prev_trace->line : null;

        parent::__construct($message, $code);
    }
}


/**
 * PHP Road Database Exception base class
 *
 * Phpr\DatabaseException is a base class for HTTP exceptions.
 */
class HttpException extends ApplicationException
{

    public $http_code;

    protected static $status_messages = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
    );

    /**
     * Creates a new Phpr\Exception instance.
     *
     * @param int $http_code HTTP code.
     * @param string $message message of the exception.
     * @param int $code Code of exception.
     */
    public function __construct($http_code, $message = null, $code = 0)
    {
        $this->http_code = $http_code;
        parent::__construct($message, $code);
    }

    /**
     * Outputs the error message along with a corresponding HTTP header.
     *
     * @param boolean $stop Stop script execution after outputting the message.
     * @param boolean $output_message Indicates whether the error message should be outputted before the.
     */
    public function output($stop = true, $output_message = true)
    {
        self::output_custom($this->http_code, $this->getMessage(), $stop, $output_message);
    }

    /**
     * Outputs custom HTTP header with a message
     *
     * @param int $http_code HTTP code.
     * @param string $message message of the exception.
     * @param boolean $stop Stop script execution after outputting the message.
     * @param boolean $output_message Indicates whether the error message should be outputted before the.
     */
    public static function output_custom($http_code, $message, $stop = true, $output_message = true)
    {
        $header_text = 'HTTP/1.1 ' . $http_code . ' ';
        if (isset(self::$status_messages[$http_code])) {
            $header_text .= self::$status_messages[$http_code];
        } else {
            $header_text .= $message;
        }

        header($header_text);

        if ($output_message) {
            echo $message;
        }

        if ($stop) {
            die;
        }
    }
}

/**
 * PHP Road PHP Exception class
 *
 * Phpr\PhpException represents the PHP Error.
 * PHP Road automatically converts all errors to exceptions of this class.
 * Use the getCode() method to obtain the PHP error number (E_WARNING, E_NOTICE and others).
 */
class PhpException extends SystemException
{

    /**
     * Creates a new Phpr\Exception instance.
     *
     * @param string $Message Message of exception.
     * @param int $Type Type of the PHP error.
     * @param string $File Source filename.
     * @param int $Line Source line.
     */
    public function __construct($Message, $Type, $File, $Line)
    {
        $this->file = $File;
        $this->line = $Line;

        parent::__construct($Message, $Type);
    }

    /**
     * Outputs a formatted exception string for display.
     *
     * @return string
     */
    public function __toString()
    {
        $result = null;

        $errorNames = array(
            E_WARNING => 'PHP Warning',
            E_NOTICE => 'PHP Notice',
            E_STRICT => 'PHP Strict Error',
            E_USER_ERROR => 'PHP User Error',
            E_USER_WARNING => 'PHP User Warning',
            E_USER_NOTICE => 'PHP User Notice',
        );

        $result = isset($errorNames[$this->code]) ? $errorNames[$this->code] : 'PHP Error';
        return $result . ': ' . $this->getMessage();
    }
}

/**
 * Validation exception class.
 *
 * Phpr\ValidationException represents a data validation error.
 */
class ValidationException extends ApplicationException
{
    public $validation;

    /**
     * Creates a new Phpr\ValidationException instance
     * @param Phpr\Validation $validation Validation object that caused the exception.
     */
    public function __construct(Validation $validation)
    {
        parent::__construct();
        $this->message = null;

        $this->validation = $validation;

        if ($validation->errorMessage !== null) {
            $this->message = $validation->errorMessage;
        }

        if (count($validation->fieldErrors)) {
            $keys = array_keys($validation->fieldErrors);

            if (strlen($this->message)) {
                $this->message .= PHP_EOL;
            }

            $this->message .= $validation->fieldErrors[$keys[0]];
        }
    }
}

/**
 * Execution exception class.
 * Thrown when trying to locate/parse a document
 * Eg. CMS content uses eval() to parse PHP code, and fails.
 *
 * Phpr\ExecutionException represents a document execution error
 */
class ExecutionException extends SystemException
{
    public $call_stack;
    public $code_line;
    public $location_desc;

    public function __construct($message, $call_stack, $line, $load_line_from_stack = false)
    {
        $call_stack = array_reverse($call_stack);
        $this->call_stack = $call_stack;
        $this->code_line = $line;
        $this->location_desc = '"' . $this->document_name() . '" (' . $this->document_type() . ')';

        if ($load_line_from_stack) {
            $trace = $this->getTrace();
            $count = count($trace);
            if ($count) {
                $this->code_line = $trace[0]['line'];
            }
        }

        parent::__construct($message);
    }

    public function stack_top()
    {
        return $this->call_stack[0];
    }

    public function document_type()
    {
        return $this->stack_top()->type;
    }

    public function document_name()
    {
        return $this->stack_top()->name;
    }

    public function document_code()
    {
        return $this->stack_top()->code;
    }
}


/**
 * PHP Road system error handler.
 * PHP Road automatically converts all errors to exceptions of class Phpr\PhpException.
 */
function SysErrorHandler($errno, $errstr, $errfile, $errline)
{

    $errorReporting = E_ALL;
    if (Phpr::$config !== null) {
        $errorReporting = Phpr::$config->get('ERROR_REPORTING', $errorReporting);
    }

    // Only throw a PHP Exception if it is listed in the ERROR_REPORTING configuration value
    $throwError = ($errorReporting & $errno) && (error_reporting() & $errno);

    //Ignore Declaration of warnings (legacy module support)
    if (strpos($errstr, 'Declaration of') === 0) {
        $throwError = false;
    }

    if ($throwError) {
        throw new PhpException($errstr, $errno, $errfile, $errline);
    }

    try {
        //Log and catch
        throw new PhpException($errstr, $errno, $errfile, $errline);
    } catch (\Exception $e) {
        // Do nothing
    }
}

/**
 * PHP Road system exception handler.
 * PHP Road uses this function to catch the unhandled exceptions and display the error page.
 */
function SysExceptionHandler($exception)
{
    try {
        Phpr::$response->openErrorPage($exception);
    } catch (\Exception $e) {
        print get_class($e) . " thrown within the exception handler. Message: " . $e->getMessage() . " on line " . $e->getLine() . " of " . $e->getFile();
    }
}

/**
 * Set the error and exception handlers
 */
set_error_handler('Phpr\SysErrorHandler');
set_exception_handler('Phpr\SysExceptionHandler');