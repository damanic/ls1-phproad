<?php
namespace Core;

use Phpr\Cron as PhprCron;

/**
 * @deprecated
 * Use: Phpr\Cron
 */
class Cron extends PhprCron
{

    /**
     * @deprecated
     */
    private function event_on_execute_cron_exception($ex)
    {
    }

    /**
     * @deprecated
     * Use: phpr:on_execute_cronjob_exception
     */
    private function event_on_execute_cronjob_exception($ex, $job)
    {
    }

    /**
     * @deprecated
     * Use: phpr:on_execute_crontab_exception
     */
    private function event_on_execute_crontab_exception($ex, $record_code)
    {
    }

    /**
     * @deprecated
     * Use: phpr:on_cronjob_shutdown
     */
    private function event_on_cronjob_shutdown($job, $error)
    {
    }

    /**
     * @deprecated
     * Use: phpr:on_cronjob_exceeded_max_duration
     */
    private function event_on_cronjob_exceeded_max_duration($job)
    {
    }

    //
    //The following methods maintain deprecated Core\Cron events.
    //
    public function onExecuteCronException($ex)
    {
        Phpr::$events->fire_event('core:on_execute_cron_exception', $ex);
    }
    public function onExecuteCrontabException($ex, $code)
    {
        Phpr::$events->fire_event('core:on_execute_crontab_exception', $ex, $code);
    }
    public function onExecuteCronjobException($ex, $job)
    {
        Phpr::$events->fire_event('core:on_execute_cronjob_exception', $ex, $job);
    }
    public function onCronjobShutdown($job, $error)
    {
        Phpr::$events->fire_event('core:on_cronjob_shutdown', $job, $error);
    }
    public function onCronjobExceededMaxDuration($job)
    {
        Phpr::$events->fire_event('core:on_cronjob_exceeded_max_duration', $job);
    }
}
