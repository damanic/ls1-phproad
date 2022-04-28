<?php
namespace Core;

use Phpr;
use FileSystem\Zip as ZipHelper;
use Phpr\Date;
use Phpr\ModuleInfo;

/**
 * @has_documentable_methods
 */
class Module extends ModuleBase
{

    protected function createModuleInfo()
    {
        return new ModuleInfo(
            "Core",
            "Core module for LSAPP",
            "LSAPP - MJMAN"
        );
    }

    public function subscribeEvents()
    {
        Phpr::$events->addEvent('onLogin', $this, 'onUserLogin');
        Phpr::$events->addEvent('core:onAfterSoftwareUpdate', $this, 'onAfterSoftwareUpdate');

        $deprecatedCron = new Cron();
        Phpr::$events->add_event('phpr:on_execute_cron_exception', $deprecatedCron, 'onExecuteCronException');
        Phpr::$events->add_event('phpr:on_execute_crontab_exception', $deprecatedCron, 'onExecuteCrontabException');
        Phpr::$events->add_event('phpr:on_execute_cronjob_exception', $deprecatedCron, 'onExecuteCronjobException');
        Phpr::$events->add_event('phpr:on_cronjob_shutdown', $deprecatedCron, 'onCronjobShutdown');
        Phpr::$events->add_event('phpr:on_cronjob_exceeded_max_duration', $deprecatedCron, 'onCronjobExceededMaxDuration');
    }
        
    public function onUserLogin()
    {
        $handler_path = PATH_APP.'/handlers/login.php';
        if (file_exists($handler_path)) {
            include $handler_path;
        }
    }

    public function onAfterSoftwareUpdate()
    {
    }
        
    /**
     * Returns a list of email template variables provided by the module.
     * The method must return an array of section names, variable names,
     * descriptions and demo-values:
     * array('Shop variables'=>array(
     *  'order_total'=>array('Outputs order total value', '$99.99')
     * ))
     * @return array
     */
    public function listEmailVariables()
    {
        return array(
            'System variables'=>array(
                'recipient_email'=>array('Outputs the email recipient email address', '{recipient_email}'),
                'email_subject'=>array('Outputs the email subject', '{email_subject}')
            )
        );
    }
        
    public function listSettingsItems()
    {
        $eula_info = EulaInfo::get();
        $eula_update_str = null;
        if ($eula_info->accepted_on) {
            $eula_update_str = sprintf(' Last updated on %s.', Date::display($eula_info->accepted_on));
        }
                
        $user = Phpr::$security->getUser();
        $is_unread = EulaInfo::is_unread($user->id);

        return array(
            array(
                'icon'=>'/modules/core/resources/images/new_page.png',
                'title'=>'License Agreement',
                'url'=>'/core/viewlicenseagreement',
                'description'=>'View LSAPP End User License Agreement.'.$eula_update_str,
                'sort_id'=>200,
                'section'=>'System',
                'class'=>($is_unread ? 'unread' : null)
            )
        );
    }
}
