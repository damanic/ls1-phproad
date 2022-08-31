<?php
use Db\Helper as DbHelper;

$tax_classes = DbHelper::objectArray('select * from shop_tax_classes');
foreach ($tax_classes as $tax_class) {
    try {
        $tax_class->rates = trim($tax_class->rates);
        if (strlen($tax_class->rates)) {
            $rates = unserialize($tax_class->rates);
            foreach ($rates as &$rate) {
                $rate['zip'] = '*';
                $rate['city'] = '';
                $rate['priority'] = '1';
                $rate['tax_name'] = 'TAX';
                $rate['compound'] = '0';
            }
            $rates = serialize($rates);
        } else {
            $rate = [];
            $rate['country'] = '*';
            $rate['state'] = '*';
            $rate['rate'] = '0';
            $rate['zip'] = '*';
            $rate['city'] = '';
            $rate['priority'] = '1';
            $rate['tax_name'] = 'TAX';
            $rate['compound'] = '0';
            $rates = serialize([$rate]);
        }
        DbHelper::query(
            'update shop_tax_classes set rates=:rates where id=:id',
            ['rates'=>$rates, 'id'=>$tax_class->id]
        );
    } catch (\Exception $ex) {
    }
}
