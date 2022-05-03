<?php
namespace Cms;

use Phpr;
use Phpr\ApplicationException;
use Backend;
use Backend\Controller;

class Templates extends Controller
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior, Backend_FileBrowser, Cms_ThemeSelector';
    public $list_model_class = 'Cms\Template';
    public $list_record_url = null;
        
    public $form_preview_title = 'Layout';
    public $form_create_title = 'New Layout';
    public $form_edit_title = 'Edit Layout';
    public $form_model_class = 'Cms\Template';
    public $form_not_found_message = 'Layout not found';
    public $form_redirect = null;
    public $form_edit_save_auto_timestamp = true;
    public $form_create_save_redirect = null;
    public $form_flash_id = 'form_flash';
        
    public $form_edit_save_flash = 'The layout has been successfully saved';
    public $form_create_save_flash = 'The layout has been successfully added';
    public $form_edit_delete_flash = 'The layout has been successfully deleted';
        
    public $list_search_enabled = true;
    public $list_search_fields = array('name', 'html_code');
    public $list_search_prompt = 'find layouts by name or content';

    public $filebrowser_onFileClick = "return onFileBrowserFileClick('%s');";
    public $file_browser_file_list_class = 'ui-layout-anchor-window-bottom offset-24';

    protected $globalHandlers = array('onSave');

    protected $required_permissions = array('cms:manage_pages');
    public $enable_concurrency_locking = true;

    public $filebrowser_dirs = array();
    public $filebrowser_default_dirs = true;

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'cms';
        $this->app_module_name = 'CMS';

        $this->list_record_url = url('/cms/templates/edit/');
        $this->form_redirect = url('/cms/templates');
        $this->form_create_save_redirect = url('/cms/templates/edit/%s/'.uniqid());
        $this->app_page = 'templates';
    }
        
    public function formAfterEditSave($model, $session_key)
    {
        $this->viewData['form_model'] = $model;
        $model->updated_user_name = $this->currentUser->name;
            
        $this->renderMultiple(array(
            'form_flash'=>flash(),
            'object-summary'=>'@_object_summary'
        ));
            
        return true;
    }
        
    public function listPrepareData()
    {
        $updated_data = Backend::$events->fireEvent('cms:onPrepareTemplateListData', $this);
        foreach ($updated_data as $updated) {
            if ($updated) {
                return $updated;
            }
        }
            
        $obj = Template::create();
            
        if (Theme::is_theming_enabled()) {
            $theme = Theme::get_edit_theme();
            if ($theme) {
                $obj->where('theme_id=?', $theme->id);
            }
        }

        return $obj;
    }
        
    public function index()
    {
        Template::auto_create_from_files();
        $this->app_page_title = 'Layouts';
    }
        
    protected function index_onDeleteSelected()
    {
        $items_processed = 0;
        $items_deleted = 0;

        $item_ids = post('list_ids', array());
        $this->viewData['list_checked_records'] = $item_ids;

        foreach ($item_ids as $item_id) {
            $item = null;
            try {
                $item = Template::create()->find($item_id);
                if (!$item) {
                    throw new ApplicationException('Layout with identifier '.$item_id.' not found.');
                }

                $item->delete();
                $items_deleted++;
                $items_processed++;
            } catch (\Exception $ex) {
                if (!$item) {
                    Phpr::$session->flash['error'] = $ex->getMessage();
                } else {
                    Phpr::$session->flash['error'] = 'Error deleting layout "'.$item->name.'": '.$ex->getMessage();
                }

                break;
            }
        }

        if ($items_processed) {
            $message = null;
                
            if ($items_deleted) {
                $message = 'Layouts deleted: '.$items_deleted;
            }

            Phpr::$session->flash['success'] = $message;
        }

        $this->renderPartial('templates_page_content');
    }
        
    protected function index_onRefresh()
    {
        Template::auto_create_from_files();
        $this->renderPartial('templates_page_content');
    }

    protected function onSave($id)
    {
        Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
    }
        
    public function formAfterCreateSave($page, $session_key)
    {
        if (post('create_close')) {
            $this->form_create_save_redirect = url('/cms/templates').'?'.uniqid();
        }
    }
        
    public function formBeforeCreateSave($model, $session_key)
    {
        if (Theme::is_theming_enabled()) {
            $theme = Theme::get_edit_theme();
            if ($theme) {
                $model->theme_id = $theme->id;
            }
        }
    }
        
    public function edit_formBeforeRender($model)
    {
        $model->load_file_content();
    }
        
    public function listGetRowClass($model)
    {
        if (!SettingsManager::get()->enable_filebased_templates || !($model instanceof Template)) {
            return null;
        }
                
        if ($model->file_is_missing()) {
            return 'error';
        }
    }
        
    protected function edit_onFixTemplateFile($id)
    {
        try {
            $obj = $this->formFindModelObject($id);
                
            $action = post('action');
                
            if ($action == 'create_new') {
                $obj->assign_file_name(post('new_file_name'));
                Phpr::$response->redirect(url('cms/templates/edit/'.$obj->id));
            } elseif ($action == 'delete') {
                $obj->delete();
                Phpr::$session->flash['success'] = $this->form_edit_delete_flash;
                Phpr::$response->redirect(url('cms/templates/'));
            }
        } catch (Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}