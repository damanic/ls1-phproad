<?php
namespace Backend;

use Backend;
use Phpr\User_Parameters as UserParams;
use Phpr\DateTime as PhprDateTime;
use Phpr\Date as Date;
use Db\Helper as DbHelper;
use Phpr\DateTimeInterval as PhprDateTimeInterval;

/**
 * Back-end reporting controller generic class
 */
class ReportingController extends Controller
{
    public $implement = 'Db_ListBehavior, Db_FilterBehavior';
        
    public $list_model_class = 'Shop\Order';
    public $list_no_data_message = 'No orders found';
    public $list_items_per_page = 6;
    public $list_custom_prepare_func = null;
    public $list_no_js_declarations = false;
    public $list_sorting_column = null;
    public $list_record_url = null;
    public $list_render_as_tree = null;
    public $list_search_enabled = null;
    public $list_search_prompt = null;
    public $list_name = null;
    public $list_no_setup_link = false;
    public $list_options = array();
    public $list_control_panel_partial = null;

    protected $settingsDomain = 'dashboard';
        
    protected $globalHandlers = array('onSetRange', 'onUpdateData');
    protected $maxChartValue = 0;
        
    public $filter_list_title = 'Report Filters';
    public $filter_prompt = 'Please choose records to include to the report.';
    public $filter_onApply = 'updateReportData();';
    public $filter_onRemove = 'updateReportData();';
    public $filter_desc_max_len = 100;
    public $filter_filters = array();
    public $filter_switchers = array();

    public $report_interval = true;
    public $report_control_partial = null;
        
    public function __construct()
    {
        $this->check_report_dates();
        $this->addJavaScript('/modules/backend/resources/javascript/datepicker.js?'.module_build('backend'));
        $this->addCss('/modules/backend/resources/css/datepicker.css?'.module_build('backend'));
        $this->addCss('/modules/backend/resources/css/reports.css?'.module_build('backend'));
        parent::__construct();
        $this->layout = PATH_APP . '/modules/backend/layouts/report.htm';
        $this->app_tab = 'reports';
        $this->app_module = 'backend';
        $this->list_control_panel_partial = PATH_APP . '/modules/backend/layouts/report_list_control_panel.htm';
        Backend::$events->fireEvent('backend:onExtendReportingController', $this);
    }

    public function export_list($format = null, $file_name = 'report_list')
    {
        $this->list_name = get_class($this).'_index_list';
        $options = array();
        $options['iwork'] = $format == 'iwork';
        $file_name = $file_name ? $file_name.'.csv' : 'report_list.csv';
        $this->listExportCsv($file_name, $options);
    }

    protected function index_onUpdateTotals()
    {
        $this->renderReportTotals();
    }

    protected function renderReportTotals()
    {
    }

    protected function get_interval_start()
    {
        $result = UserParams::get($this->settingsDomain.'_report_int_start');

        if (!strlen($result)) {
            return Date::firstYearDate(PhprDateTime::now())->format('%x');
        }

        return $result;
    }
        
    protected function get_interval_end()
    {
        return UserParams::get($this->settingsDomain.'_report_int_end', null, PhprDateTime::now()->format('%x'));
    }

    protected function get_interval_type()
    {
        return UserParams::get($this->settingsDomain.'_report_int_type', null, 'day');
    }
        
    protected function get_interval_ranges()
    {
        return UserParams::get($this->settingsDomain.'_report_int_ranges');
    }
        
    protected function set_interval_start($value)
    {
        UserParams::set($this->settingsDomain.'_report_int_start', $value);
    }
        
    protected function set_interval_end($value)
    {
        UserParams::set($this->settingsDomain.'_report_int_end', $value);
    }

    protected function set_interval_type($value)
    {
        UserParams::set($this->settingsDomain.'_report_int_type', $value);
    }

    protected function set_interval_ranges($value)
    {
        UserParams::set($this->settingsDomain.'_report_int_ranges', $value);
    }
        
    protected function onSetRange()
    {
        $this->set_interval_start(post('interval_start'));
        $this->set_interval_end(post('interval_end'));
        $this->set_interval_type(post('interval_type'));
        $this->set_interval_ranges(post('interval_ranges'));

        $this->listRenderTable();
    }
        
    protected function intervalQueryStr()
    {
        $start = PhprDateTime::parse($this->get_interval_start(), '%x')->toSqlDate();
        $end = PhprDateTime::parse($this->get_interval_end(), '%x')->toSqlDate();
            
        $result = " report_date >= '$start' and report_date <= '$end'";
        return $result;
    }
        
    protected function intervalQueryStrOrders()
    {
        $start = PhprDateTime::parse($this->get_interval_start(), '%x')->toSqlDate();
        $end = PhprDateTime::parse($this->get_interval_end(), '%x')->toSqlDate();
            
        $result = " order_date >= '$start' and order_date <= '$end'";
        return $result;
    }

    /*
         * Data helpers
     */
        
    protected function addToArray(&$arr, $key, &$value, $keyParams = array())
    {
        if (!array_key_exists($key, $arr)) {
            $arr[$key] = (object)array('values'=>array(), 'params'=>$keyParams);
        }
                
        $arr[$key]->values[] = $value;
    }
        
    protected function addMaxValue($value)
    {
        $this->maxChartValue = max($value, $this->maxChartValue);
        return $value;
    }

    protected function check_report_dates()
    {
        $ok = DbHelper::scalar('SELECT backend_report_dates FROM report_dates WHERE report_date = CURDATE()');
        if ($ok) {
            return;
        }
        $this->generate_report_dates();
    }

    /**
     * Generates report dates from the last existing report date
     * for 5 years in the future.
     */
    protected function generate_report_dates()
    {
        $last_date = DbHelper::scalar('select report_date from backend_report_dates order by report_date desc limit 0, 1');
        $date = PhprDateTime::parse($last_date, PhprDateTime::universalDateFormat)->addDays(1);

        $interval = new PhprDateTimeInterval(1);
        $prevMonthCode = -1;
        $prevYear = $date->getYear();
        $prevYearCode = -1;

        $five_years_in_days = 1825;

        for ($i = 1; $i <= $five_years_in_days; $i++) {
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

            $monthCode = $year.'.'.$month;
            if ($prevMonthCode != $monthCode) {
                $monthStart = $date->format('%Y-%m-01');
                $monthFormatted = $date->format('%m.%Y');
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
                    'report_date'=>$date->toSqlDate(),
                    'year'=>$year,
                    'month'=>$date->getMonth(),
                    'day'=>$date->getDay(),
                    'month_start'=>$monthStart,
                    'month_code'=>$monthCode,
                    'month_end'=>$monthEnd,
                    'year_start'=>$yearStart,
                    'year_end'=>$yearEnd
                )
            );
            $date = $date->addInterval($interval);
        }
    }
}
