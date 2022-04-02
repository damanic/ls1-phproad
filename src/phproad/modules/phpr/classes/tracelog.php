<?php
namespace Phpr;

use Phpr;
use Phpr\SystemException;
use FileSystem\Log as File_Log;

/**
 * PHPR Trace Log Class
 *
 * Allows writing of traceable messages to trace log files and/or database
 *
 * To configure the trace log use the TRACE_LOG parameter in the application configuration file:
 *
 *   $CONFIG["TRACE_LOG"]["BLOG"] = PATH_APP."/logs/blog.txt";
 *   $CONFIG["TRACE_LOG"]["DEBUG"] = PATH_APP."/logs/debug.txt";
 *
 * The second-level key determines the listener name. Use the listener names to write tracing
 * messages to different files:
 *
 *   Phpr::$trace_log->write('My traceable message', 'BLOG');
 *   trace_log('My traceable message', 'BLOG');
 *
 * The instance of this class is available in the Phpr global object: Phpr::$trace_log.
 *
 * You can instruct PHPR to write to the database only by setting the file path to null:
 *
 *   $CONFIG["TRACE_LOG"]["BLOG"] = null;
 *
 */
class TraceLog
{
    private $listeners;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Loads the error log configuration
     */
    protected function loadConfiguration()
    {
        $this->listeners = array();

        foreach (Phpr::$config->get("TRACE_LOG", array()) as $listenerName => $filePath) {
            $this->addListener($listenerName, $filePath);
        }
    }

    /**
     * Writes a tracing message to a log file.
     *
     * @param  mixed  $Message  Specifies a message to log. The message can be an object, array or string.
     * @param  string $Listener Specifies a listener name to use. If this parameter is omitted, the first listener will be used.
     * @return boolean Returns true if message was logged successfully.
     */
    public function write($Message, $Listener = null)
    {
        if (!count($this->listeners)) {
            return false;
        }

        // Evaluate the listener name and ensure whether it exists
        //
        if ($Listener === null) {
            $keys = array_keys($this->listeners);
            $Listener = $keys[0];
        } else {
            if (!array_key_exists($Listener, $this->listeners)) {
                return false;
            }
        }

        // Convert the message to string
        //
        if (is_array($Message) || is_object($Message)) {
            $Message = print_r($Message, true);
        }

        // Write the message
        //
        return $this->writeLogMessage($Message, $Listener);
    }

    public function addListener($listenerName, $filePath)
    {
        if (!Phpr::$config->get('NO_TRACELOG_CHECK')) {
            if ($filePath !== null) {
                // Check whether the file or directory is writable
                //
                if (file_exists($filePath)) {
                    if (!is_writable($filePath)) {
                        $exception = new SystemException('The trace log file is not writable: ' . $filePath);
                        $exception->hint_message = 'Please assign writing permissions on the trace log file.';
                        throw $exception;
                    }
                } else {
                    $directory = dirname($filePath);
                    if (!is_writable($directory)) {
                        $exception = new SystemException(
                            'The trace log file directory is not writable: ' . $directory
                        );
                        $exception->hint_message = 'Please assign writing permissions on the trace log directory.';
                        throw $exception;
                    }
                }
            }
        }

        $this->listeners[$listenerName] = $filePath;
    }

    /**
     * Writes a message to the trace log.
     * You may override this method in the inherited class and write messages to a database table.
     *
     * @param  string $Message  A message to write.
     * @param  string $Listener Specifies a listener name to use.
     * @return boolean Returns true if the message was logged successfully.
     */
    protected function writeLogMessage($Message, $Listener)
    {
        if ($this->listeners[$Listener] !== null) {
            return File_Log::writeLine($this->listeners[$Listener], $Message);
        } else {
            if (!class_exists('Phpr\\Trace_Log_Record') && !Phpr::$class_loader->load('Phpr\\Trace_Log_Record')) {
                return;
            }

            Phpr_Trace_Log_Record::add($Listener, $Message);
        }
    }
}
