<?php
namespace Shop;

use Db\DataFilter;

class DeletedFilter extends DataFilter
{
    public $model_class_name = 'Shop\OrderDeletedStatus';
    public $list_columns = array('name');
        
    public function prepareListData()
    {
        $className = $this->model_class_name;
        return new $className();
    }

    public function applyToModel($model, $keys, $context = null)
    {
        $codes = array();
        foreach ($keys as $id) {
            $code = OrderDeletedStatus::code_by_id($id);
            if ($code) {
                $codes[] = $code;
            }
        }

        if (in_array('active', $codes)) {
            $model->where('deleted_at is null');
        } elseif (in_array('deleted', $codes)) {
            $model->where('deleted_at is not null');
        }
    }
}
