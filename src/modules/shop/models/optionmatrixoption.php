<?php
namespace Shop;

use Db\ActiveRecord;

class OptionMatrixOption extends ActiveRecord
{
    public $table_name = 'shop_option_matrix_options';
    
    public static function create()
    {
        return new self();
    }
}
