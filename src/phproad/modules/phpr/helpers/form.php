<?php
namespace Phpr;

use Phpr;
use Phpr\ApplicationException;

/**
 * PHPR Form helper
 *
 * This class contains functions for working with HTML forms.
 */
class Form
{
    /**
     * Returns the opening form tag.
     *
     * @param  array $attributes Optional list of the opening tag attributes.
     * @return string
     */
    public static function openTag($attributes = array())
    {
        $DefUrl = h(rawurldecode(strip_tags(root_url(Phpr::$request->GetCurrentUri()))));

        if (($pos = mb_strpos($DefUrl, '|')) !== false) {
            $DefUrl = mb_substr($DefUrl, 0, $pos);
        }

        $result = "<form ";
        $result .= Html::formatAttributes(
            $attributes,
            array("action" => $DefUrl, "method" => "post", "id" => "FormElement", "onsubmit" => "return false;")
        );
        $result .= ">\n";

        return $result;
    }

    /**
     * Returns the closing form tag.
     *
     * @return string
     */
    public static function closeTag()
    {
        $result = "</form>";
        return $result;
    }

    /**
     * Returns the checked="checked" string if the $Value is true.
     * Use this helper to set a checkbox state.
     *
     * @param  boolean $Value Specifies the checbox state value
     * @return string
     */
    public static function checkboxState($Value)
    {
        return $Value ? "checked=\"checked\"" : "";
    }

    /**
     * Returns the checked="checked" string if the $Value1 equals $Value2
     * Use this helper to set a radiobutton state.
     *
     * @param  boolean $Value1 Specifies the first value
     * @param  boolean $Value2 Specifies the second value
     * @return string
     */
    public static function radioState($Value1, $Value2)
    {
        return $Value1 == $Value2 ? "checked=\"checked\"" : "";
    }

    /**
     * Returns the selected="selected" string if the $SelectedState = $CurrentState
     * Use this helper to set a select option state.
     *
     * @param  boolean $SelectedState Specifies the select value that is currently selected
     * @param  boolean $CurrentState  Specifies the current option value
     * @return string
     */
    public static function optionState($SelectedState, $CurrentState)
    {
        return $SelectedState == $CurrentState ? 'selected="selected"' : null;
    }

    public static function multiOptionState($items, $name, $value)
    {
        foreach ($items as $item) {
            if ($item->$name == $value) {
                return 'selected="selected"';
            }
        }

        if (is_array($items) && in_array($value, $items)) {
            return 'selected="selected"';
        }

        return null;
    }


    /**
     * Form widget
     */

    public static function widget($field_name, $options = array())
    {
        if (!isset($options['class'])) {
            throw new ApplicationException(
                "Missing widget class from Phpr\Form ::widget(), please define 'class' in options array as the second parameter"
            );
        }

        $class = $options['class'];
        $model = isset($options['model']) ? $options['model']: null;

        // All widgets need a model
        //
        
        if (is_string($model)) {
            $model = new $model();
        }

        if (!$model) {
            $model = new User();
        } // Gotta use something!

        // All widgets need a controller
        $controller = new Controller();

        // Create widget
        $widget = new $class($controller, $model, $field_name, $options);

        // Capture printed output
        //

        ob_start();
        $widget->render();
        $result = ob_get_contents();
        ob_end_clean();

        return $result;
    }

