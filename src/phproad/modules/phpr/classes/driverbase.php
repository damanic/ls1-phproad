<?php
namespace Phpr;

use FileSystem\Path;

/**
 * PHPR module driver base class
 *
 * This class assists in working with module drivers
 */
class DriverBase extends Extension
{
    // Driver folder name
    public static $driverFolder;

    // Driver file suffix
    public static $driverSuffix;

    protected $hostObject = null;

    public function __construct($obj = null)
    {
        parent::__construct();

        $this->hostObject = $obj;
    }

    public function getHostObject()
    {
        return $this->hostObject;
    }

    /**
     * Returns full relative path to a resource file situated in the driver's resources directory.
     * @param string $path Specifies the relative resource file name, for example '/assets/javascript/widget.js'
     * @return string Returns full relative path, suitable for passing to the controller's add_css() or add_javascript() method.
     */
    public function getVendorPath($path)
    {
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }

        $class_name = get_class($this);
        $class_path = Path::getPathToClass($class_name);
        return $class_path . '/' . strtolower($class_name) . '/vendor' . $path;
    }

    public function getPartialPath($partial_name = null)
    {
        $class_name = get_class($this);
        $class_path = Path::getPathToClass($class_name);
        return $class_path . '/' . strtolower($class_name) . '/partials/' . $partial_name;
    }

    public function getPublicAssetPath($partial_name = null)
    {
        $class_name = get_class($this);
        $class_path = Path::getPathToClass($class_name);
        $local_path = $class_path . '/' . strtolower($class_name) . '/assets/' . $partial_name;
        return Path::getPublicPath($local_path);
    }
}