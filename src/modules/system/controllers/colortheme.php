<?php
namespace System;

use Backend\SettingsController;
use Users\Groups as UserGroups;
use Phpr\ApplicationException;

class ColorTheme extends SettingsController
{
    protected $access_for_groups = array(UserGroups::admin);
    public $implement = 'Db_FormBehavior';

    public $form_edit_title = 'Customize';
    public $form_model_class = 'System\ColorThemeParams';
        
    public $form_redirect = null;
    public $form_edit_save_flash = 'The customization options have been saved.';

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
        $this->form_redirect = url('system/settings/');
    }

    public function index()
    {
        try {
            $record = ColorThemeParams::get();
            if (!$record) {
                throw new ApplicationException('Color theme configuration is not found.');
            }
            $this->edit($record->id);
            $this->app_page_title = $this->form_edit_title;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function index_onSave()
    {
        $record = ColorThemeParams::get();
        $this->edit_onSave($record->id);
    }
        
    protected function index_onCancel()
    {
        $record = ColorThemeParams::get();
        $this->edit_onCancel($record->id);
    }
}
