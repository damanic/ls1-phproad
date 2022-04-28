<?php
use Phpr\DateTime as PhprDateTime;
use Phpr\DateTimeInterval as PhprDateTimeInterval;
use Phpr\Date as Date;
use Db\Helper as DbHelper;

$tables = Db\Helper::listTables();

//report_dates ->  backend_report_dates
if (in_array('report_dates', $tables) && in_array('backend_report_dates', $tables)) {
    if (Db\Helper::scalar('SELECT COUNT(*) FROM `backend_report_dates`') == 0) {
        Db\Helper::query('INSERT INTO `backend_report_dates` SELECT * FROM `report_dates`');
    }
}

if (in_array('backend_report_dates', $tables)) {
    if (Db\Helper::scalar('SELECT COUNT(*) FROM `backend_report_dates`') == 0) {
        $date = new PhprDateTime();
        $date->setDate(2008, 1, 1);

        $interval = new PhprDateTimeInterval(1);
        $prevMonthCode = -1;
        $prevYear = 2008;
        $prevYearCode = -1;

        for ($i = 1; $i <= 3650; $i++) {
            $year = $date->getYear();
            $month = $date->getMonth();

            if ($prevYear != $year) {
                $prevYear = $year;
            }

            if ($prevYearCode != $year) {
                $prevYearCode = $year;
                $yDate = new PhprDateTime();
                $yDate->setDate($year, 1, 1);
                $yearStart = $yDate->toSqlDate();

                $yDate->setDate($year, 12, 31);
                $yearEnd = $yDate->toSqlDate();
            }

            /*
             * Months
             */

            $monthCode = $year . '.' . $month;
            if ($prevMonthCode != $monthCode) {
                $monthStart = $date->toSqlDate();
                $prevMonthCode = $monthCode;
                $monthEnd = Date::lastMonthDate($date)->toSqlDate();
            }

            DbHelper::query(
                "insert into backend_report_dates(report_date, year, month, day, 
				month_start, month_code, month_end, year_start, year_end) 
				values (:report_date, :year, :month, :day, 
				:month_start, :month_code, :month_end,
				:year_start, :year_end)",
                array(
                    'report_date' => $date->toSqlDate(),
                    'year' => $year,
                    'month' => $date->getMonth(),
                    'day' => $date->getDay(),
                    'month_start' => $monthStart,
                    'month_code' => $monthCode,
                    'month_end' => $monthEnd,
                    'year_start' => $yearStart,
                    'year_end' => $yearEnd
                )
            );
            $date = $date->addInterval($interval);
        }
    }
}
