<?php
namespace Phpr;

use Phpr;
use \Db as Db;
/**
 * PHPR Error Log Class
 *
 * Allows writing the error messages to the error log file.
 *
 * To enable the error logging set the ERROR_LOG config value to true:
 *
 *   $CONFIG['ERROR_LOG'] = true;
 *
 * By default the error log file is located in the logs directory (logs/errors.txt).
 * You can specify a different location by setting the ERROR_LOG_FILE config value:
 *
 *   $CONFIG['ERROR_LOG_FILE'] = "/home/logs/private_errors.txt".
 *
 */
class ErrorLog
{
    private $logFileName;
    private $isEnabled;
    private $ignoreExceptions;

    public static $disable_db_logging = false;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    public static function encode_error_details($value)
    {
        $value = json_encode($value);
        if (class_exists('\Phpr\SecurityFramework')) {
            $security = SecurityFramework::create();
            list($key_1, $key_2) = Phpr::$config->get('ADDITIONAL_ENCRYPTION_KEYS', array('jd$5ka#1', '9ao@!d4k'));
            $value = $security->encrypt($value, $key_1, $key_2);
        }
        return base64_encode($value);
    }

    public static function decode_error_details($value)
    {
        $value = base64_decode($value);

        if (class_exists('\Phpr\SecurityFramework')) {
            $security = SecurityFramework::create();
            list($key_1, $key_2) = Phpr::$config->get('ADDITIONAL_ENCRYPTION_KEYS', array('jd$5ka#1', '9ao@!d4k'));
            $value = $security->decrypt($value, $key_1, $key_2);
        }

        return json_decode($value);
    }

    public static function get_exception_details($exception)
    {
        $error = (object)array(
            'call_stack' => array(),
            'class_name' => get_class($exception),
            'log_id' => isset($exception->log_id) ? $exception->log_id : '',
            'log_status' => isset($exception->log_status) ? $exception->log_status : '',
            'message' => ucfirst(nl2br(htmlentities($exception->getMessage()))),
            'hint' => isset($exception->hint_message) && strlen(
                $exception->hint_message
            ) ? $exception->hint_message : null,
            'is_document' => $exception instanceof ExecutionException,
            'document' => $exception instanceof ExecutionException ? $exception->document_name(
            ) : \FileSystem\Path::getPublicPath($exception->getFile()),
            'document_type' => $exception instanceof ExecutionException ? $exception->document_type(
            ) : 'PHP document',
            'line' => $exception instanceof ExecutionException ? $exception->code_line : $exception->getLine(),
            'code_highlight' => (object)array(
                'brush' => $exception instanceof ExecutionException ? 'php' : 'php',
                'lines' => array()
            )
        );

        // code highlight
        $code_lines = null;

        if ($exception instanceof ExecutionException) {
            $code_lines = explode("\n", $exception->document_code());

            foreach ($code_lines as $i => $line) {
                $code_lines[$i] .= "\n";
            }

            $error_line = $exception->code_line - 1;
        } else {
            $file = $exception->getFile();
            if (file_exists($file) && is_readable($file)) {
                $code_lines = @file($file);
                $error_line = $exception->getLine() - 1;
            }
        }

        if ($code_lines) {
            $start_line = $error_line - 6;
            if ($start_line < 0) {
                $start_line = 0;
            }

            $end_line = $start_line + 12;
            $line_num = count($code_lines);
            if ($end_line > $line_num - 1) {
                $end_line = $line_num - 1;
            }

            $code_lines = array_slice($code_lines, $start_line, $end_line - $start_line + 1);

            $error->code_highlight->start_line = $start_line;
            $error->code_highlight->end_line = $end_line;
            $error->code_highlight->error_line = $error_line;

            foreach ($code_lines as $i => $line) {
                $error->code_highlight->lines[$start_line + $i] = $line;
            }
        }

        // stack trace
        if ($error->is_document) {
            $last_index = count($exception->call_stack) - 1;

            foreach ($exception->call_stack as $index => $stack_item) {
                $error->call_stack[] = (object)array(
                    'id' => $last_index - $index + 1,
                    'document' => h($stack_item->name),
                    'type' => h($stack_item->type)
                );
            }
        } else {
            $trace_info = $exception->getTrace();
            $last_index = count($trace_info) - 1;

            foreach ($trace_info as $index => $event) {
                $functionName = (isset($event['class']) && strlen(
                    $event['class']
                )) ? $event['class'] . $event['type'] . $event['function'] : $event['function'];

                if ($functionName == 'Phpr\SysErrorHandler' || $functionName == 'Phpr\SysExceptionHandler') {
                    continue;
                }

                $file = isset($event['file']) ? \FileSystem\Path::getPublicPath($event['file']) : null;
                $line = isset($event['line']) ? $event['line'] : null;

                $args = null;
                if (isset($event['args']) && count($event['args'])) {
                    $args = Exception::_formatTraceArguments($event['args'], false);
                }

                $error->call_stack[] = (object)array(
                    'id' => $last_index - $index + 1,
                    'function_name' => $functionName,
                    'args' => $args ? $args : '',
                    'document' => $file,
                    'line' => $line
                );
            }
        }

        return $error;
    }

