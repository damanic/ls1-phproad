<?php
namespace Core;

use Phpr\SystemException;
use Phpr\ModuleManager as PhprModuleManager;
use Users\Group as UserGroup;
use System\CompoundEmailVar;
use Backend\TabCollection;

class ModuleManager
{
    protected static $modules = null;
    protected static $tabs = null;
    protected static $moduleTabs = null;
    protected static $eventsSubscribed = false;

    /**
     * Returns a list of modules
     * @return array
     */
    public static function listModules($allow_caching = true, $return_disabled_only = false)
    {
        $moduleObjects = PhprModuleManager::getModules($allow_caching, $return_disabled_only);
        if ($moduleObjects) {
            $allowedPath = PATH_APP."/modules";
            foreach ($moduleObjects as $key => $moduleObject) {
                $allowedPath = PATH_APP."/modules";
                if (!stristr($moduleObject->dir_path, $allowedPath)) {
                    unset($moduleObjects[$key]);
                }
            }
        }
        return $moduleObjects;
    }
        
    public static function listSettingsItems($group_by_sections = false)
    {
        $result = array();
        $modules = self::listModules();
        foreach ($modules as $module) {
            $result = array_merge($result, $module->listSettingsItems(), self::listModuleSettingsForm($module, true));
        }
                
        uasort($result, array('Core\ModuleManager', 'compareSettingItems'));
            
        if ($group_by_sections) {
            return self::groupSettingsItems($result);
        }

        return $result;
    }

    public static function listSettingsItemsPermissible($user, $group_by_sections = false)
    {

        if ($user->belongsToGroups(UserGroup::ADMIN)) {
            return self::listSettingsItems($group_by_sections);
        }

        $items = self::listSettingsItems(false);
        $permissible_items = array();
        foreach ($items as $item) {
            $permission_access = isset($item['access_permission']) ? $item['access_permission'] : false;
            if ($permission_access) {
                $permission_info = explode(':', $permission_access);
                $cnt = count($permission_info);
                if ($cnt == 2) {
                    if ($user->get_permission($permission_info[0], $permission_info[1])) {
                        $permissible_items[] = $item;
                    }
                }
            }
        }
        if ($group_by_sections) {
            return self::groupSettingsItems($permissible_items);
        }

        return $permissible_items;
    }
        
    protected static function listModuleSettingsForm($module, $global)
    {
        $result = array();
            
        $settings_forms = $module->listSettingsForms();
        foreach ($settings_forms as $code => &$settings_form_info) {
            $is_global = !(array_key_exists('personal', $settings_form_info) && $settings_form_info['personal']);
            if ((!$is_global && $global) || ($is_global && !$global)) {
                continue;
            }
                
            $settings_form_info['url'] = 'core/settings/edit/'.$module->getId().'/'.$code;
            $result[] = $settings_form_info;
        }
            
        return $result;
    }
        
    protected static function groupSettingsItems($items)
    {
        $misc_title = 'Miscellaneous';
        $result_grouped = array();
            
        foreach ($items as $id => $item) {
            $section = is_array($item) && array_key_exists('section', $item) ? $item['section'] : $misc_title;
            if (!array_key_exists($section, $result_grouped)) {
                $result_grouped[$section] = array();
            }
                    
            $result_grouped[$section][$id] = $item;
        }
            
        if (array_key_exists($misc_title, $result_grouped)) {
            $misc_items = $result_grouped[$misc_title];
            unset($result_grouped[$misc_title]);
            $result_grouped[$misc_title] = $misc_items;
        }
            
        return $result_grouped;
    }
        
    public static function listPersonalSettingsItems($group_by_sections = false)
    {
        $result = array();
        $modules = self::listModules();
        foreach ($modules as $module) {
            $result = array_merge($result, $module->listPersonalSettingsItems(), self::listModuleSettingsForm($module, false));
        }

        uasort($result, array('Core\ModuleManager', 'compareSettingItems'));
            
        if ($group_by_sections) {
            return self::groupSettingsItems($result);
        }

        return $result;
    }
        
    public static function buildPermissionsUi($host_obj)
    {
        $modules = self::listModules();
        foreach ($modules as $id => $module) {
            $module->buildPermissionsUi($host_obj);
        }
    }
        
