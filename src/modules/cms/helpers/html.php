<?php
namespace Cms;

use Phpr;

class Html
{
    public static function flash()
    {
        $result = null;

        foreach (Phpr::$session->flash as $type => $message) {
            if ($type == 'system') {
                continue;
            }

            $result .= '<p class="flash '.$type.'">'.h($message).'</p>';
        }

        Phpr::$session->flash->now();

        return $result;
    }
        
    public static function content_block($code, $name)
    {
        global $_cms_current_page_object;
        if (!$_cms_current_page_object) {
            return;
        }

        return $_cms_current_page_object->get_content_block_content($code);
    }
        
    public static function global_content_block($code, $return_content = false)
    {
        $block = GlobalContentBlock::get_by_code($code);
        if (!$block) {
            return sprintf('Global content block "%s" not found.', $code);
        }
                
        if ($return_content) {
            return $block->content;
        }
                
        echo $block->block_type == 'text' ? h($block->content) : $block->content;
    }
        
    public static function site_url($url, $add_host_name_and_protocol = true)
    {
        return root_url($url, $add_host_name_and_protocol);
    }
        
    /**
     * Returns file URL relative to the currently active theme resources directory.
     * @param string $path File path in the theme resources directory.
     * @param boolean $root_url Return URL relative to the LSAPP domain root.
     * @param string $add_host_name_and_protocol Return absolute URL with the host name and protocol.
     * This parameter works only if the $root_url parameter is true.
     */
    public static function theme_resource_url($path, $root_url = true, $add_host_name_and_protocol = false)
    {
        $url = $path;

        if (substr($url, 0, 1) != '/') {
            $url = '/'.$url;
        }
            
        if (Theme::is_theming_enabled() && ($theme = Theme::get_active_theme())) {
            $url = $theme->get_resources_path().$url;
        } else {
            $url = SettingsManager::get()->resources_dir_path.$url;
        }

        if ($root_url) {
            return root_url($url, $add_host_name_and_protocol);
        }
                
        return $url;
    }
        
    /**
     * Returns file URL relative to the website resources directory (/resources by default).
     * @param string $path File path in the resources directory.
     * @param boolean $root_url Return URL relative to the LSAPP domain root.
     * @param string $add_host_name_and_protocol Return absolute URL with the host name and protocol.
     * This parameter works only if the $root_url parameter is true.
     */
    public static function resource_url($path, $root_url = true, $add_host_name_and_protocol = false)
    {
        $url = $path;
            
        if (substr($url, 0, 1) != '/') {
            $url = '/'.$url;
        }
            
        $url = SettingsManager::get()->resources_dir_path.$url;
                
        if ($root_url) {
            return root_url($url, $add_host_name_and_protocol);
        }
                
        return $url;
    }
        
    public static function include_resources($src_mode = false)
    {
        $scriptStart = '<script type="text/javascript" src="';
        $scriptEnd = '&amp;ver='.module_build('cms').'"></script>'."\n";
        $result = '';
        if ($src_mode) {
            $scripts = [
                root_url('/modules/cms/resources/javascript/mootools_src.js'),
                root_url('/modules/cms/resources/javascript/mootools_more_src.js'),
                root_url('/modules/cms/resources/javascript/frontend_src.js?dir='.Phpr::$request->getSubdirectory()),
            ];
            foreach ($scripts as $script) {
                $result .= $scriptStart.$script.$scriptEnd;
            }
            $cssUri = root_url('/modules/cms/resources/css/frontend_css.css');
            $result .= '<link rel="stylesheet" type="text/css" href="'.$cssUri.'" />'."\n";
        } else {
            $scriptUri = root_url('/modules/cms/resources/javascript/frontend.js');
            $scriptUri .= '?dir='.Phpr::$request->getSubdirectory();
            $result .= $scriptStart.$scriptUri.$scriptEnd;
            $cssUri = root_url('/modules/cms/resources/css/frontend_css.css');
            $result .= '<link rel="stylesheet" type="text/css" href="'.$cssUri.'" />'."\n";
        }
        return $result;
    }
}
