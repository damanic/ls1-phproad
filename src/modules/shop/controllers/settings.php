<?php

namespace Shop;

use Phpr;
use Backend;
use Backend\SettingsController;
use Phpr\ApplicationException;

class Settings extends SettingsController
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior';
    public $form_model_class = '';

    public $list_model_class = null;
    public $list_record_url = null;

    public $list_custom_body_cells = null;
    public $list_custom_head_cells = null;
    public $list_top_partial = null;

    public $list_search_enabled = false;
    public $list_search_fields = array();
    public $list_search_prompt = null;

    protected $globalHandlers = array(
        'onLoadCountryStateForm',
        'onSaveCountryState',
        'onUpdateCountryStateList',
        'onDeleteCountryState'
    );

    protected $required_permissions = array(
        'shop:manage_countries_and_states',
        'shop:manage_shop_currency',
    );

    public function __construct()
    {
        parent::__construct();

        switch (Phpr::$router->action) {
            case 'countries':
                $this->list_model_class = 'Shop\Country';
                $this->list_record_url = url('/shop/settings/edit_country');

                $path = PATH_APP . '/phproad/modules/db/behaviors/db_listbehavior/partials/';
                $this->list_custom_body_cells = $path . '_list_body_cb.htm';
                $this->list_custom_head_cells = $path . '_list_head_cb.htm';

                $this->list_search_enabled = true;
                $this->list_search_fields = [
                    'shop_countries.name',
                    'shop_countries.code',
                    'shop_countries.code_3',
                    'shop_countries.code_iso_numeric'
                ];
                $this->list_search_prompt = 'find countries by name or code';
                $this->list_top_partial = 'country_selectors';
                break;
            case 'create_country':
                $this->form_model_class = 'Shop\Country';
                break;
        }
    }

    /*
         * Currency setup
     */

    public function currency()
    {
        if (!$this->currentUser->get_permission('shop', 'manage_shop_currency')) {
            Phpr::$session->flash['error'] = 'You do not have permission, access denied.';
            Phpr::$response->redirect(url('system/settings'));
        }
        $this->app_page_title = 'Currency';
        $this->form_model_class = 'Shop\CurrencySettings';
        $settings = CurrencySettings::get();
        $settings->init_columns_info();
        $settings->define_form_fields();
        $this->viewData['settings'] = $settings;
    }

    protected function currency_onSave()
    {
        try {
            $settings = CurrencySettings::get();
            $settings->init_columns_info();
            $settings->define_form_fields();
            $settings->save(post(get_class_id('Shop\CurrencySettings')));

            Phpr::$session->flash['success'] = 'Currency settings have been saved.';
            Phpr::$response->redirect(url('system/settings'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    /*
         * Countries
     */

    public function countries()
    {
        if (!$this->currentUser->get_permission('shop', 'manage_countries_and_states')) {
            Phpr::$session->flash['error'] = 'You do not have permission, access denied.';
            Phpr::$response->redirect(url('system/settings'));
        }
        $this->app_page_title = 'Countries';
    }

    protected function countries_onLoadEnableDisableCountriesForm()
    {
        try {
            $country_ids = post('list_ids', array());

            if (!count($country_ids)) {
                throw new ApplicationException('Please select countries to enable or disable.');
            }

            $this->viewData['country_count'] = count($country_ids);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('enable_disable_country_form');
    }

    protected function countries_onApplyCountriesEnabledStatus()
    {
        $country_ids = post('list_ids', array());

        $enabled = post('enabled');
        $enabled_in_backend = post('enabled_in_backend');

        if ($enabled) {
            $enabled_in_backend = true;
        }

        foreach ($country_ids as $country_id) {
            $country = Country::create()->find($country_id);
            if ($country) {
                $country->update_enabled_status($enabled, $enabled_in_backend);
            }
        }

        $this->onListReload();
    }

    public function create_country()
    {
        $this->app_page_title = 'New Country';

        try {
            $country = $this->viewData['form_model'] = $this->initCountry();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function create_country_onSave()
    {
        try {
            $country = $this->initCountry();

            Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $country);
            $country->save(post(get_class_id('Shop\Country'), []), $this->formGetEditSessionKey());
            Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $country);

            Phpr::$session->flash['success'] = 'Country has been successfully added';
            Phpr::$response->redirect(url('/shop/settings/countries'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function create_country_onCancel()
    {
        $this->initCountry()->cancelDeferredBindings($this->formGetEditSessionKey());
        Phpr::$response->redirect(url('/shop/settings/countries'));
    }

    public function edit_country($id)
    {
        $this->app_page_title = 'Edit Country';

        try {
            $this->viewData['form_model'] = $this->initCountry($id);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    protected function edit_country_onSave($id)
    {
        try {
            $country = $this->initCountry($id);

            Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $country);
            $country->save(post(get_class_id('Shop\Country'), []), $this->formGetEditSessionKey());
            Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $country);

            Phpr::$session->flash['success'] = 'Country has been successfully saved';
            Phpr::$response->redirect(url('/shop/settings/countries'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function edit_country_onDelete($id)
    {
        try {
            $country = $this->initCountry($id);
            $country->delete();

            Phpr::$session->flash['success'] = 'Country has been successfully deleted';
            Phpr::$response->redirect(url('/shop/settings/countries'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function edit_country_onCancel($id)
    {
        $this->initCountry($id)->cancelDeferredBindings($this->formGetEditSessionKey());
        Phpr::$response->redirect(url('/shop/settings/countries'));
    }

    protected function onLoadCountryStateForm()
    {
        try {
            $id = post('state_id');
            $state = $id ? CountryState::create()->find($id) : CountryState::create();
            if (!$state) {
                throw new ApplicationException('State not found');
            }

            $state->define_form_fields();

            $this->viewData['state'] = $state;
            $this->viewData['session_key'] = post('edit_session_key');
            $this->viewData['state_id'] = post('state_id');
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('country_state_form');
    }

    protected function onSaveCountryState($countryId)
    {
        try {
            $id = post('state_id');
            $state = $id ? CountryState::create()->find($id) : CountryState::create();
            if (!$state) {
                throw new ApplicationException('State not found');
            }

            $country = $this->initCountry($countryId);

            $state->init_columns_info();
            $state->define_form_fields();

            if ($id) {
                Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $state);
            } else {
                Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $state);
            }

            $state->save(post(get_class_id('Shop\CountryState')), $this->formGetEditSessionKey());

            if (!$id) {
                $country->states->add($state, post('country_session_key'));
            }

            if ($id) {
                Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $state);
            } else {
                Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $state);
            }
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onUpdateCountryStateList($countryId)
    {
        try {
            $this->viewData['form_model'] = $this->initCountry($countryId);
            $this->renderPartial('country_states_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    protected function onDeleteCountryState($countryId)
    {
        try {
            $country = $this->viewData['form_model'] = $this->initCountry($countryId);

            $id = post('state_id');
            $state = $id ? CountryState::create()->find($id) : ExtraOption::create();

            if ($state) {
                $state->check_in_use();
                Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $state);

                $country->states->delete($state, $this->formGetEditSessionKey());
                $state->delete();
            }

            $this->viewData['form_model'] = $country;
            $this->renderPartial('country_states_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    public function listGetRowClass($model)
    {
        $class = null;
        if ($model instanceof Country) {
            $result = 'country_' . ($model->enabled ? 'enabled' : 'disabled') . ' ';

            $enabled_flag = null;
            if (!$model->enabled && !$model->enabled_in_backend) {
                $enabled_flag = 'disabled';
            } elseif (!$model->enabled && $model->enabled_in_backend) {
                $enabled_flag = 'special';
            }

            $class = $result . $enabled_flag;
        }
        return $class;
    }

    private function initCountry($id = null)
    {
        $obj = $id == null ? Country::create() : Country::create()->find($id);
        if ($obj) {
            //Include disabled states
            $obj->has_many['states'] = array(
                'class_name' => 'Shop\CountryState',
                'foreign_key' => 'country_id',
                'order' => 'shop_states.disabled, shop_states.name',
                'delete' => true
            );
            $obj->init_columns_info();
            $obj->define_form_fields();
        } elseif ($id != null) {
            throw new ApplicationException('Country not found');
        }

        return $obj;
    }


}
