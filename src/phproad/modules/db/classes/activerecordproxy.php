<?php namespace Db;

class ActiverecordProxy
{
    private $proxyModelClass;
    private $proxyModelKey;
    private $proxyModelFields = array();
    private $proxyLightObj;
    private $proxyHeavyObj;
    private $proxyStrict = false;

    protected static $proxiable_methods = array();

    public function __construct($key, $model_class, $fields, $strict = false)
    {
        $this->proxyModelKey = $key;
        $this->proxyModelClass = $model_class;
        $this->proxyModelFields = $fields;
        $this->proxyStrict = $strict;
    }

    public function __set($field, $value)
    {
        if ($this->proxyStrict && !array_key_exists($field, $this->proxyModelFields)) {
            $error = "var {$field} is not a proxied property of " . $this->get_proxied_model_class() . '.';
            throw new \RuntimeException($error);
        }
        $this->proxyModelFields[$field] = $value;
    }

    public function __get($field)
    {
        if (array_key_exists($field, $this->proxyModelFields)) {
            return $this->proxyModelFields[$field];
        }

        return $this->get_object()->$field;
    }

    /*
     * Check if proxy loaded value exists for the given field name
     */
    public function __isset($field)
    {
        return isset($this->proxyModelFields[$field]);
    }

    public function __unset($field)
    {
        if ($this->proxyStrict) {
            if (!array_key_exists($field, $this->proxyModelFields)) {
                $error = "var {$field} is not a proxied property of " . $this->get_proxied_model_class() . '.';
                throw new \RuntimeException($error);
            }
            $this->proxyModelFields = null;
        } else {
            unset($this->proxyModelFields[$field]);
        }
    }

    public function __call($method, $arguments = array())
    {
        /*
         * Try to call extension methods
         */

        if (array_key_exists($method, $this->extension_data['methods'])) {
            return parent::__call($method, $arguments);
        }

        /*
         * Try to call a proxiable method
         */

        $proxiable_method_name = $method . '_proxiable';

        if (array_key_exists($this->proxyModelClass, self::$proxiable_methods)
            && array_key_exists($method, self::$proxiable_methods[$this->proxyModelClass])
        ) {
            $proxiable = self::$proxiable_methods[$this->proxyModelClass][$method];
        } else {
            $proxiable = method_exists($this->proxyModelClass, $proxiable_method_name);
            if (array_key_exists($this->proxyModelClass, self::$proxiable_methods)) {
                self::$proxiable_methods[$this->proxyModelClass] = array();
            }

            self::$proxiable_methods[$this->proxyModelClass][$method] = $proxiable;
        }

        if ($proxiable) {
            array_unshift($arguments, $this);
            return call_user_func_array(array($this->proxyModelClass, $proxiable_method_name), $arguments);
        }

        /*
         * Create a light model object and call its method
         */

        if ($this->has_proxiable_method($method)) {
            return call_user_func_array(array($this->get_object(true), $method), $arguments);
        }

        /*
         * Create a heavy model object and call its method
         */
        return call_user_func_array(array($this->get_object(false), $method), $arguments);
    }

    public function get_proxied_model_class()
    {
        return $this->proxyModelClass;
    }

    public static function is_a($obj, $class_name)
    {
        if (is_a($obj, $class_name)) {
            return true;
        }
        if (is_a($obj, 'Db\ActiveRecordProxy')) {
            if ($class_name == $obj->get_proxied_model_class()) {
                return true;
            }
        }
        return false;
    }

    protected function has_proxiable_method($method)
    {
        $class = $this->proxyModelClass;
        if (class_exists($class)) {
            if (property_exists($class, 'proxiable_methods') && is_array($class::$proxiable_methods)) {
                $proxiable_methods = $class::$proxiable_methods;
                if (in_array($method, $proxiable_methods)) {
                    $proxiable = method_exists($this->proxyModelClass, $method);
                    return $proxiable;
                }
            }
        }
        return false;
    }

    protected function get_object($light = false)
    {
        if ($this->proxyHeavyObj) { //no point loading a light object when already loaded heavy
            return $this->proxyHeavyObj;
        }

        $model_options = array();
        if ($light && $this->proxyLightObj) {
            if ($this->proxyLightObj) {
                return $this->proxyLightObj;
            }
            $model_options = array(
                'no_validation' => true,
                'no_column_init' => true,
                'no_timestamps' => true,
            );
        }

        $obj = new $this->proxyModelClass($this->proxyModelFields, $model_options);
        if ($light) {
            return $this->proxyLightObj = $obj;
        }
        return $this->proxyHeavyObj = $obj->find($this->proxyModelKey);
    }
}
