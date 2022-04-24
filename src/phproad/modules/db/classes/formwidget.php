<?php namespace Db;

use ReflectionObject;

use Phpr;
use Phpr\SystemException;
use FileSystem\Path;

/**
 * Base class for form widgets. Form widgets can render custom form controls,
 * and provide their life cycle operations.
 */
class FormWidget
{
    public $unique_id;
    public $column_name;
    public $model;
    public $model_class;
    public $widget_class;

    private static $loadedWidgets = array();

    protected $controller;
    protected $view_path;
    protected $resources_path;
    protected $configuration;
    protected $view_data = array();

    public function __construct($controller, $model, $column_name, $configuration)
    {
        $ref_object = new \ReflectionObject($this);
        $widget_root_dir = str_replace("\\", "/", dirname($ref_object->getFileName())) . '/' . strtolower(
                get_class($this)
            );

        $this->view_path = $widget_root_dir.DS.'partials';
        $this->resources_path = Path::getPublicPath($widget_root_dir.DS.'resources');

        $this->controller = $controller;
        $this->model = $model;
        $this->column_name = $column_name;
        $this->configuration = $configuration;

        foreach ($configuration as $name => $value) {
            $this->$name = $value;
        }

        $this->load_resources();

        if (!Phpr::$request->isRemoteEvent())
            self::$loadedWidgets[$this->widget_class] = true;
    }

    /**
     * Adds widget-specific resource files. Use $this->controller->addJavaScript() and $this->controller->addCss()
     * to register new resources.
     */
    protected function load_resources()
    {
    }

    /**
     * Outputs <link> and <script> tags to load widget specific resource files. This method has an inbuilt safe guard
     * to only include the widget resources once.
     */
    public function include_resources($force = false)
    {
        if (array_key_exists($this->widget_class, self::$loadedWidgets) && !$force)
            return;

        self::$loadedWidgets[$this->widget_class] = true;

        return $this->controller->load_resources();
    }

    /**
     * Tries to render a controller partial, and if it does not exist, renders the widget partial with the same name.
     *
     * @param string $view_name           Specifies a view name
     * @param array  $params              A list of parameters to pass to the partial file
     * @param bool   $override_controller Indicates that the controller partial should be overridden
     *                                    by the widget partial even if the controller partial does
     *                                    exist.

     * @param  bool   $throw               Indicates that an exception should be thrown in case if the partial does not exist
     * @return bool
     */
    public function render_partial($view_name, $params = array(), $override_controller = false, $throw = true)
    {
        $this->renderPartialFile(
            $this->controller->getViewsDirPath(),
            $view_name,
            $params,
            $override_controller,
            $throw
        );
    }

    private function renderPartialFile(
        $controller_view_path,
        $view_name,
        $params = array(),
        $override_controller = false,
        $throw = true
    ) {
        $this->controller->viewData = $this->view_data + $this->controller->viewData;
        $controller_view_path = $controller_view_path . '/_' . $view_name . '.htm';

        if (!$override_controller && file_exists($controller_view_path)) {
            $this->controller->renderPartial($controller_view_path, $params, true, true);
        } else {
            $view_path = $this->view_path . '/_' . $view_name . '.htm';
            if (!$throw && !file_exists($view_path)) {
                return;
            }

            $this->controller->renderPartial($view_path, $params, true, true);
        }
    }

    public function handle_event($event, $model, $field)
    {
        if (substr($event, 0, 2) != 'on') {
            throw new SystemException('Invalid widget event name: ' . $event);
        }

        if (!method_exists($this, $event)) {
            throw new SystemException(
                sprintf('Event handler %s not found in widget %s.', $event, get_class($this))
            );
        }

        $this->$event($field, $model);
    }

    public function render()
    {
    }

    //
    // Getters
    //

    /**
     * Returns full relative path to a resource file situated in the widget's resources directory.
     * @param string $path Specifies the relative resource file name, for example '/assets/javascript/widget.js'
     * @return string Returns full relative path, suitable for passing to the controller's add_css() or add_javascript() method.
     */
    protected function getPublicAssetPath($path)
    {
        if (substr($path, 0, 1) != '/')
            $path = '/'.$path;

        return $this->resources_path.$path;
    }

    public function getFormId()
    {
        return $this->getElementId('form');
    }

    public function getId($identifier=null)
    {
        if ($identifier === null)
            $identifier = $this->unique_id;

        return get_class_id($this->widget_class).get_class_id($this->model_class)."_".$identifier;
    }

    public function getElementId($element)
    {
        return $this->getId().'_'.$element;
    }

    public function getEventHandlerData($handler)
    {
        return "phpr_event_field: '".$this->column_name."', phpr_custom_event_name: '".$handler."'";
    }


    /**
     * @deprecated
     */
    protected function map_resource_file($path)
    {
        return $this->getPublicAssetPath($path);
    }
}