    /**
     * Writes an exception information to the log file.
     *
     * @param  Exception $exception Specifies the exception to log.
     * @return boolean Returns true if exception was logged successfully.
     */
    public function logException(Exception $exception)
    {
        if (!$this->isEnabled) {
            return false;
        }

        foreach ($this->ignoreExceptions as $IgnoredExceptionClass) {
            if ($exception instanceof $IgnoredExceptionClass) {
                return false;
            }
        }

        switch ($exception) {
            case ($exception instanceof DeprecateException):
                $message = sprintf(
                    "%s: %s. ",
                    get_class($exception),
                    $exception->getMessage()
                );

                if ($exception->code_file && $exception->code_line) {
                    $message .= sprintf(
                        "In %s, line %s",
                        $exception->code_file,
                        $exception->code_line
                    );
                }
                break;

            case ($exception instanceof ExecutionException):
                $message = sprintf(
                    "%s: %s. In %s, line %s",
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->location_desc,
                    $exception->code_line
                );
                break;

            default:
                $message = sprintf(
                    "%s: %s. In %s, line %s",
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                );
                break;
        }


        $error = self::get_exception_details($exception);
        $log_to_db = !($exception instanceof DatabaseException);

        $details = null;

        if (Phpr::$config->get('ENABLE_DB_ERROR_DETAILS', true)) {
            $details = self::encode_error_details($error);
        }

        return $this->writeLogMessage($message, $log_to_db, $details);
    }

    /**
     * Writes a message to the error log.
     * You may override this method in the inherited class and write messages to a database table.
     *
     * @param  string  $message   A message to write.
     * @param bool $log_to_db Whether to log to the database.
     * @param  string  $details   The error details string.
     * @return array Returns array containing the db error log id and the status of error log write
     */
    protected function writeLogMessage($message, $log_to_db = true, $details = null)
    {
        $record_id = null;

        if (!class_exists('\FileSystem\Log') && !Phpr::$classLoader->load('\FileSystem\Log')) {
            echo $message;
        }

        if ($log_to_db && Phpr::$config->get('LOG_TO_DB') && !self::$disable_db_logging) {
            if (!Db::getActiveConnection()) {
                Db::sql()->driver()->connect();
            }

            if (Db::getActiveConnection()) {
                if (!class_exists('Phpr\\Trace_Log_Record') && !Phpr::$classLoader->load('Phpr\\Trace_Log_Record')) {
                    return;
                }
                $record_id = Trace_Log_Record::add('ERROR', $message, $details)->id;
            }
        }

        if (Phpr::$config->get('ENABLE_ERROR_STRING', true)) {
            $message .= ($details ? ' Encoded details: ' . $details : '');
        }

        return array('id' => $record_id, 'status' => \FileSystem\Log::writeLine($this->logFileName, $message));
    }

    /**
     * Loads the error log configuration
     */
    protected function loadConfiguration()
    {
        // Determine if the error log is enabled
        //
        $this->isEnabled = Phpr::$config !== null && Phpr::$config->get("ERROR_LOG", false);

        if ($this->isEnabled) {
            // Load the log file path
            //
            $this->logFileName = Phpr::$config->get("ERROR_LOG_FILE", PATH_APP . "/logs/errors.txt");

            // Check whether the file and directory are writable
            //
            if (file_exists($this->logFileName)) {
                if (!is_writable($this->logFileName)) {
                    $exception = new SystemException('The error log file is not writable: ' . $this->logFileName);
                    $exception->hint_message = 'Please assign writing permissions on the error log file.';
                    throw $exception;
                }
            } else {
                $directory = dirname($this->logFileName);
                if (!is_writable($directory)) {
                    $exception = new SystemException(
                        'The error log file directory is not writable: ' . $directory
                    );
                    $exception->hint_message = 'Please assign writing permissions on the error log directory.';
                    throw $exception;
                }
            }
        }

        // Load the ignored exceptions list
        //
        $this->ignoreExceptions = Phpr::$config !== null ? Phpr::$config->get("ERROR_IGNORE", array()) : array();
    }
}
