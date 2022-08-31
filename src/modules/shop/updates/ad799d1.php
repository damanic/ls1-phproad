<?php
use Db\Helper as DbHelper;

if (Phpr::$config->get('NESTED_CATEGORY_URLS')) {
    DbHelper::query('update shop_configuration set nested_category_urls=1');
}