    public static function listEmailVariables($module_id = null, $add_compound_variables = false)
    {
        $modules = self::listModules();
        $variables = array();
        foreach ($modules as $module) {
            if ($module_id !== null) {
                if ($module->getId() != $module_id) {
                    continue;
                }
            }
                
            $module_variables = $module->listEmailVariables();
            if (!is_array($module_variables)) {
                throw new SystemException('Method listEmailVariables must return an array. Please check module "'.$module->getId().'".');
            }
                
            $variables += $module_variables;
        }
            
        if ($add_compound_variables) {
            $scopes = self::listEmailScopes();
            $sections = array_keys($variables);
            foreach ($sections as $section) {
                $scope = null;
                foreach ($scopes as $scope_code => $scope_name) {
                    if ($scope_name == $section) {
                        $scope = $scope_code;
                        break;
                    }
                }
                    
                if ($scope) {
                    $vars = CompoundEmailVar::list_scope_variables($scope);
                    foreach ($vars as $var) {
                        $variables[$section][$var->code] = array($var->description, 'Test '.$var->code.' value.');
                    }
                }
            }
        }

        return $variables;
    }
        
    public static function listHtmlEditorConfigs()
    {
        $modules = self::listModules();
        $result = array();
        foreach ($modules as $module) {
            $module_configs = $module->listHtmlEditorConfigs();
            if (!is_array($module_configs)) {
                throw new SystemException('Method listHtmlEditorConfigs must return an array. Please check module "'.$module->getId().'".');
            }
                
            $module_id = $module->getId();
            if (!array_key_exists($module_id, $result)) {
                $result[$module_id] = array();
            }
                    
            foreach ($module_configs as $code => $description) {
                $result[$module_id][$code] = $description;
            }
        }

        return $result;
    }
        
    public static function applyEmailVariables($template_text, $order, $customer)
    {
        $modules = self::listModules();
        foreach ($modules as $module) {
            $template_text = $module->applyEmailVariables($template_text, $order, $customer);
        }

        return $template_text;
    }
        
    public static function listDashboardIndicators()
    {
        $modules = self::listModules();
        $indicators = array();
        foreach ($modules as $module) {
            $module_id = $module->getId();
            $module_indicators = $module->listDashboardIndicators();
            if (!is_array($module_indicators)) {
                throw new SystemException('Method listDashboardIndicators must return an array. Please check module "'.$module_id.'".');
            }
                    
            foreach ($module_indicators as $id => $indicator_info) {
                if (isset($indicator_info['partial'])) {
                    $indicator_info['partial'] = PATH_APP.'/modules/'.mb_strtolower($module_id).'/dashboard/'.$indicator_info['partial'];
                }
                    
                $indicators[$module_id.'_'.$id] = $indicator_info;
            }
        }
            
        uasort($indicators, array('Core\ModuleManager', 'compareDashboardItems'));

        return $indicators;
    }

    public static function listDashboardReports()
    {
        $modules = self::listModules();
        $reports = array();
        foreach ($modules as $module) {
            $module_id = $module->getId();
            $module_reports = $module->listDashboardReports();
            if (!is_array($module_reports)) {
                throw new SystemException('Method listDashboardReports must return an array. Please check module "'.$module_id.'".');
            }
                    
            foreach ($module_reports as $id => $report_info) {
                if (isset($report_info['partial'])) {
                    $report_info['partial'] = PATH_APP.'/modules/'.mb_strtolower($module_id).'/dashboard/'.$report_info['partial'];
                }
                    
                $reports[$module_id.'_'.$id] = $report_info;
            }
        }
            
        uasort($reports, array('Core\ModuleManager', 'compareDashboardItems'));

        return $reports;
    }

    public static function listReports()
    {
        $modules = self::listModules();
        $reports = array();
        foreach ($modules as $module) {
            $module_id = $module->getId();
            $module_reports = $module->listReports();
            if (!is_array($module_reports)) {
                throw new SystemException('Method listReports must return an array. Please check module "'.$module_id.'".');
            }

            if (isset($module_reports[0]['name'])) {
                $reports[$module_id] = $module_reports;
            } else {
                $reports[$module_id] = array('name'=>$module->getModuleInfo()->name, 'reports'=>$module_reports);
            }
        }

        ksort($reports);
        return $reports;
    }
        
