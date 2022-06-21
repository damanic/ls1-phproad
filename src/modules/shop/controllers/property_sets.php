<?php
namespace Shop;

use Phpr;
use Backend;
use Backend\Controller;
use Phpr\ApplicationException;

class Property_Sets extends Controller
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior';
    public $list_model_class = 'Shop\PropertySet';
    public $list_record_url = null;

    public $form_preview_title = 'Property Set';
    public $form_create_title = 'New Property Set';
    public $form_edit_title = 'Edit Property Set';
    public $form_model_class = 'Shop\PropertySet';
    public $form_not_found_message = 'property set not found';
    public $form_redirect = null;

    public $form_edit_save_flash = 'The property set has been successfully saved';
    public $form_create_save_flash = 'The property set has been successfully added';
    public $form_edit_delete_flash = 'The property set has been successfully deleted';

    protected $required_permissions = array('shop:manage_shop_settings');
        
    protected $globalHandlers = array(
        'onLoadPropertyForm',
        'onSaveProperty',
        'onUpdatePropertyList',
        'onDeleteProperty',
    );

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'shop';
        $this->app_page = 'products';
        $this->app_module_name = 'Shop';

        $this->list_record_url = url('/shop/property_sets/edit/');
        $this->form_redirect = url('/shop/property_sets');
    }
        
    public function index()
    {
        $this->app_page_title = 'Property Sets';
    }

    protected function onLoadPropertyForm()
    {
        try {
            $this->resetFormEditSessionKey();

            $id = post('property_id');
            $property = $id ? PropertySetProperty::create()->find($id) : PropertySetProperty::create();
            if (!$property) {
                throw new ApplicationException('Property not found');
            }

            $property->define_form_fields();

            $this->viewData['property'] = $property;
            $this->viewData['session_key'] = post('edit_session_key');
            $this->viewData['property_id'] = post('property_id');
            $this->viewData['trackTab'] = false;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }

        $this->renderPartial('property_form');
    }
        
    protected function onSaveProperty($set_id)
    {
        try {
            $id = post('property_id');
            $property = $id ? PropertySetProperty::create()->find($id) : PropertySetProperty::create();
            if (!$property) {
                throw new ApplicationException('Property not found');
            }
                    
            if (!$id) {
                Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $property);
            } else {
                Backend::$events->fireEvent('core:onBeforeFormRecordUpdate', $this, $property);
            }

            $set = $this->getSetObj($set_id);

            $property->init_columns_info();
            $property->define_form_fields();
            $property->save(post(get_class_id('Shop\PropertySetProperty')), $this->formGetEditSessionKey());
                
            if (!$id) {
                Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $property);
            } else {
                Backend::$events->fireEvent('core:onAfterFormRecordUpdate', $this, $property);
            }

            if (!$id) {
                $set->properties->add($property, post('set_session_key'));
            }
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
        
    private function getSetObj($id)
    {
        return strlen($id) ? $this->formFindModelObject($id) : $this->formCreateModelObject();
    }
        
    protected function onUpdatePropertyList($parentId = null)
    {
        try {
            $this->viewData['form_model'] = $this->getSetObj($parentId);
            $this->renderPartial('properties_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
        
    protected function onDeleteProperty($parentId = null)
    {
        try {
            $set = $this->getSetObj($parentId);

            $id = post('property_id');
            $property = $id ? PropertySetProperty::create()->find($id) : PropertySetProperty::create();
            if ($property) {
                Backend::$events->fireEvent('core:onBeforeFormRecordDelete', $this, $property);
                $set->properties->delete($property, $this->formGetEditSessionKey());
            }

            $this->viewData['form_model'] = $set;
            $this->renderPartial('properties_list');
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
