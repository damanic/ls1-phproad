<?php

class Db_GridDateEditor extends Db_GridEditor
{
    public function format_row_content($row_index, $column_info, $field_name, $row_data, $session_key)
    {
        return null;
    }

    public function render_popup_contents($column_info, $controller, $field_name)
    {
        try {
            $grid_data = post_array_item(post('widget_model_class'), 'grid_data', array());
            $data_index = post('phpr_grid_row_index');

            $selected_date = null;
            if (isset($grid_data[$data_index])) {
                $selected_date = $grid_data[$data_index][post('phpr_popup_column')];
            }

            $controller->renderPartial(
                PATH_APP . '/phproad/modules/db/partials/_date_editor_content.htm', array(
                'form_model' => $this->model,
                'row_index' => post('phpr_grid_row_index'),
                'date_format' => str_replace('%', null, Phpr::$lang->mod('phpr', 'short_date_format', 'dates')),
                'week' => Phpr::$lang->mod('phpr', 'week_abbr', 'dates'),
                'days' => Phpr\Html::getLocaleDatesArray('A_weekday_', 7),
                'days_short' => Phpr\Html::getLocaleDatesArray('a_weekday_', 7, 7),
                'months' => Phpr\Html::getLocaleDatesArray('n_month_', 12),
                'month_short' => Phpr\Html::getLocaleDatesArray('b_month_', 12),
                'selected_date' => $selected_date
                )
            );
        } catch (\Exception $ex) {
            Phpr::$response->ajaxReportException($ex, true, true);
        }
    }
}
