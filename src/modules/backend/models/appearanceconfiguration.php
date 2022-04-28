<?php
namespace Backend;

use Core\ConfigurationRecord;

class AppearanceConfiguration extends ConfigurationRecord
{
    public $record_code = 'appearance_configuration';
    public $is_personal = true;
        
    public static function create()
    {
        $configObj = new AppearanceConfiguration();
        return $configObj->load();
    }
        
    protected function build_form()
    {
        $this->add_field('menu_style', 'Main menu style', 'full', db_varchar)
            ->renderAs(frm_dropdown)
            ->tab('Main menu')
            ->comment('Note that on mobile devices the menu is always cascading', 'above');
    }

    public function get_menu_style_options()
    {
        return array(
            'single-level' => 'Plain menu',
            'two-level' => 'Cascading menu'
        );
    }

    protected function init_config_data()
    {
        $this->menu_style = 'single-level';
    }
}
