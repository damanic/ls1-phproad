<?

class Phpr_DebugHelper
{
    protected static $start_times = array();
    protected static $incremental = array();

    public static $listener = 'INFO';

    public static function start_timing($name)
    {
        self::$start_times[$name] = microtime(true);
    }

    public static function end_timing($name, $message = null, $add_memory_usage = false, $reset_timer = true)
    {
        $time_end = microtime(true);

        $message = $message ? $name . ' - ' . $message : $name;
        $time = $time_end - self::$start_times[$name];

        if ($add_memory_usage) {
            $message .= ' Peak memory usage: ' . Phpr_Files::fileSize(memory_get_peak_usage());
        }

        if ($reset_timer) {
            self::start_timing($name);
        }

        self::timing_trace_log($time, $message);
    }

    public static function increment($name)
    {
        $time_end = microtime(true);
        $time = $time_end - self::$start_times[$name];
        if (!array_key_exists($name, self::$incremental)) {
            self::$incremental[$name] = 0;
        }

        self::$incremental[$name] += $time;
    }

    public static function end_incremenral_timing($name, $message = null)
    {
        $message = $message ? $message : $name;
        $time = self::$incremental[$name];
        self::timing_trace_log($time, $message);
    }

    public static function backtrace()
    {
        $trace = debug_backtrace();
        $data = array();
        foreach ($trace as $trace_step) {
            if (isset($trace_step['file'])) {
                $data[] = basename(
                        $trace_step['file']
                    ) . ' #' . $trace_step['line'] . ' ' . $trace_step['function'] . '()';
            } else {
                $data[] = $trace_step['function'] . '()';
            }
        }

        Phpr::$traceLog->write(implode("\n", $data), self::$listener);
    }

    protected static function timing_trace_log($microtime, $msg)
    {
        $hours = (int)($microtime / 60 / 60);
        $minutes = (int)($microtime / 60) - $hours * 60;
        $seconds = $microtime - $hours * 60 * 60 - $minutes * 60;
        $seconds = number_format((float)$seconds, 3, '.', '');
        Phpr::$traceLog->write('[microtime: ' . $microtime . '][seconds: ' . $seconds . '] ' . $msg, self::$listener);
    }
}

?>