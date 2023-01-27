<?php
namespace System;

use Db\ActiveRecord;
use Core\ModuleManager;
use Core\Twig;

class CompoundEmailVar extends ActiveRecord
{
    public $table_name = 'system_compound_email_vars';
        
    protected static $scope_variables = null;

    public static function create($values = null)
    {
        return new self($values);
    }

    public function define_columns($context = null)
    {
        $this->define_column('code', 'Variable Name')
            ->validation()
            ->fn('trim')
            ->fn('mb_strtolower')
            ->required('Please specify the variable name.')
            ->unique('Name "%s" is already in use.')
            ->regexp(
                '/^[0-9a-z_-]*$/i',
                'Variable name can contain only latin characters, numbers, underscores and the dash sign'
            );

        $this->define_column('description', 'Description')
            ->validation()
            ->fn('trim')
            ->required('Please specify the variable description.');

        $this->define_column('scope', 'Scope')
            ->validation()
            ->fn('trim')
            ->required('Please select a variable scope.');

        $this->define_column('content', 'Code')
            ->invisible()
            ->validation()
            ->fn('trim');
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('code', 'left');
        $this->add_form_field('description', 'right');
        $this->add_form_field('scope')->renderAs(frm_dropdown);
        $this->add_form_field('content')
            ->size('large')
            ->cssClasses('code')
            ->renderAs(frm_code_editor)
            ->comment('Please provide the PHP/HTML code displaying the variable content.', 'above');
    }
        
    public function get_scope_options($key_index = -1)
    {
        return ModuleManager::listEmailScopes();
    }
        
    public static function list_scope_variables($scope)
    {
        if (self::$scope_variables === null) {
            $vars = self::create()->order('code')->find_all();
            self::$scope_variables = array();
                
            foreach ($vars as $var) {
                if (!array_key_exists($var->scope, self::$scope_variables)) {
                    self::$scope_variables[$var->scope] = array();
                }
                        
                self::$scope_variables[$var->scope][] = $var;
            }
        }

        if (array_key_exists($scope, self::$scope_variables)) {
            return self::$scope_variables[$scope];
        }

        return array();
    }
        
    public static function apply_scope_variables($message, $scope, $parameters = array())
    {
        $vars = self::list_scope_variables($scope);
        $engine = EmailParams::get_templating_engine();
        foreach ($vars as $var) {
            $var_value = '';
            if ($engine == 'php') {
                ob_start();
                try {
                    eval('?>'.$var->content);
                    $var_value =  ob_get_clean();
                } catch (\ParseError | \Error | \Exception $p) {
                    $var_value = 'ERROR APPLYING COMPOUND VARIABLE ('.$var->code.'): '.h($p->getMessage());
                }
            } else {
                try {
                    $var_value = Twig::get()->parse($var->content, $parameters, 'Email variable "'.$var->code.'"');
                } catch (\Exception $ex) {
                        $var_value = 'ERROR APPLYING COMPOUND VARIABLE ('.$var->code.'): '.h($ex->getMessage());
                }
            }
            if ($engine == 'php') {
                @ob_end_clean();
            }
            $message = str_replace('{'.$var->code.'}', $var_value, $message);
        }
            
        return $message;
    }
}
