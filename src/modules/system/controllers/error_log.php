<?php
namespace System;

use Phpr;
use Phpr\ErrorLog as PhprErrorLog;
use Db\Helper as DbHelper;
use Backend\SettingsController;
use Users\Group as UserGroup;

class Error_Log extends SettingsController
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior';
    public $list_model_class = 'Phpr\Trace_Log_Record';
    public $list_record_url = null;

    public $form_preview_title = 'Preview';
    public $form_model_class = 'Phpr\Trace_Log_Record';
    public $form_not_found_message = 'Record not found';
    public $form_redirect = null;

    protected $access_for_groups = array(UserGroup::ADMIN);
    protected $public_actions = array('cron');

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
        $this->app_module_name = 'System';
        $this->list_record_url = url('/system/error_log/preview/');
        $this->form_redirect = url('/system/error_log/');
    }
        
    public function index()
    {
        try {
            $this->app_page_title = 'Error Log';
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    protected function index_onClear()
    {
        try {
            DbHelper::query('delete from trace_log where log="ERROR"');
            Phpr::$session->flash['success'] = 'Error log records have been successfully deleted.';
            Phpr::$response->redirect(url('/system/error_log/'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
        
    public function decoder()
    {
        try {
            $this->app_page_title = 'Decoder';
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    protected function decoder_onDecode()
    {
        try {
            $this->viewData['error'] = PhprErrorLog::decode_error_details(post('encoded_string'));
            $this->renderPartial('decoded_details');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
