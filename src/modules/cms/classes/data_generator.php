<?php
namespace Cms;

use Phpr\ApplicationException;
use Phpr\DateTimeInterval;
use Phpr\DateTime as PhprDateTime;

class Data_Generator
{
    // Data_Generator::generate_page_visits_file(
    //  PATH_APP.'/logs/visits.sql',
    //  new PhprDateTime('2008-01-01 00:00:00'),
    //  500,
    //  365);
    public static function generate_page_visits_file($sql_file_path, $start_date, $visits_per_day, $days)
    {
        if (file_exists($sql_file_path)) {
            unlink($sql_file_path);
        }
            
        set_time_limit(3600);
            
        if (!($fp = @fopen($sql_file_path, 'a'))) {
            throw new ApplicationException('Error creating file');
        }

        @fwrite($fp, "truncate table cms_page_visits; \n");
        @fwrite($fp, "insert into cms_page_visits(url, visit_date, ip, page_id) values \n");

        $page_id_urls = array();
        $pages = Page::create()->find_all();
        foreach ($pages as $page) {
            $page_id_urls[$page->id] = $page->url;
        }

        $page_count = count($pages);
        $page_ids = array_keys($page_id_urls);

        $ips = array();

        $current_date = $start_date;
        $interval = new DateTimeInterval(1);
        for ($day_index = 1; $day_index <= $days; $day_index++) {
            $day_visits = rand($visits_per_day - round($visits_per_day/3), $visits_per_day + round($visits_per_day/3));
            $day_visits = sin(deg2rad(($day_index % 7)*25.7))*$day_visits + rand($day_visits/5, $day_visits/5);
            $date = $current_date->toSqlDate();

            for ($visit_index = 1; $visit_index <= $day_visits; $visit_index++) {
                $page_index = rand(0, $page_count-1);
                $page_id = $page_ids[$page_index];
                $url = $page_id_urls[$page_id];
                    
                if (rand(1, 10) < 2 || !count($ips)) {
                    $ips[] = $ip = self::genIp();
                } else {
                    $ip = $ips[rand(0, count($ips)-1)];
                }

                $str = "('$url', '$date', '$ip', $page_id)";
                if (!($day_index == $days && $visit_index = $day_visits)) {
                    $str .= ",\n";
                }

                @fwrite($fp, $str);
            }

            $current_date = $current_date->addInterval($interval);
        }

        fclose($fp);
        return true;
    }
        
    protected static function genIp()
    {
        return rand(1, 255).'.'.rand(1, 255).'.'.rand(1, 255).'.'.rand(1, 255);
    }
        
    public static function generate_totals_chart_data($visit_data, $start_date)
    {
        $visitors_per_date = array();
        foreach ($visit_data as &$obj) {
            $obj->record_value *= 10;
            $visitors_per_date[$obj->series_id] = $obj->record_value;
        }

        $sales_data = array();
        $current_date = $start_date;
        $interval = new DateTimeInterval(1);
        $days = 31;
        for ($day_index = 1; $day_index <= $days; $day_index++) {
            $date_str = $current_date->format(PhprDateTime::universalDateFormat);
            $value = $visitors_per_date[$date_str]*2*rand(1, 5) + rand(1000, 2000)-1000;
                
            $entry = array(
                'graph_code'=>'amount',
                'graph_name'=>'amount',
                'series_id'=>$date_str,
                'series_value'=>$date_str,
                'record_value'=>$value
            );
                
            $sales_data[] = (object)$entry;
            $current_date = $current_date->addInterval($interval);
        }
            
        return $sales_data;
    }
}
