<?php

namespace Shop;

use Db\ActiveRecord;

class OrderDeletedStatus extends ActiveRecord
{
    public $table_name = 'shop_order_deleted_status';
    protected static $cache = null;

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
        $this->define_column('name', 'Status')->order('asc');
    }

    public static function code_by_id($id)
    {
        if (!self::$cache) {
            self::$cache = self::create()->find_all()->as_array('code', 'id');
        }

        return self::$cache[$id] ?? null;
    }
}
