<?php
namespace Shop;

use Phpr;
use Db\DataCollection;

/**
 * Manage the list of products added to the Compare Products list.
 * This class has methods for adding products to the list, loading added products,
 * removing specific products from the list and clearing the list. The Shop module also has a number of
 * AJAX handlers which you can use for managing the product compare list. {@link action@shop:compare} action
 * uses this class internally.
 * @documentable
 * @see https://lsdomainexpired.mjman.net/docs/implementing_the_compare_products_feature/
 * Implementing the Compare Products feature
 * @see action@shop:compare
 * @see ajax@shop:on_addToCompare
 * @see ajax@shop:on_removeFromCompare
 * @see ajax@shop:on_clearCompareList
 * @author LSAPP - MJMAN
 * @package shop.classes
 */
class ComparisonList
{
    /**
     * Adds a product to the product comparison list.
     * @documentable
     * @param int $product_id Specifies the product identifier.
     */
    public static function add_product($product_id)
    {
        $product_id = trim($product_id);

        if (!preg_match('/^[0-9]+$/', $product_id)) {
            return;
        }

        $items = self::list_product_ids();
        $items[$product_id] = 1;

        self::set_product_ids($items);
    }
        
    /**
     * Returns a list of products previously added with {@link ComparisonList::add_product() add_product()} method.
     * @documentable
     * @return DataCollection Returns an collection of {@link Product} objects.
     */
    public static function list_products()
    {
        $items = self::list_product_ids();
        $items = array_keys($items);
        $result = array();

        if ($items) {
            $products = Product::create()->where('shop_products.id in (?)', array($items))->find_all();
            $productsArray = $products->as_array(null, 'id');
            $result = array();
            foreach ($items as $item_id) {
                if (array_key_exists($item_id, $productsArray)) {
                    $result[] = $productsArray[$item_id];
                }
            }
        }

        return new DataCollection($result);
    }

    /**
     * Removes all products from the comparison lis.
     * @documentable
     */
    public static function clear()
    {
        self::set_product_ids(array());
    }

    /**
     * Removes a product from the product comparison list.
     * @documentable
     * @param int $product_id Specifies the product identifier.
     */
    public static function remove_product($product_id)
    {
        $product_id = trim($product_id);

        $items = self::list_product_ids();
        if (isset($items[$product_id])) {
            unset($items[$product_id]);
        }

        self::set_product_ids($items);
    }
        
    protected static function list_product_ids()
    {
        return Phpr::$session->get('comparison_list_items', array());
    }
        
    protected static function set_product_ids($ids)
    {
        Phpr::$session->set('comparison_list_items', $ids);
    }
}