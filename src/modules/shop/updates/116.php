<?php
use Db\Helper as DbHelper;

/*
 * Stripped tag description to assist with product search.
 */
$products = DbHelper::objectArray('select * from shop_products');
foreach ($products as $product) {
    $description = html_entity_decode(strip_tags($product->description), ENT_QUOTES, 'UTF-8');

    DbHelper::query(
        'update shop_products set pt_description=:pt_description where id=:id',
        [
        'pt_description'=>$description,
        'id'=>$product->id
        ]
    );
}
