<?php
namespace System;

use Phpr;
use Phpr\ModuleInfo;
use Core\ModuleBase;
use Core\UpdateManager;

class Module extends ModuleBase
{
    /**
     * Creates the module information object
     * @return ModuleInfo
     */
    protected function createModuleInfo()
    {
        return new ModuleInfo(
            "System",
            "LSAPP configuration features",
            "LSAPP - MJMAN"
        );
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
        $host_obj->add_field($this, 'manage_email_templates', 'Manage email templates', 'left')
            ->renderAs(frm_checkbox)
            ->comment('Create email templates and edit template content.', 'above');
    }

    /**
     * Returns a list of the module back-end GUI tabs.
     * @param TabCollection $tabCollection A tab collection object to populate.
     * @return mixed
     */
    public function listTabs($tabCollection)
    {
        $user = Phpr::$security->getUser();
        $settings_items_allowed = count(self::getSettingItemsPermissible($user));
        if (!$user->is_administrator() && !$settings_items_allowed) {
            return;
        }

        $tabs = $tabCollection->tab('system', 'System', 'settings', 1000);

        if ($user->is_administrator()) {
            $tabs->addSecondLevel('settings', 'Settings', 'settings');
            $tabs->addSecondLevel('users', 'Users', 'users');

            if (!Phpr::$config->get('DISABLE_BACKUP_FEATURE')) {
                $tabs->addSecondLevel('backup', 'Backup or Restore', 'backup');
            }

            $tabs->addSecondLevel('modules', 'Modules & Updates', 'modules');
        }
    }
        
    /**
     * Returns notifications to be displayed in the main menu.
     * @return array Returns an array of notifications in the following format:
     * array(
     *    array(
     *      'id'=>'new-tickets',
     *      'closable'=>false,
     *      'text'=>'10 new support tickets',
     *      'icon'=>'resources/images/notification.png',
     *      'link'=>'/support/tickets'
     *    )
     * ).
     * The 'link', 'id' and 'closable' keys are optional, but id should be specified if closable is true.
     * Use the url() function to create values for the 'link' value.
     * The icon should be a PNG image of size 16x16. Icon path should be specified relative to the module
     * root directory.
     */
    public function listMenuNotifications()
    {
        $user = Phpr::$security->getUser();
        $updates_available = $user->is_administrator() && UpdateManager::create()->get_updates_flag();

        if (!$updates_available) {
            return array();
        }
                
        return array(
            array(
                'text'=>'LSAPP updates available',
                'icon'=>'resources/images/database_refresh.png',
                'link'=>url('system/modules/').'#check'
            )
        );
    }
        
    /**
     * Returns a list of HTML Editor configurations used by the module
     * The method must return an array of configuration codes and descriptions:
     * array('blog_post_content'=>'Blog post')
     * @return array
     */
    public function listHtmlEditorConfigs()
    {
        return array('system_email_template'=>'Email message template');
    }

    public function listSettingsItems()
    {
        return array(
            array(
                'icon'=>'/modules/system/resources/images/mail_settings.png',
                'title'=>'Email Settings',
                'url'=>'/system/email',
                'description'=>'Configure email settings. Specify SMTP server address and authorization parameters. ',
                'sort_id'=>20,
                'section'=>'System',
                ),
            array(
                'icon'=>'/modules/system/resources/images/themes.png',
                'title'=>'Customize',
                'url'=>'/system/colortheme',
                'description'=>'Choose theme for backend, upload a company logo, set header and footer text.',
                'sort_id'=>110,
                'section'=>'System'
                ),
            array(
                'icon'=>'/modules/system/resources/images/email_templates.png',
                'title'=>'Email Templates',
                'url'=>'/system/email_templates',
                'description'=>'Edit email message templates.',
                'sort_id'=>30,
                'section'=>'System',
                'access_permission'=>'system:manage_email_templates'
                ),
            array(
                'icon'=>'/modules/cms/resources/images/icon_editor_settings.png',
                'title'=>'HTML Editor Settings',
                'url'=>'/system/editor_config/',
                'description'=>'Configure HTML Editor buttons, menus and other features.',
                'sort_id'=>40,
                'section'=>'System'
                ),
            array(
                'icon'=>'/modules/system/resources/images/error_log.png',
                'title'=>'View Error Log',
                'url'=>'/system/error_log/',
                'description'=>'View error log messages. Preview errors time and description.',
                'sort_id'=>120,
                'section'=>'System'
                ),
            array(
                'icon'=>'/modules/system/resources/images/full_page.png',
                'title'=>'View Access Log',
                'url'=>'/system/access_log/',
                'description'=>'View list of successful user logins.',
                'sort_id'=>130,
                'section'=>'System'
                )
        );
    }
        
    public function listPersonalSettingsItems()
    {
        return array(
            array(
                'icon'=>'/modules/system/resources/images/user.png',
                'title'=>'My Account',
                'url'=>'/system/users/mysettings',
                'description'=>'Update your account details.',
                'sort_id'=>100,
                'section'=>'System'
            )
        );
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
        $params = EmailParams::get();
        $result = array();
        if ($params->allow_recipient_blocking) {
            $result = array(
                'Template variables' => array(
                    'this_template_unsubscribe_url' => array(
                        'Generates a link for recipient to unsubscribe from future delivery of this email template',
                        '{this_template_unsubscribe_url}'
                    ),
                    'all_template_unsubscribe_url'  => array(
                        'Generates a link for recipient to unsubscribe from all non-transactional email templates',
                        '{all_template_unsubscribe_url}'
                    )
                )
            );
        }
        return $result;
    }

    public static function getSettingItemsPermissible($user, $group = false)
    {
        if ($user->is_administrator()) {
            $items = ModuleManager::listSettingsItems($group);
        } else {
            $items = ModuleManager::listSettingsItemsPermissible($user, $group);
        }
        return $items;
    }
}
