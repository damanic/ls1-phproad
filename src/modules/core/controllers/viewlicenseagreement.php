<?php
namespace Core;

use Backend\SettingsController;
use Phpr\ApplicationException;

class ViewLicenseAgreement extends SettingsController
{
    public $implement = 'Db\FormBehavior';
        
    public function index()
    {
        $this->app_tab = 'system';
        $this->app_page_title = 'License Agreement';

        try {
            $eula_info = EulaInfo::get();
            $this->viewData['eula_info'] = $eula_info;
            if (!$eula_info->agreement_text) {
                throw new ApplicationException('License agreement not found');
            }
                    
            $this->viewData['accepted_user_name'] = $eula_info->get_accepted_user_name();
            EulaInfo::mark_read($this->currentUser->id);
        } catch (\Exception $ex) {
            $this->handlePageError($ex);
        }
    }
}
