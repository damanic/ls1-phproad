<?php
namespace Backend;

use Phpr;
use Core\ModuleBase;
use Phpr\ModuleInfo;
use Phpr\Strings;

class Module extends ModuleBase
{
    /**
     * Creates the module information object
     * @return ModuleInfo
     */
    protected function createModuleInfo()
    {
        return new ModuleInfo(
            "Backend",
            "LSAPP back-end user interface",
            "LSAPP - MJMAN"
        );
    }

    /**
     * Returns a list of the module back-end GUI tabs.
     * @param TabCollection $tabCollection A tab collection object to populate.
     * @return mixed
     */
    public function listTabs($tabCollection)
    {
        $user = Phpr::$security->getUser();

        if ($user && $user->get_permission('backend', 'access_dashboard')) {
            $tabCollection->tab('dashboard', 'Dashboard', '', 10);
        }

        $reports = Reports::listReports();
        if (count($reports)) {
            $tabCollection->tab('reports', 'Reports', 'reports', 15);
        }
    }
        
    /**
     * Builds user permissions interface
     * For drop-down and radio fields you should also add methods returning
     * options. For example, of you want to have "Access Level" drop-down:
     * public function get_access_level_options();
     * This method should return array with keys corresponding your option identifiers
     * and values corresponding its titles.
     *
     * @param $host_obj ActiveRecord object to add fields to
     */
    public function buildPermissionsUi($host_obj)
    {
        $host_obj->add_field($this, 'access_dashboard', 'Dashboard Access')
            ->renderAs(frm_checkbox)
            ->comment('User has access to the dashboard.', 'above')
            ->tab('Account');
    }
        
    public function listPersonalSettingsItems()
    {
        return array(
            array(
                'icon'=>'/modules/backend/resources/images/edit.png',
                'title'=>'Code Editor Settings',
                'url'=>'/backend/codeeditorsettings',
                'description'=>'Customize the built-in code editor: select color theme, enable word wrapping, etc.',
                'sort_id'=>10,
                'section'=>'System'
                ),
            array(
                'icon'=>'/modules/backend/resources/images/computer_process.png',
                'title'=>'Appearance',
                'url'=>'/backend/appearancesettings',
                'description'=>'Customize the Administration Area appearance: choose the main menu style, etc.',
                'sort_id'=>20,
                'section'=>'System'
                )
        );
    }

    /**
     * Detect if current URL request is for backend
     * @return bool True if backend
     */
    public static function isBackend() : bool
    {
        $target = Phpr::$config->get('BACKEND_URL', 'backend');
        $q = Phpr::$request->get_fields['q'] ?? '';
        $backendUrl = '/' . Strings::normalizeUri($target);
        $currentUrl = '/' . Strings::normalizeUri($q);
        return stristr($currentUrl, $backendUrl) !== false;
    }
}
