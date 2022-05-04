<?php
namespace Phpr;

use DateTimeZone;

use Phpr;
use Phpr\TimeZone;
use Phpr\DateTime as PhprDateTime;
use Phpr\DateTimeInterval;
use Phpr\SystemException;

/*
 * Date and time helper
 */

class Date
{
    protected static $user_time_zone = null;

    public static function lastMonthDate($Date)
    {
        $Year = $Date->getYear();
        $Month = $Date->getMonth();

        $DaysInMonth = $Date->daysInMonth($Year, $Month);
        $Result = new PhprDateTime();
        $Result->setDate($Year, $Month, $DaysInMonth);

        return $Result;
    }

    public static function firstMonthDate($Date)
    {
        $Year = $Date->getYear();
        $Month = $Date->getMonth();

        $Result = new PhprDateTime();
        $Result->setDate($Year, $Month, 1);

        return $Result;
    }

    public static function firstYearDate($Date)
    {
        $Year = $Date->getYear();

        $Result = new PhprDateTime();
        $Result->setDate($Year, 1, 1);

        return $Result;
    }

    public static function firstWeekDate($Date)
    {
        $Days = $Date->getDayOfWeek() - 1;
        $Interval = new DateTimeInterval($Days);
        return $Date->substractInterval($Interval);
    }


    public static function lastWeekDate($Date)
    {
        $Days = 7 - $Date->getDayOfWeek();
        $Interval = new DateTimeInterval($Days);
        return $Date->addInterval($Interval);
    }

    public static function firstDateOfPrevMonth($Date)
    {
        $OneDayInterval = new DateTimeInterval(1);
        return self::firstMonthDate($Date->substractInterval($OneDayInterval));
    }

    public static function firstDateOfNextMonth($Date)
    {
        $OneDayInterval = new DateTimeInterval(1);

        $Result = self::lastMonthDate($Date);
        return $Result->addInterval($OneDayInterval);
    }

    /**
     * Converts a date from GMT to a time zone specified
     * in the configuration file and outputs it according
     * a specified format
     */
    public static function display($dateObj, $format = "%x")
    {
        if (!$dateObj) {
            return null;
        }

        $timeZoneObj = self::getUserTimezone();
        $dateObj->setTimeZone($timeZoneObj);
        unset($timeZoneObj);

        return $dateObj->format($format);
    }

    /**
     * Converts GMT datetime to a time zone specified in the configuration gile
     */
    public static function userDate($dateObj)
    {
        $timeZoneObj = self::getUserTimezone();
        $dateObj->setTimeZone($timeZoneObj);
        unset($timeZoneObj);

        return $dateObj;
    }

    /**
     * Returns a timezone object
     */
    public static function getUserTimezone()
    {
        if (self::$user_time_zone !== null) {
            return self::$user_time_zone;
        }

        $timeZone = Phpr::$config->get('TIMEZONE', 'GMT');
        try {
            return self::$user_time_zone = new DateTimeZone($timeZone);
        } catch (\Exception $Ex) {
            throw new SystemException(
                'Invalid time zone specified in config.php: ' . $timeZone . '. 
                Please refer this document for the list of correct time zones: http://docs.php.net/timezones.'
            );
        }
    }

    /**
     * Returns true if the $dateObj represents today date
     *
     * @param Phpr\DateTime $dateObj Specifies a date object in GMT timezone
     */
    public static function isToday($dateObj)
    {
        $nowMn = PhprDateTime::now()->getDate();
        $dateMn = $dateObj->getDate();
        $interval = $dateMn->substractDateTime($nowMn);
        return $interval->getDays() == 0;
    }

    /**
     * Returns true if the $dateObj represents yesterday date
     *
     * @param Phpr\DateTime $dateObj Specifies a date object in GMT timezone
     */
    public static function isYesterday($dateObj)
    {
        $nowMn = PhprDateTime::now()->getDate();
        $dateMn = $dateObj->getDate();
        $interval = $dateMn->substractDateTime($nowMn);

        return $interval->getDays() == -1;
    }

    public static function asGmt($dateObj)
    {
        return $dateObj->format("%Y-%m-%dT%H:%M:%S+00:00");
    }
}
