<?php
namespace Backend;

use Phpr;

class ReportsController extends Controller
{
    public function index()
    {
        $reports = Reports::listReports();
        if (!count($reports)) {
            Phpr::$response->redirect(url());
        }

        $first_report_info = Reports::getFirstReportInfo();
        if ($first_report_info) {
            $url = url($first_report_info['module_id'].'/'.$first_report_info['report_id'].'_report');
            Phpr::$response->redirect($url);
        }
            
        Phpr::$response->redirect(url());
    }
}