    /**
     * Form text input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formInput($name = '', $value = '', $extra = '')
    {
        return self::makeFormInput('text', $name, $value, $extra);
    }

    /**
     * Form hidden input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formHidden($name = '', $value = '', $extra = '')
    {
        return self::makeFormInput('hidden', $name, $value, $extra);
    }

    /**
     * Form file input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formFile($name = '', $value = '', $extra = '')
    {
        return self::makeFormInput('file', $name, $value, $extra);
    }

    /**
     * Form password input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formPassword($name = '', $value = '', $extra = '')
    {
        return self::makeFormInput('password', $name, $value, $extra);
    }

    /**
     * Form checkbox input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param bool $checked Is checkbox checked?
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formCheckbox($name = '', $value = '', $checked = false, $extra = '')
    {
        if ($value === false) {
            $value = 0;
        }

        $attributes = array();

        if ($checked) {
            $attributes['checked'] = 'checked';
        }

        return self::makeFormInput('checkbox', $name, $value, $extra, $attributes);
    }

    /**
     * Form radio input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param bool $checked Is radio selected
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formRadio($name = '', $value = '', $checked = false, $extra = '')
    {
        if ($value === false) {
            $value = 0;
        }

        $attributes = array();

        if ($checked) {
            $attributes['checked'] = 'checked';
        }

        return self::makeFormInput('radio', $name, $value, $extra, $attributes);
    }

    /**
     * Form submit button
     *
     * @param string $name Button name
     * @param string $value Button text
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formSubmit($name = '', $value = '', $extra = '')
    {
        return self::makeFormInput('submit', $name, $value, $extra);
    }

    /**
     * Form reset button
     *
     * @param string $name Button name
     * @param string $value Button text
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formReset($name = '', $value = '', $extra = '')
    {
        return self::makeFormInput('reset', $name, $value, $extra);
    }


    /**
     * Form textarea
     *
     * @param mixed $data Textarea name (string) or data (array) to define name, cols and rows.
     * @param string $value Textarea value
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formTextarea($data = '', $value = '', $extra = '')
    {
        $attributes = array('name' => ((!is_array($data)) ? $data : ''), 'cols' => '35', 'rows' => '12');

        if (is_array($extra)) {
            $attributes = array_merge($extra, $attributes);
            $extra = '';
        }

        return "<textarea " . Html::formatAttributes($attributes) . " " . $extra . ">" . $value . "</textarea>";
    }

    /**
     * Form dropdown select input
     *
     * @param string $name Select input name
     * @param Array $options Dropdown options
     * @param Array $selected Dropdown selected options
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formDropdown(
        $name = '',
        $options = array(),
        $selected = array(),
        $extra = '',
        $empty_option = false
    ) {
        if (!is_array($selected)) {
            $selected = array($selected);
        }

        if (count($selected) === 0 && isset($_POST[$name])) {
            $selected = array($_POST[$name]);
        }

        if (is_array($extra)) {
            $extra = Html::formatAttributes($extra);
        } else {
            if ($extra != '') {
                $extra = ' ' . $extra;
            }
        }

        $multiple = (count($selected) > 1 && strpos($extra, 'multiple') === false) ? ' multiple="multiple"' : '';
        $return = '<select name="' . $name . '"' . $extra . $multiple . ">" . PHP_EOL;

        if ($empty_option !== false) {
            $return .= '<option value="">' . h($empty_option) . '</option>' . PHP_EOL;
        }

        foreach ($options as $key => $value) {
            $key = (string)$key;
            if (is_array($value)) {
                $return .= '<optgroup label="' . $key . '">' . PHP_EOL;
                foreach ($value as $optgroup_key => $optgroup_val) {
                    $selected_string = (in_array($optgroup_key, $selected)) ? ' selected="selected"' : '';
                    $return .= '<option value="' . $optgroup_key . '"' . $selected_string . '>' . $optgroup_val . "</option>" . PHP_EOL;
                }
                $return .= '</optgroup>' . PHP_EOL;
            } else {
                $selected_string = (in_array($key, $selected)) ? ' selected="selected"' : '';
                $return .= '<option value="' . $key . '"' . $selected_string . '>' . $value . "</option>" . PHP_EOL;
            }
        }
        $return .= '</select>';
        return $return;
    }

    /**
     * Form button
     *
     * @param string $name Button name
     * @param string $text Button text
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formButton($name = '', $text = '', $extra = '')
    {
        $attributes = array('name' => $name, 'type' => 'button');

        if (is_array($extra)) {
            $attributes = array_merge($extra, $attributes);
            $extra = '';
        }

        return "<button " . Html::formatAttributes($attributes) . " " . $extra . ">" . $text . "</button>";
    }

    /**
     * Form label
     *
     * @param string $text Text value for the label
     * @param string $id Assosiated input ID
     * @param string $extra Extra attributes to include
     * @return string
     */
    public static function formLabel($text = '', $id = '', $extra = '')
    {
        $for = ($id != '') ? ' for="' . $id . '" ' : '';

        if (is_array($extra)) {
            $extra = Html::formatAttributes($extra);
        } else {
            if ($extra != '') {
                $extra = ' ' . $extra;
            }
        }

        return '<label' . $for . $extra . '>' . $text . '</label>';
    }

    /**
     * Returns a form field compatible class name for given model object
     * Converts namespaces to underscore
     * @param $object
     * @return array|string|string[]
     */
    public static function formModelClass($object)
    {
        return str_replace('\\', '_', get_class($object));
    }




    /**
     * Generic input
     *
     * @param string $type Input type
     * @param string $name Input name
     * @param string $value Input value
     * @param string $extra Extra attributes to include
     * @return string
     */
    private static function makeFormInput($type = 'text', $name = '', $value = '', $extra = '', $attributes = array())
    {
        if (!is_array($attributes)) {
            $attributes = array();
        }

        $attributes['name'] = $name;
        $attributes['type'] = $type;
        $attributes['value'] = $value;

        if (is_array($extra)) {
            $attributes = array_merge($extra, $attributes);
            $extra = '';
        }
        return "<input " . Html::formatAttributes($attributes) . " " . $extra . " />";
    }
    
    /**
     * @deprecated
     */
    public static function open_tag($attributes = array())
    {
        Phpr::$deprecate->setFunction('open_tag', 'openTag');
        return self::openTag($attributes);
    }

    /**
     * @deprecated
     */
    public static function close_tag()
    {
        Phpr::$deprecate->setFunction('close_tag', 'closeTag');
        return self::closeTag();
    }
}
