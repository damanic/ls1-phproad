<?php
namespace Shop;

use Db\DataFilterSwitcher;

class HideDisabledProductsSwitcher extends DataFilterSwitcher
{
    public function applyToModel($model, $enabled, $context = null)
    {
        if ($enabled) {
            $model->where(
                '((enabled is not null and enabled=1 and (disable_completely is null or disable_completely=0)))'
            );
        }
            
        return $model;
    }
}
