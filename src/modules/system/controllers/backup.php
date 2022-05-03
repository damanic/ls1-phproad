<?php
namespace System;

use Phpr;
use Phpr\ApplicationException;
use FileSystem\File;
use FileSystem\Upload;
use Backend\Controller;
use Users\Groups as UserGroups;
use Core\CronManager;

class Backup extends Controller
{
    public $implement = 'Db_ListBehavior, Db_FormBehavior';
    public $list_model_class = 'System\Backup_Archive';
    public $list_record_url = null;

    public $form_preview_title = 'Archive';
    public $form_create_title = 'Create archive';
    public $form_model_class = 'System\Backup_Archive';
    public $form_not_found_message = 'Archive not found';
    public $form_create_save_flash = 'Archive has been successfully created.';
    public $form_redirect = null;
        
    public $list_custom_body_cells = null;
    public $list_custom_head_cells = null;

    protected $access_for_groups = array(UserGroups::admin);
    protected $public_actions = array('cron');

    public function __construct()
    {
        parent::__construct();
        $this->app_tab = 'system';
        $this->app_page = 'backup';
        $this->app_module_name = 'System';
            
        if (Phpr::$config->get('DISABLE_BACKUP_FEATURE')) {
            Phpr::$response->redirect(url('/'));
        }

        $this->list_record_url = url('/system/backup/preview/');
        $this->form_redirect = url('/system/backup/');
            
        $this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
        $this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
    }
        
    public function index()
    {
        try {
            $this->app_page_title = 'Backup/Restore';
            $this->viewData['configured'] = Backup_Params::isConfigured();
            if ($this->viewData['configured']) {
                try {
                    Backup_Params::validateParams();
                } catch (\Exception $ex) {
                    $this->viewData['configError'] = $ex->getMessage();
                }
            }
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    public function index_onDeleteSelected()
    {
        $items_processed = 0;
        $items_deleted = 0;

        $item_ids = post('list_ids', array());
        $this->viewData['list_checked_records'] = $item_ids;

        foreach ($item_ids as $item_id) {
            $item = null;
            try {
                $item = Backup_Archive::create()->find($item_id);
                if (!$item) {
                    throw new ApplicationException('Archive with identifier '.$item_id.' not found.');
                }

                $item->delete();
                $items_deleted++;
                $items_processed++;
            } catch (\Exception $ex) {
                if (!$item) {
                    Phpr::$session->flash['error'] = $ex->getMessage();
                } else {
                    Phpr::$session->flash['error'] = 'Error deleting archive. '.$ex->getMessage();
                }

                break;
            }
        }

        if ($items_processed) {
            $message = null;
                
            if ($items_deleted) {
                $message = 'Archives deleted: '.$items_deleted;
            }

            Phpr::$session->flash['success'] = $message;
        }

        $this->viewData['configured'] = Backup_Params::isConfigured();
        $this->renderPartial('backup_page_content');
    }

    public function listGetRowClass($model)
    {
        return strlen($model->error_message) ? 'error' : null;
    }

    /*
     * Configuration
     */
        
    public function setup()
    {
        $this->app_page_title = 'Backup system configuration';
            
        try {
            $model = Backup_Params::get();
            $model->init_columns_info();
            $model->define_form_fields();
            $this->viewData['model'] = $model;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    public function setup_onSave()
    {
        try {
            $obj = Backup_Params::get();
            $obj->init_columns_info();
            $obj->define_form_fields();

            $this->formRecoverCheckboxes($obj);

            $obj->save(post('System_Backup_Params', array()), $this->formGetEditSessionKey());

            Phpr::$session->flash['success'] = 'Backup system parameters have been saved successfully.';
            Phpr::$response->redirect(url('/system/backup'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }

    /*
     * Download
     */
        
    public function get($id)
    {
        try {
            $this->viewData['id'] = $id;
            $this->app_page_title = 'Download archive';
            $archive = Backup_Archive::create()->find($id);

            if (!$archive) {
                throw new ApplicationException('Archive not found');
            }

            if (!file_exists($archive->path)) {
                throw new ApplicationException('Archive file not found');
            }

            $fileName = basename($archive->path);
            header("Content-type: application/octet-stream");
            header('Content-Disposition: inline; filename="'.$fileName.'"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: pre-check=0, post-check=0, max-age=0');
            header('Accept-Ranges: bytes');
            header('Content-Length: '.filesize($archive->path));
            header("Connection: close");
            File::print($archive->path);
            $this->suppressView();
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }

    /*
     * Restore
     */
        
    public function restore($id)
    {
        try {
            $this->viewData['archive_id'] = $id;

            $this->app_page_title = 'Restore data from archive';
            $archive = Backup_Archive::create()->find($id);

            if (!$archive) {
                throw new ApplicationException('Archive not found');
            }

            $this->viewData['archive'] = $archive;
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    protected function restore_onRestore($id)
    {
        try {
            $archive = Backup_Archive::create()->find($id);
            if (!$archive) {
                throw new ApplicationException('Archive is not found');
            }
                    
            Backup_Archive::restoreData($archive->path);
                
            Phpr::$session->flash['success'] = 'Data has been successfully restored.';
            Phpr::$response->redirect(url('/system/backup'));
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
        
    public function fileRestore()
    {
        try {
            $this->app_page_title = 'Restore data from file';
                
            if (post('postback')) {
                try {
                    Upload::validateUploadedFile($_FILES['file']);
                    $fileInfo = $_FILES['file'];
                        
                    $pathInfo = pathinfo($fileInfo['name']);
                    if (!isset($pathInfo['extension']) || (strtolower($pathInfo['extension']) != 'lar' && strtolower($pathInfo['extension']) != 'zip')) {
                        throw new ApplicationException('Uploaded file is not a LSAPP archive.');
                    }

                    Backup_Archive::restoreFromFile($fileInfo);
                    Phpr::$session->flash['success'] = 'Data has been successfully restored.';
                    Phpr::$response->redirect(url('/system/backup'));
                } catch (\Exception $ex) {
                    $this->viewData['form_error'] = $ex->getMessage();
                }
            }
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
        
    /*
     * The following page can be used with cron jobs to automated backup creating
     * URL of this page is /backend/system/backup/cron  (the "backend" part could be different according to
     * your system configuration).
     */
        
    public function cron()
    {
        $this->layout = null;
            
        if (CronManager::access_allowed()) {
            try {
                Backup_Archive::backup(true, true);
                echo "Success";
            } catch (\Exception $ex) {
                echo $ex->getMessage();
            }
        }
    }
}