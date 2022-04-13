<?php
namespace Phpr;

use Phpr\SystemException;
use Phpr\Inflector;

class Extension
{

    // Equivilent of "use", define this attribute instead
    //
    // Eg:
    // use my_model_class;
    //
    // Would be:
    // public $implement = 'my_model_class';
    //
    public $implement;
    public static $_implement_static = array();

    protected $extension_data = array(
        'extensions' => array(),
        'methods' => array(),
        'dynamic_methods' => array(),
        'processed_extensions' => array()
    );

    protected $extension_hidden = array(
        'fields' => array(),
        'methods' => array('extension_is_hidden_field', 'extension_is_hidden_field')
    );

    public function __construct()
    {
        // Implement static extensions, or use default array() as starter
        $class_name = get_called_class();
        $uses = (isset(self::$_implement_static[$class_name])) ? self::$_implement_static[$class_name] : array();

        if (!$this->implement && !count($uses)) {
            return;
        }

        if (!$this->implement) {
            $this->implement = array();
        }

        if (is_string($this->implement)) {
            $uses = array_merge($uses, explode(',', $this->implement));
        } else {
            if (is_array($this->implement)) {
                $uses = array_merge($uses, $this->implement);
            } else {
                throw new SystemException(
                    'Class ' . get_class($this) . ' contains an invalid ' . $this->implement . ' value'
                );
            }
        }

        $this->extend_with($uses);
    }

    protected function extension_hide_field($name)
    {
        $this->extension_hidden['fields'][] = $name;
    }

    protected function extension_hide_method($name)
    {
        $this->extension_hidden['methods'][] = $name;
    }

    public function extension_is_hidden_field($name)
    {
        return in_array($name, $this->extension_hidden['fields']);
    }

    public function extension_is_hidden_method($name)
    {
        return in_array($name, $this->extension_hidden['methods']);
    }

    public static function always_extend_with($class_name, $replace_previous = false)
    {
        $called_class_name = get_called_class();

        if (!isset(self::$_implement_static[$called_class_name]) || !is_array(
                self::$_implement_static[$called_class_name]
            )) {
            self::$_implement_static[$called_class_name] = array();
        }

        if ($replace_previous) {
            return self::$_implement_static[$called_class_name] = array($class_name);
        }

        if (!in_array($class_name, self::$_implement_static)) {
            return self::$_implement_static[$called_class_name][] = $class_name;
        }
    }

    public function extend_with($extension_objects, $recursion_extension = true, $_deprecatedProp = null)
    {
        if (!is_array($extension_objects)) {
            $extension_objects = array($extension_objects);
        }

        $new_extensions = array();

        foreach ($extension_objects as $extension_object) {
            if (is_string($extension_object)) {
                $extension_name = trim($extension_object);
                if (!$extension_name) {
                    continue;
                }

                if (array_key_exists($extension_name, $this->extension_data['extensions'])) {
                    throw new SystemException(sprintf('Extension "%s" already added', $extension_name));
                }

                $extension_object = new $extension_name($this);
                $this->extension_data['extensions'][$extension_name] = $extension_object;
            } else {
                if (is_object($extension_object)) {
                    $extension_name = \get_class_id($extension_object);

                    if (array_key_exists($extension_name, $this->extension_data['extensions'])) {
                        throw new SystemException(sprintf('Extension "%s" already added', $extension_name));
                    }

                    $this->extension_data['extensions'][$extension_name] = $extension_object;
                }
            }

            $new_extensions[$extension_name] = $extension_object;
        }

        foreach ($new_extensions as $extension_name => $extension_object) {
            $this->extension_extract_methods($extension_name, $extension_object);

            // Since we cannot process things from extensions in our constructor, we do it in the init_extension method.
            if ($extension_object->method_exists('init_extension')) {
                $extension_object->init_extension();
            }
        }

        if ($recursion_extension) {
            foreach ($new_extensions as $extension_name => $extension_object) {
                if (is_subclass_of($extension_object, 'Phpr\Extension')) {
                    $extension_object->extend_with($this, false);
                }
            }
        }
    }

    public function add_dynamic_method($extension, $dynamic_name, $actual_name)
    {
        $this->extension_data['dynamic_methods'][$dynamic_name] = array($extension, $actual_name);
    }

    protected function extension_extract_methods($extension_name, $extension_object)
    {
        $result = array(
            'methods' => array(),
            'extensions' => array()
        );

        $result = self::extension_extract_methods_recursive(
            $extension_name,
            $extension_object,
            \get_class_id($this),
            \get_class_id($extension_object),
            $result
        );
        $this->extension_data['methods'] = array_merge($this->extension_data['methods'], $result['methods']);
    }

