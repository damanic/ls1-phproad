<?

	class System_Email_Templates extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'System_EmailTemplate';
		public $list_record_url = null;

		public $form_model_class = 'System_EmailTemplate';
		public $form_not_found_message = 'Template not found';
		public $form_create_context_name = 'create';
		public $form_redirect = null;
		public $form_create_title = 'New Email Template';
		public $form_edit_title = 'Edit Email Template';

		public $list_search_enabled = true;
		public $list_search_fields = array('@code', '@subject', '@description');
		public $list_search_prompt = 'find templates by code, subject or description';

		public $form_edit_save_flash = 'Email template has been successfully saved';
		public $form_create_save_flash = 'Email template has been successfully added';
		public $form_edit_delete_flash = 'Email template has been successfully deleted';
		
		//protected $access_for_groups = array(Users_Groups::admin);
		protected $required_permissions = array( 'system:manage_email_templates' );

        protected $blocked_file_types = array(
            'ade',
            'adp',
            'apk',
            'appx',
            'appxbundle',
            'bat',
            'cab',
            'chm',
            'cmd',
            'com',
            'cpl',
            'dll',
            'dmg',
            'ex',
            'ex_',
            'exe',
            'hta',
            'ins',
            'isp',
            'iso',
            'jar',
            'js',
            'jse',
            'lib',
            'lnk',
            'mde',
            'msc',
            'msi',
            'msix',
            'msixbundle',
            'msp',
            'mst',
            'nsh',
            'pif',
            'ps1',
            'scr',
            'sct',
            'shb',
            'sys',
            'vb',
            'vbe',
            'vbs',
            'vxd',
            'wsc',
            'wsf',
            'wsh'
        );

		public $globalHandlers = array('onTest');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';

			$this->list_record_url = url('/system/email_templates/edit/');
			$this->form_redirect = url('/system/email_templates/');
		}
		
		public function index()
		{
			try
			{
				$this->app_page_title = 'Email Templates';
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		protected function onTest($id)
		{
			try
			{
				$obj = strlen($id) ? $this->formFindModelObject($id) : $this->formCreateModelObject();
				$obj->validate_data(post($this->form_model_class, array()));
				$obj->send_test_message();
				
				echo Backend_Html::flash_message('The test message has been successfully sent.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function listGetRowClass($model)
		{
			$classes = $model->is_system ? null : 'important';
			return $classes;
		}

        public function formBeforeFileUploadSave($model, $dbName, $dbFile, $sessionKey){
            if(is_a($model,'System_EmailTemplate') && $dbName == 'file_attachments' ){
                $pathInfo = pathinfo($dbFile->name);
                $fileExtension = isset($pathInfo['extension']) ? $pathInfo['extension'] : null;
                if (!$fileExtension || in_array($fileExtension,$this->blocked_file_types)) {
                    $dbFile->delete();
                    throw new Phpr_ApplicationException('The file type '.strtoupper($fileExtension).' is not permitted for '.$dbName);
                }
            }
        }
	}