    public static function listEmailScopes()
    {
        $modules = self::listModules();
        $result = array();
        foreach ($modules as $module) {
            $module_id = $module->getId();
            $scopes = $module->listEmailScopes();
            if (!is_array($scopes)) {
                throw new SystemException('Method listEmailScopes must return an array. Please check module "'.$module_id.'".');
            }

            foreach ($scopes as $code => $name) {
                $result[$module_id.':'.$code] = $name;
            }
        }
            
        ksort($result);
        return $result;
    }

    /**
     * Returns a list of 1st level tabs of all modules
     * @param string $moduleId Optional. Specifies a module identifier to return tabs for.
     * @returns array
     */
    public static function listTabs($moduleId = null)
    {
        if (self::$tabs == null) {
            $modules = self::listModules();
            $tabs = array();
            self::$moduleTabs = array();

            foreach ($modules as $curModuleId => $module) {
                $lowerModuleId = strtolower($curModuleId);
                $tabCollection = new TabCollection($lowerModuleId);
                $module->listTabs($tabCollection);
                self::$moduleTabs[$lowerModuleId] = $tabCollection->tabs;

                $tabs = array_merge($tabs, $tabCollection->tabs);
            }

            uasort($tabs, array('Core\ModuleManager', 'compareTabOrders'));
            self::$tabs = $tabs;
        }
            
        if ($moduleId === null) {
            return self::$tabs;
        }
            
        $result = array();
            
        $moduleId = strtolower($moduleId);
        if (!isset(self::$moduleTabs[$moduleId])) {
            return array();
        }

        return self::$moduleTabs[$moduleId];
    }
        
    /**
     *  Finds a tab by a module and tab identifier
     */
    public static function findTab($moduleId, $tabId)
    {
        $moduleId = strtolower($moduleId);
        $tabs = self::listTabs();
        foreach ($tabs as $tab) {
            if ($tab->id == $tabId && $tab->moduleId == $moduleId) {
                return $tab;
            }
        }
            
        return null;
    }
        
    /**
     * Returns a list of content tabs for a specified module and tab identifier
     */
    public static function getContentTabs($moduleId, $tabId)
    {
        $tab = self::findTab($moduleId, $tabId);
        if (!$tab) {
            return array();
        }
                
        $result = $tab->contentTabs;
        uasort($result, array('Core\ModuleManager', 'compareTabOrders'));

        return $result;
    }

    /**
     * Returns a module by its identifier
     * @param string $moduleId Specifies the module identifier
     * @return ModuleBase
     */
    public static function findById($moduleId)
    {
        $modules = self::listModules();

        if (isset($modules[$moduleId])) {
            return $modules[$moduleId];
        }

        return null;
    }
        
    /**
     * Returns module main menu notifications.
     * @return array
     */
    public static function listModulesMenuNotifications()
    {
        $result = array();
            
        $default = array('id'=>null, 'closable'=>false, 'text'=>null, 'icon'=>null, 'link'=>null);
            
        $modules = self::listModules();
        foreach ($modules as $id => $module) {
            $result[$id] = array();

            $notifications = $module->listMenuNotifications();
            if (!is_array($notifications)) {
                $notifications = array();
            }

            foreach ($notifications as $notification) {
                $result[$id][] = array_merge($default, $notification);
            }
        }
            
        return $result;
    }

    /**
     * Sorting functions
     */
        
    public static function compareTabOrders($tabA, $tabB)
    {
        if ($tabA->position == $tabB->position) {
            return 0;
        }

        if ($tabA->position > $tabB->position) {
            return 1;
        }

        return -1;
    }
        
    public static function compareDashboardItems($indA, $indB)
    {
        $nameA = isset($indA['name']) ? $indA['name'] : 'Unknown indicator';
        $nameB = isset($indB['name']) ? $indB['name'] : 'Unknown indicator';
            
        return strcmp($nameA, $nameB);
    }

    public static function compareSettingItems($a, $b)
    {
        $sort_a = isset($a['sort_id']) ? $a['sort_id'] : 10000;
        $sort_b = isset($b['sort_id']) ? $b['sort_id'] : 10000;
            
        if ($sort_a == $sort_b) {
            return 0;
        }

        if ($sort_a > $sort_b) {
            return 1;
        }

        return -1;
    }

    /**
     * Sorting function
     */
    public static function compareModuleInfo($moduleInfoA, $moduleInfoB)
    {
        return strcasecmp($moduleInfoA->getModuleInfo()->name, $moduleInfoB->getModuleInfo()->name);
    }
}
