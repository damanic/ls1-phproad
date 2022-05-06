<?php
namespace Shop;

use Db\DataFilter;

class CategoryFilter extends DataFilter
{
    public $model_class_name = 'Shop\Category';
    public $list_columns = array('name');
        
    public function prepareListData()
    {
        $className = $this->model_class_name;
        return new $className();
    }

    public function applyToModel($model, $keys, $context = null)
    {
        if ($context == 'product_report') {
            $model->where(
                '(exists 
                    (select * from shop_products_categories 
                     where shop_products_categories.shop_product_id=(
                        if( shop_products.grouped is null or shop_products.grouped=0, 
                            shop_products.id, 
                            shop_products.product_id
                        )
                    )
                    and shop_products_categories.shop_category_id in (?))
                )',
                array($keys)
            );
            return;
        }
            
        if ($context != 'product_list') {
            $model->where(
                '(exists 
                    (select shop_products.id from shop_products, shop_order_items, shop_products_categories 
                     where shop_products.id=shop_order_items.shop_product_id 
                     and shop_order_items.shop_order_id=shop_orders.id 
                     and shop_products_categories.shop_product_id=(
                         if(shop_products.grouped is null or shop_products.grouped=0, 
                         shop_products.id, 
                         shop_products.product_id)
                     ) 
                     and shop_products_categories.shop_category_id in (?))
                )',
                array($keys)
            );
        } else {
            $model->where(
                '(exists 
                    (select * from shop_products_categories 
                        where shop_products_categories.shop_product_id=(
                            if(shop_products.grouped is null or shop_products.grouped=0, 
                            shop_products.id, 
                            shop_products.product_id)
                    ) 
                    and shop_products_categories.shop_category_id in (?))
                )',
                array($keys)
            );
        }
    }
        
    public function asString($keys, $context = null)
    {
        $keysStr = $this->keysToStr($keys);
            
        return "and (exists 
                        (select shop_products.id from shop_products_categories 
                        where shop_products.id=shop_order_items.shop_product_id 
                        and shop_order_items.shop_order_id=shop_orders.id 
                        and shop_products_categories.shop_product_id=(
                            if(shop_products.grouped is null or shop_products.grouped=0, 
                            shop_products.id, 
                            shop_products.product_id)
                        ) 
                        and shop_products_categories.shop_category_id in $keysStr)
                    )";
    }
}
