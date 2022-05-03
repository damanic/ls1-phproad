<?php
namespace Cms;

use Backend;
use Core\Twig as CoreTwig;
use Phpr\ApplicationException;
use FileSystem\File;
use Twig\TwigFunction;

class Twig extends CoreTwig
{
    private $controller_extension;
    private $controller;
        
    public function __construct($controller)
    {
        $this->controller = $controller;
        parent::__construct();
    }
        
    protected function configure_environment()
    {
        parent::configure_environment();
            
        $this->controller_extension = new TwigControllerExtension($this->controller);
        $this->environment->addExtension($this->controller_extension);

        /*
         * Register CMS functions
         */
        $html_safe = array('is_safe' => array('html'));
        $this->environment->addFunction(
            'content_block',
            new TwigFunction('content_block', [null, 'content_block'], $html_safe)
        );
        $this->environment->addFunction(
            'option_state',
            new TwigFunction('option_state', [null, 'option_state'], $html_safe)
        );
        $this->environment->addFunction(
            'checkbox_state',
            new TwigFunction('checkbox_state', [null, 'checkbox_state'], $html_safe)
        );
        $this->environment->addFunction(
            'radio_state',
            new TwigFunction('radio_state', [null, 'radio_state'], $html_safe)
        );
        $this->environment->addFunction(
            'post',
            new TwigFunction('post', [null, 'post'])
        );
        $this->environment->addFunction(
            'flash',
            new TwigFunction('flash', [null, 'flash'])
        );
        $this->environment->addFunction(
            'flash_message',
            new TwigFunction('flash_message', [null, 'flash_message'], $html_safe)
        );
        $this->environment->addFunction(
            'global_content_block',
            new TwigFunction('global_content_block', [null, 'global_content_block'], $html_safe)
        );
        $this->environment->addFunction(
            'include_resources',
            new TwigFunction('include_resources', [null, 'include_resources'], $html_safe)
        );
        $this->environment->addFunction(
            'process_ls_tags',
            new TwigFunction('process_ls_tags', [null, 'process_ls_tags'], $html_safe)
        );
        $this->environment->addFunction(
            'theme_resource_url',
            new TwigFunction('theme_resource_url', [null, 'theme_resource_url'])
        );
            
        $functions = $this->controller_extension->getFunctions();
        foreach ($functions as $function) {
            $twigFunction =  new TwigFunction(
                $function,
                [$this->controller_extension, $function],
                $html_safe
            );
        }
                
        /*
         * Allow other modules to register Twig extensions
         */
        Backend::$events->fireEvent('cms:onRegisterTwigExtension', $this->environment);
    }
        
    protected function get_cache_dir()
    {
        $cache_dir = parent::get_cache_dir();
            
        if (Theme::is_theming_enabled()) {
            $theme = Theme::get_active_theme();
            if ($theme) {
                $cache_dir .= '/'.$theme->code;
                if (!file_exists($cache_dir) || !is_dir($cache_dir)) {
                    if (!@mkdir($cache_dir, File::getPermissions())) {
                        throw new ApplicationException(
                            'Error creating Twig cache directory (temp/twig_cache/'.$theme->code.')'
                        );
                    }
                }
            }
        }

        return $cache_dir;
    }
}
