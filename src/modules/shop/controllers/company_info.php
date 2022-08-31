<?php

namespace Shop;

use Backend\SettingsController;
use Users\Group as UserGroup;
use Phpr\ApplicationException;

class Company_Info extends SettingsController
{
    protected $access_for_groups = array(UserGroup::ADMIN);
    public $implement = 'Db_FormBehavior';

    public $form_edit_title = 'Company Information and Settings';
    public $form_model_class = 'Shop\CompanyInformation';

    public $form_redirect = null;
    public $form_edit_save_flash = 'Company information has been saved.';

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';

        $this->form_redirect = url('system/settings/');
    }

    public function index()
    {
        try {
            $record = CompanyInformation::get();
            if (!$record) {
                throw new ApplicationException('Company information configuration is not found.');
            }

            $this->edit($record->id);
            $this->app_page_title = $this->form_edit_title;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function index_onSave()
    {
        $record = CompanyInformation::get();
        $this->edit_onSave($record->id);
    }

    protected function index_onCancel()
    {
        $record = CompanyInformation::get();
        $this->edit_onCancel($record->id);
    }
}
