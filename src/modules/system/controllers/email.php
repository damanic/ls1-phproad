<?php
namespace System;

use Phpr;
use Phpr\ApplicationException;
use Backend\SettingsController;
use Users\Group as UserGroup;
use Core\Email as CoreEmail;
use Backend\Html;

class Email extends SettingsController
{
    protected $access_for_groups = array(UserGroup::ADMIN);
    public $implement = 'Db_FormBehavior';

    public $form_edit_title = 'Email Settings';
    public $form_model_class = 'System\EmailParams';
        
    public $form_redirect = null;
    public $form_edit_save_flash = 'Email configuration has been saved.';

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
        $this->form_redirect = url('system/settings/');
    }

    public function index()
    {
        try {
            $record = EmailParams::get();
            if (!$record) {
                throw new ApplicationException('Email configuration is not found.');
            }
                
            $this->edit($record->id);
            $this->app_page_title = $this->form_edit_title;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function index_onSave()
    {
        $record = EmailParams::get();
        $this->edit_onSave($record->id);
    }

    protected function index_onTest()
    {
        try {
            $obj = EmailParams::get();
            $classId = get_class_id($this->form_model_class);
            $form_data = post($classId, array());
                
            if (array_key_exists('smtp_password', $form_data) && strlen($form_data['smtp_password'])) {
                $form_data['smtp_password'] = base64_encode($form_data['smtp_password']);
            } else {
                $form_data['smtp_password'] = $obj->smtp_password;
            }
                
            $obj->validate_data($form_data, $this->formGetEditSessionKey());
            $viewData = array();
            CoreEmail::send(
                'system',
                'test_message',
                $viewData,
                'LSAPP test notification',
                $this->currentUser->short_name,
                $this->currentUser->email,
                array(),
                $obj
            );
            echo Html::flash_message('The test message has been successfully sent.');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
