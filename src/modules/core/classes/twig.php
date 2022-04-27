<?php
namespace Core;

use Twig\Environment;
use FileSystem\Directory;
use Phpr\ApplicationException;
use Twig\TwigFunction;

class Twig
{
    protected $environment = null;
    protected $loader = null;
    protected static $instance = null;
        
    public function __construct()
    {
        $cache_dir = $this->get_cache_dir();
            
        $this->loader = new TwigNamedStringLoader();
        $this->environment = new Environment($this->loader, array('cache'=>$cache_dir, 'auto_reload'=>true));
        $this->configure_environment();
    }
        
    protected function get_cache_dir()
    {
        $cache_dir = PATH_APP.'/temp/twig_cache';
            
        if (!file_exists($cache_dir) || !is_dir($cache_dir)) {
            if (!@mkdir($cache_dir, Directory::getPermissions())) {
                throw new ApplicationException('Error creating Twig cache directory (temp/twig_cache)');
            }
        }

        return $cache_dir;
    }
        
    public function parse($string, $parameters, $object_name = 'Template')
    {
        $this->loader->source = $string;
            
        return $this->environment->render($object_name, $parameters);
    }
        
    protected function configure_environment()
    {
        $html_safe = array('is_safe' => array('html'));

        $this->environment->addFunction('resource_url', new TwigFunction('resource_url', array(null,'resource_url')));
        $this->environment->addFunction('site_url', new TwigFunction('site_url', array(null,'site_url')));
        $this->environment->addFunction('open_form', new TwigFunction('open_form', array(null,'open_form'), $html_safe));
        $this->environment->addFunction('close_form', new TwigFunction('close_form', array(null,'close_form'), $html_safe));
        $this->environment->addFunction('root_url', new TwigFunction('root_url', array(null,'root_url'), $html_safe));
        $this->environment->addFunction('traceLog', new TwigFunction('traceLog', array(null,'traceLog')));
        $this->environment->addFunction('trace_log', new TwigFunction('traceLog',array(null,'traceLog')));
        $this->environment->addFunction('format_currency', new TwigFunction('format_currency',array(null,'format_currency')));
        $this->environment->addFunction('uniqid', new TwigFunction('uniqid',array(null,'uniqid')));
        $this->environment->addFunction('post', new TwigFunction('post',array(null,'post')));
        $this->environment->addFunction('post_array_item', new TwigFunction('post_array_item',array(null,'post_array_item')));
        $this->environment->addFunction('zebra', new TwigFunction('zebra', array(null,'zebra'), $html_safe));

        $core_extension = new TwigExtension();
        $this->environment->addExtension($core_extension);

        $functions = $core_extension->getFunctions();
        foreach ($functions as $function) {
            $twigFunction = new TwigFunction(
                $function,
                array(
                    $core_extension,
                    $function
                ),
                array(
                    'is_safe' => ['html']
                )
            );
            $this->environment->addFunction($function, $twigFunction);
        }
    }
        
    public static function get()
    {
        if (self::$instance != null) {
            return self::$instance;
        }
                
        return self::$instance = new self();
    }
}
