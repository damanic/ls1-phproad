<?php

namespace Shop;

use Backend\SettingsController;
use Users\Groups as UserGroup;
use Phpr\ApplicationException;

class Currency_Converter_Settings extends SettingsController
{
    protected $access_for_groups = array(UserGroup::admin);
    public $implement = 'Db_FormBehavior';

    public $form_edit_title = 'Currency Converter';
    public $form_model_class = 'Shop\CurrencyConversionParams';

    public $form_redirect = null;
    public $form_edit_save_flash = 'Currency converter parameters have been saved.';

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';

        $this->form_redirect = url('system/settings/');
    }

    public function index()
    {
        try {
            $record = CurrencyConversionParams::get();
            if (!$record) {
                throw new ApplicationException('Currency converter parameters not found.');
            }

            $this->edit($record->id);
            $this->app_page_title = $this->form_edit_title;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    public function formFindModelObject($recordId)
    {
        $model = CurrencyConversionParams::get();

        $params = post(get_class_id('Shop\CurrencyConversionParams'), array());
        if (isset($params['class_name'])) {
            $model->class_name = $params['class_name'];
        }

        $model->define_form_fields();

        return $model;
    }

    protected function index_onUpdateConverterParams()
    {
        $record = CurrencyConversionParams::get();
        $params = post(get_class_id('Shop\CurrencyConversionParams'), array());
        $record->class_name = $params['class_name'];
        $record->define_form_fields();

        echo ">>tab_2<<";
        $this->formRenderFormTab($record, 1);
    }

    protected function index_onSave()
    {
        $record = CurrencyConversionParams::get();
        $this->edit_onSave($record->id);
    }

    protected function index_onCancel()
    {
        $record = CurrencyConversionParams::get();
        $this->edit_onCancel($record->id);
    }
}
