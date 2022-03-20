<?php

class Db_ActiverecordProxy extends Phpr_Extensible
{
    private $_proxy_model_class;
    private $_proxy_model_key;
    private $_proxy_model_fields = array();
    private $_proxy_light_obj;
    private $_proxy_heavy_obj;
    private $_proxy_strict = false;

    protected static $proxiable_methods = array();

    public function __construct($key, $model_class, $fields, $strict = false)
    {
        $this->_proxy_model_key = $key;
        $this->_proxy_model_class = $model_class;
        $this->_proxy_model_fields = $fields;
        $this->_proxy_strict = $strict;
    }

    public function __set($field, $value)
    {
        if ($this->_proxy_strict && !array_key_exists($field, $this->_proxy_model_fields)) {
            $error = "var {$field} is not a proxied property of " . $this->get_proxied_model_class() . '.';
            throw new \RuntimeException($error);
        }
        $this->_proxy_model_fields[$field] = $value;
    }

    public function __get($field)
    {
        if (array_key_exists($field, $this->_proxy_model_fields)) {
            return $this->_proxy_model_fields[$field];
        }

        return $this->get_object()->$field;
    }

    /*
     * Check if proxy loaded value exists for the given field name
     */
    public function __isset($field)
    {
        return isset($this->_proxy_model_fields[$field]);
    }

    public function __unset($field)
    {
        if ($this->_proxy_strict) {
            if (!array_key_exists($field, $this->_proxy_model_fields)) {
                $error = "var {$field} is not a proxied property of " . $this->get_proxied_model_class() . '.';
                throw new \RuntimeException($error);
            }
            $this->_proxy_model_fields = null;
        } else {
            unset($this->_proxy_model_fields[$field]);
        }
    }

    public function __call($method, $arguments = array())
    {
        /*
         * Try to call extension methods
         */

        if (array_key_exists($method, $this->extensible_data['methods'])) {
            return parent::__call($method, $arguments);
        }

        /*
         * Try to call a proxiable method
         */

        $proxiable_method_name = $method . '_proxiable';

        if (array_key_exists($this->_proxy_model_class, self::$proxiable_methods) 
            && array_key_exists($method, self::$proxiable_methods[$this->_proxy_model_class])
        ) {
            $proxiable = self::$proxiable_methods[$this->_proxy_model_class][$method];
        } else {
            $proxiable = method_exists($this->_proxy_model_class, $proxiable_method_name);
            if (array_key_exists($this->_proxy_model_class, self::$proxiable_methods)) {
                self::$proxiable_methods[$this->_proxy_model_class] = array();
            }

            self::$proxiable_methods[$this->_proxy_model_class][$method] = $proxiable;
        }

        if ($proxiable) {
            array_unshift($arguments, $this);
            return call_user_func_array(array($this->_proxy_model_class, $proxiable_method_name), $arguments);
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
        return $this->_proxy_model_class;
    }

    public static function is_a($obj, $class_name)
    {
        if (is_a($obj, $class_name)) {
            return true;
        }
        if (is_a($obj, 'Db_ActiveRecordProxy')) {
            if ($class_name == $obj->get_proxied_model_class()) {
                return true;
            }
        }
        return false;
    }

    protected function has_proxiable_method($method)
    {
        $class = $this->_proxy_model_class;
        if (class_exists($class)) {
            if (property_exists($class, 'proxiable_methods') && is_array($class::$proxiable_methods)) {
                $proxiable_methods = $class::$proxiable_methods;
                if (in_array($method, $proxiable_methods)) {
                    $proxiable = method_exists($this->_proxy_model_class, $method);
                    return $proxiable;
                }
            }
        }
        return false;
    }

    protected function get_object($light = false)
    {
        if ($this->_proxy_heavy_obj) { //no point loading a light object when already loaded heavy
            return $this->_proxy_heavy_obj;
        }

        $model_options = array();
        if ($light && $this->_proxy_light_obj) {
            if ($this->_proxy_light_obj) {
                return $this->_proxy_light_obj;
            }
            $model_options = array(
                'no_validation' => true,
                'no_column_init' => true,
                'no_timestamps' => true,
            );
        }

        $obj = new $this->_proxy_model_class($this->_proxy_model_fields, $model_options);
        if ($light) {
            return $this->_proxy_light_obj = $obj;
        }
        return $this->_proxy_heavy_obj = $obj->find($this->_proxy_model_key);
    }
}
