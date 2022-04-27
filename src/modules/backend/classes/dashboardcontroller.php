<?php
namespace Backend;

class DashboardController extends ReportingController
{
    protected function get_interval_start($as_date = true)
    {
        return Dashboard::get_interval_start($as_date);
    }
        
    protected function get_interval_end($as_date = false)
    {
        return Dashboard::get_interval_end($as_date);
    }
        
    protected function get_active_interval_start()
    {
        return Dashboard::get_active_interval_start(true);
    }
}