    protected static function extension_extract_methods_recursive(
        $extension_name,
        $extension_object,
        $original_extension_name,
        $parent_extension_name,
        $result
    ) {
        // Don't look for methods on ourself
        if ($extension_name === $original_extension_name) {
            return $result;
        }

        // This extension has already been added to the result so let's move on
        if (in_array($extension_name . $parent_extension_name, $result['extensions'])) {
            return $result;
        }

        $extension_methods = get_class_methods($extension_name);
        foreach ($extension_methods as $method_name) {
            if ($method_name == '__construct' || $extension_object->extension_is_hidden_method($method_name)) {
                continue;
            }

            // We need to set the value to use the parent class so the methods cascade through the inheritence
            $result['methods'][$method_name] = $parent_extension_name;
        }

        $result['extensions'][] = $extension_name . $parent_extension_name;

        // Check the extension's extensions for methods that this parent extension can handle due to implementing them
        $sub_extensions = $extension_object->extension_data['extensions'];
        foreach ($sub_extensions as $sub_extension_name => $sub_extension_object) {
            $result = self::extension_extract_methods_recursive(
                $sub_extension_name,
                $sub_extension_object,
                $original_extension_name,
                $parent_extension_name,
                $result
            );
        }

        return $result;
    }

    public function is_extended_with($name)
    {
        foreach ($this->extension_data['extensions'] as $class_name => $extension) {
            if ($class_name == $name) {
                return true;
            }
        }

        return false;
    }

    public function get_extension($name)
    {
        return (isset($this->extension_data['extensions'][$name]))
            ? $this->extension_data['extensions'][$name]
            : null;
    }

    public function method_exists($name)
    {
        $methodNames = $this->includeLegacyNames($name);
        foreach ($methodNames as $methodName) {
            if (method_exists($this, $methodName)
                || isset($this->extension_data['methods'][$methodName])
                || isset($this->extension_data['dynamic_methods'][$methodName])) {
                return true;
            }
        }
        return false;
    }

    // Too Much Magic!
    //

    public function __get($name)
    {
        // build a list of names to check for a property
        $propertyNames = $this->includeLegacyNames($name);

        foreach ($propertyNames as $propertyName) {
            if (property_exists($this, $propertyName)) {
                return $this->{$propertyName};
            }

            foreach ($this->extension_data['extensions'] as $extension_object) {
                if (property_exists($extension_object, $propertyName)) {
                    return $extension_object->{$propertyName};
                }
            }
        }
        //throw new SystemException('Property ' . $name . ' is not defined in class ' . get_class($this));
    }

    public function __set($name, $value)
    {
        // build a list of names to check for a property
        $propertyNames = $this->includeLegacyNames($name);

        foreach ($propertyNames as $propertyName) {
            if (property_exists($this, $propertyName)) {
                return $this->{$propertyName} = $value;
            }

            foreach ($this->extension_data['extensions'] as $extension_object) {
                if (!isset($extension_object->{$propertyName})) {
                    continue;
                }

                return $extension_object->{$propertyName} = $value;
            }
        }

       // throw new SystemException('Property ' . $name . ' is not defined in class ' . get_class($this));
    }

    public function __call($name, $params = null)
    {
        // build a list of names to check for a method
        $methodNames = $this->includeLegacyNames($name);

        // loop until we finding a method to match the name
        foreach ($methodNames as $methodName) {
            if (method_exists($this, $methodName)) {
                return call_user_func_array(array($this, $methodName), $params);
            }

            if (isset($this->extension_data['methods'][$methodName])) {
                $extensionName = $this->extension_data['methods'][$methodName];
                $extensionObject = $this->extension_data['extensions'][$extensionName];

                return call_user_func_array(array($extensionObject, $methodName), $params);
            }

            if (isset($this->extension_data['dynamic_methods'][$methodName])) {
                $extensionObject = $this->extension_data['dynamic_methods'][$methodName][0];
                $actual_name = $this->extension_data['dynamic_methods'][$methodName][1];
                return call_user_func_array(array($extensionObject, $actual_name), $params);
            }
        }

//        throw new SystemException('Class ' . get_class($this) . ' does not have a method definition for ' . $name);
    }

    /**
     * Older modules may have outdated references using old code style.
     * This method returns the name requested along with CamelCase and PascalCase variants.
     * @param string $name Method/Function/Property name
     * @return array array of possible names
     */
    private function includeLegacyNames($name): array
    {
        return array(
            $name,
            Inflector::camelize($name),
            Inflector::underscore($name)
        );
    }


    /**
     * @deprecated
     */
    public function addDynamicMethod($extension, $dynamic_name, $actual_name)
    {
        return $this->add_dynamic_method($extension, $dynamic_name, $actual_name);
    }

    /**
     * @deprecated
     */
    public function methodExists($method)
    {
        return $this->method_exists($method);
    }

    /**
     * @deprecated
     */
    public function isExtendedWith($class_name)
    {
        return $this->is_extended_with($class_name);
    }

    /**
     * @deprecated
     */
    protected function extHideMethod($methodName)
    {
        $this->extension_hide_method($methodName);
    }
    /**
     * @deprecated
     */

    public function extMethodIsHidden($methodName)
    {
        return $this->extension_is_hidden_method($methodName);
    }

    /**
     * Hides a field from merging with an extendable class
     */
    protected function extHideField($fieldName)
    {
        $this->extension_hide_field($fieldName);
    }

    public function extFieldIsHidden($fieldName)
    {
        return $this->extension_is_hidden_field($fieldName);
    }
}
