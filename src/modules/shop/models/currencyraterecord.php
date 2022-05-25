<?php
namespace Shop;

use Db\ActiveRecord;
use Db\Helper as DbHelper;

class CurrencyRateRecord extends ActiveRecord
{
    public $table_name = 'shop_currency_exchange_rates';

    public static function create()
    {
        return new self();
    }
        
    public static function delete_old_records()
    {
        DbHelper::query('delete from shop_currency_exchange_rates where DATE_ADD(created_at, interval 90 day) < NOW()');
    }
}
