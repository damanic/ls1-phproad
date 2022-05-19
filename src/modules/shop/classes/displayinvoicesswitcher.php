<?php
namespace Shop;

use Db\DataFilterSwitcher;

class DisplayInvoicesSwitcher extends DataFilterSwitcher
{
    public $list_columns = array('name');

    public function applyToModel($model, $enabled, $context = null)
    {
        if (!$enabled) {
            $model->where('parent_order_id is null');
        }
            
        return $model;
    }
}
