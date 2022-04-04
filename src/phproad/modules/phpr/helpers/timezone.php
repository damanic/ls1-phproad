<?php

namespace Phpr;

use DateTimeZone;
use Phpr;

/*
 * TimeZone helper
 */

class TimeZone
{
    public static $timezones = null;

    public static function isValidTimezone($time_zone)
    {
        if (empty($time_zone)) {
            return false;
        }
        try {
            new \DateTimeZone($time_zone);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public static function getTimezoneList(): array
    {
        if (self::$timezones === null) {
            self::$timezones = Phpr::$session->get('Phpr\TimeZone::getTimezoneList', null);


            if (self::$timezones === null) {
                self::$timezones = array();
                $offsets = array();
                $now = new \DateTime();

                foreach (\DateTimeZone::listIdentifiers() as $timezone) {
                    $now->setTimezone(new \DateTimeZone($timezone));
                    $offsets[] = $offset = $now->getOffset();
                    self::$timezones[$timezone] = '(' . self::formatGmtOffset(
                            $offset
                        ) . ') ' . self::formatTimezoneName($timezone);
                }

                array_multisort($offsets, self::$timezones);
                Phpr::$session->set('Phpr\TimeZone::getTimezoneList', self::$timezones);
            }
        }

        return self::$timezones;
    }

    protected function formatGmtOffset($offset)
    {
        $hours = intval($offset / 3600);
        $minutes = abs(intval($offset % 3600 / 60));
        return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
    }

    protected function formatTimezoneName($name)
    {
        $name = str_replace('/', ', ', $name);
        $name = str_replace('_', ' ', $name);
        $name = str_replace('St ', 'St. ', $name);
        return $name;
    }
}
