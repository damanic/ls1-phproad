<?

	class System_Settings extends Backend_Controller
	{
		
		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';
		}
		
		public function index()
		{
			$user = Phpr::$security->getUser();
			$permissible_items = System_Module::getSettingItemsPermissible($user,true);
			if(!count($permissible_items)){
				Phpr::$response->redirect(url('/'));
				exit;
			}
			$this->app_page_title = 'Settings';
			$this->app_page = 'settings';
			$this->viewData['items'] = $permissible_items;
			$this->viewData['body_class'] = 'no_padding';
		}
	}
	
?>