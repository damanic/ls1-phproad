<?php
namespace Backend;

use Shop\Orders_Report;
use Cms\Analytics;

class ChartData extends DashboardController
{
    public function unique_visits()
    {
        $this->xmlData();
        $this->layout = null;
        $data = self::get_unique_visits_data();
        $this->viewData['sales_data'] = $data['sales'];
        $this->viewData['chart_data'] = $data['visits'];
    }

    public static function get_unique_visits_data()
    {
        $_this = new Backend_ChartData();
        $start = $_this->get_interval_start(true);
        $end = $_this->get_interval_end(true);
        $data = array(
            'sales' => Orders_Report::get_totals_chart_data($start, $end),
            'visits' => Analytics::getVisitorsChartData($start, $end),
        );
        return $data;
    }
}
