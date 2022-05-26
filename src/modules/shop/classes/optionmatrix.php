<?php
namespace Shop;

use Db\DataCollection;
use Phpr\SystemException;

class OptionMatrix
{
    protected static $recordCache = array();

    /**
     * Returns product's property by the property name.
     * @param array|object $options Specifies product option values or OptionMatrixRecord object.
     * Option values should be specified in the following format:
     * ['Option name 1'=>'option value 1', 'Option name 2'=>'option value 2']
     * or: ['option_key_1'=>'option value 1', 'option_key_2'=>'option value 2']
     * Option keys and values are case sensitive. See also $option_keys parameter.
     * @param string $property_name Specifies the property name.
     * @param Product $product Specifies the product.
     * @param boolean $option_keys Indicates whether array keys in the
     *  $options parameter represent option keys (md5(name))
     * rather than option names. Otherwise $options keys are considered to be plain option name.
     * @return mixed Returns the property value.
     */
    public static function get_property($options, $property_name, $product, $option_keys = false)
    {
        if (is_array($options)) {
            $type = $option_keys ? 'keys' : 'values';
            $hash = sha1(serialize($options) . '_' . $product->id);
            $options_cache_key = $hash . '_' . $type;
            if (!array_key_exists($options_cache_key, self::$recordCache)) {
                $record = OptionMatrixRecord::find_record($options, $product, $option_keys);
                self::$recordCache[$options_cache_key] = $record;
            }
            $om_record = self::$recordCache[$options_cache_key];
        } elseif (is_object($options) && $options instanceof OptionMatrixRecord) {
            $om_record = $options;
        } else {
            throw new SystemException(
                'Invalid argument passed to OptionMatrix::get_property() first parameter.'
            );
        }


        if ($om_record) {
            /*
             * If the property is not supported, fallback to the product's property
             */

            $product_property_name = $om_record->is_property_supported($property_name);
            if ($product_property_name !== true) {
                return self::getProductProperty($product, $property_name);
            }

            /*
             * If record is found, load its property value
             */

            $result = $om_record->$property_name;

            /*
             * If property is price or sale_price, return the OM record price -
             * it will fallback to the product price automatically if needed.
             */

            if ($property_name == 'price') {
                return $om_record->get_price($product);
            }

            if ($property_name == 'sale_price') {
                return $om_record->get_sale_price($product);
            }

            if ($property_name == 'is_on_sale') {
                return $om_record->is_on_sale($product);
            }

            if ($property_name == 'is_out_of_stock') {
                return $om_record->is_out_of_stock($product);
            }

            if ($property_name == 'is_low_stock') {
                return $om_record->is_low_stock($product);
            }

            if ($property_name == 'volume') {
                return $om_record->get_volume($product);
            }

            /*
             * If the property is empty, fallback to the product's property
             */

            if ((is_object($result) && ($result instanceof DataCollection) && !$result->count) ||
                (is_array($result) && !count($result)) ||
                (!is_object($result) && !is_array($result) && !strlen($result))
            ) {
                return self::getProductProperty($product, $property_name);
            }

            /*
             * Some properties (price) require extra processing
             */

            //

            return $result;
        }

        /*
         * If record is not found, but product has Option Matrix records and the requesed property is "disabled"
         * return TRUE - we consider non-existing Option Matrix records as disabled.
         */

        if ($property_name == 'disabled' && $product->has_om_records()) {
            return true;
        }

        /*
         * If record is not found, fallback to the product's property
         */

        return self::getProductProperty($product, $property_name);
    }

    /**
     * Resets internal option record cache.
     */
    public static function reset_cache()
    {
        self::$recordCache = array();
    }

    protected static function getProductProperty($product, $property_name)
    {
        $product = is_object($product) ? $product : Product::find_by_id($product);
        if (!$product) {
            return null;
        }

        if ($property_name == 'price') {
            return $product->price();
        }

        if ($property_name == 'sale_price') {
            return $product->get_sale_price();
        }

        if ($property_name == 'is_on_sale') {
            return $product->is_on_sale();
        }

        if ($property_name == 'is_out_of_stock') {
            return $product->is_out_of_stock();
        }

        if ($property_name == 'is_low_stock') {
            return $product->is_low_stock();
        }

        if ($property_name == 'volume') {
            return $product->volume();
        }

        return $product->$property_name;
    }
}
