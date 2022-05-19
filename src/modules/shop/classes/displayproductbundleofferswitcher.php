<?php
namespace Shop;

use Db\DataFilterSwitcher;

class DisplayProductBundleOfferSwitcher extends DataFilterSwitcher
{
    public function applyToModel($model, $enabled, $context = null)
    {
        if ($enabled) {
            $model->where('(shop_products.id IN(SELECT product_id FROM shop_product_bundle_items))');
        }
            
        return $model;
    }
}
