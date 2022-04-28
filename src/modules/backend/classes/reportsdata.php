<?php
namespace Backend;

use Backend;
use Phpr\Date;
use Phpr\DateTime as PhprDateTime;
use Db\Helper as DbHelper;

/**
 * @has_documentable_methods
 */
class ReportsData
{
    protected static $startReportingDate = false;
        
    public static function listReportYears()
    {
        $date = self::getEndReportingDate();
        $startDate = self::getStartReportingDate();
        if (!$startDate) {
            $startDate = $date;
        }
                
        $records =  DbHelper::objectArray('
				select 
					distinct year as name, 
					year_start as start, 
					year_end as end
				from 
					backend_report_dates
				where 
					backend_report_dates.report_date >= :start_date
					and backend_report_dates.report_date <= :now_date
				order by backend_report_dates.report_date
			', array('now_date'=>$date, 'start_date'=>$startDate));

        foreach ($records as $record) {
            $record->start = PhprDateTime::parse($record->start, PhprDateTime::universalDateFormat)->format('%x');
            $record->end = PhprDateTime::parse($record->end, PhprDateTime::universalDateFormat)->format('%x');
        }

        return $records;
    }
        
    public static function listReportMonths()
    {
        $date = self::getEndReportingDate();
        $startDate = self::getStartReportingDate();
        if (!$startDate) {
            $startDate = $date;
        }

        $records = DbHelper::objectArray('
				select 
					distinct month_start as name, 
					month_start as start, 
					month_end as end
				from 
					backend_report_dates
				where 
					backend_report_dates.report_date >= :start_date
					and backend_report_dates.report_date <= :now_date
				order by backend_report_dates.report_date
			', array('now_date'=>$date, 'start_date'=>$startDate));
            
        foreach ($records as $record) {
            $record->name = PhprDateTime::parse($record->name, PhprDateTime::universalDateFormat)->format('%n, %Y');
            $record->start = PhprDateTime::parse($record->start, PhprDateTime::universalDateFormat)->format('%x');
            $record->end = PhprDateTime::parse($record->end, PhprDateTime::universalDateFormat)->format('%x');
        }

        return $records;
    }
        
    protected static function getEndReportingDate()
    {
        $result = Date::userDate(PhprDateTime::now())->getDate();
            
        $api_dates = Backend::$events->fireEvent('shop:onGetEndReportingDate', $result->format(PhprDateTime::universalDateFormat));
        foreach ($api_dates as $api_date) {
            if ($api_date && is_string($api_date)) {
                $result = PhprDateTime::parse($api_date, PhprDateTime::universalDateFormat);
                break;
            }
        }
            
        return $result;
    }
        
    protected static function getStartReportingDate()
    {
        if (self::$startReportingDate !== false) {
            return self::$startReportingDate;
        }

        $result = DbHelper::scalar('select date(order_datetime) from shop_orders order by id limit 0,1');
            
        $api_dates = Backend::$events->fireEvent('shop:onGetStartReportingDate', $result);
        foreach ($api_dates as $api_date) {
            if ($api_date && is_string($api_date)) {
                $result = $api_date;
                break;
            }
        }
            
        return self::$startReportingDate = $result;
    }
        
    /**
     * Allows to override the reporting start date.
     * The event handler should return the new reporting start date as string. The default reporting start date
     * matches the first order date.
     * Example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onGetStartReportingDate', $this, 'get_start_reporting_date');
     * }
     *
     * public function get_start_reporting_date($default_date)
     * {
     *   return '2009-10-01';
     * }
     * </pre>
     * @event shop:onGetStartReportingDate
     * @package shop.events
     * @see shop:onGetEndReportingDate
     * @author LSAPP - MJMAN
     * @param string $default_date Specifies the default reporting start date calculated by LSAPP.
     * @return string Returns the new reporting start date in the following format: yyyy-mm-dd
     */
    private function event_onGetStartReportingDate($default_date)
    {
    }
            
    /**
     * Allows to override the reporting end date.
     * The event handler should return the new reporting end date as string. The default reporting end date
     * matches the current date in the application time zone.
     * Example:
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onGetEndReportingDate', $this, 'get_end_reporting_date');
     * }
     *
     * public function get_end_reporting_date($default_date)
     * {
     *   return '20015-10-01';
     * }
     * </pre>
     * @event shop:onGetEndReportingDate
     * @package shop.events
     * @see shop:onGetStartReportingDate
     * @author LSAPP - MJMAN
     * @param string $default_date Specifies the default reporting end date calculated by LSAPP.
     * @return string Returns the new reporting start date in the following format: yyyy-mm-dd
     */
    private function event_onGetEndReportingDate($default_date)
    {
    }
}
