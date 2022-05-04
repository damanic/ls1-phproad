<?php
namespace Phpr;

use DateTimeZone;

use Db\DataCollection;
use Phpr;
use Phpr\DateTime as PhprDateTime;
use Phpr\SystemException;
use Db\ActiveRecord;

/**
 * Represents a set of validation rules.
 * Objects of this class are usually created by
 * {@link Validation::add()}
 * and
 * {@link \Db\ColumnDefinition::validation()}
 *
 * Almost all methods of this class return the updated object.
 * It allows to define rules as a chain:
 *
 *  <pre>
 *      $this->define_column('name', 'Name')
 *      ->order('asc')
 *      ->validation()
 *      ->fn('trim')
 *      ->required("Please specify the theme name.");
 *  </pre>
 *
 * Rules are executed in the order they added.
 * Some rules, like fn() can update the input value, instead of performing the actual
 * validation. The updated value id then used in other rules.
 * If the validation object is used with {@link ActiveRecord models},
 * the updated field values are assigned to the model properties before it is saved to the database.
 */
class ValidationRules
{
    const ruleType = 'type';
    const objName = 'name';
    const typeFunction = 'function';
    const typeMethod = 'method';
    const typeInternal = 'internal';
    const params = 'params';
    const message = 'message';

    /**
     * @ignore
     * Contains a list of validation rules.
     * @var    array
     */
    public $rules;

    /**
     * @ignore
     * Contains a field name.
     * @var    string
     */
    public $fieldName;

    /**
     * @ignore
     * Determines whether the field is focusable.
     * @var    string
     */
    public $focusable;

    /**
     * @ignore
     * An element that should be focused in case of error
     * @var    string
     */
    public $focusId;

    public $required;

    protected $validation;

    /**
     * Creates a new ValidationRules instance. Do not instantiate this class directly -
     * the controller Validation property: $this->validation->addRule("FirstName").
     *
     * @param Validation $Validation Specifies the validation class instance.
     * @param bool            $Focusable  Specifies whether the field is focusable.
     * @param string          $FieldName  Specifies a field name.
     */
    public function __construct($Validation, $FieldName, $Focusable)
    {
        $this->rules = array();
        $this->validation = $Validation;
        $this->fieldName = $FieldName;
        $this->focusable = $Focusable;
    }

    /**
     * Adds a rule that processes a value using a PHP function.
     * The function must accept a single parameter - the value
     * and return a string or boolean value. The updated value
     * is used by all following validation rules. Example:
     * <pre>$this->define_column('author_name', 'Author')->validation()->fn('trim');</pre>
     *
     * @documentable
     * @param        string $name Specifies a PHP function name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function fn($Name)
    {
        $this->rules[] = array(self::ruleType => self::typeFunction, self::objName => $Name);
        return $this;
    }

    /**
     * Sets an identifier of a element that should be focused in case of error
     *
     * @param  string $Id Specifies an element identifier
     * @return ValidationRules
     */
    public function focusId($Id)
    {
        $this->focusId = $Id;
        return $this;
    }

    /**
     * Adds a rule that validates a value with an owner class' method.
     * Use this method with {@link ActiveRecord ActiveRecord} models. The model class should
     * contain a public method with the specified name. The should accept two parameters -
     * the field name and value, and return a string or boolean value. Alternatively you can use
     * {@link Validation::setError() setError()} method of the validation object to throw an exception.
     * <pre>
     * public function define_columns($context = null)
     * {
     *   $this->define_column('is_enabled', 'Enabled')->validation()->method('validate_enabled');
     *   ...
     * }
     *
     * public function validate_enabled($name, $value)
     * {
     *   if (!$value && $this->is_default)
     *     $this->validation->setError('This theme is default and cannot be disabled.', $name, true);
     *
     *   return $value;
     * }
     * </pre>
     *
     * @documentable
     * @param        string $name Specifies the method name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function method($Name)
    {
        $this->rules[] = array(self::ruleType => self::typeMethod, self::objName => $Name);
        return $this;
    }

    /**
     * @param  string $Rule    Specifies the rule name
     * @param  string $Name    Specifies a field name
     * @param  string $Value   Specifies a value to validate
     * @param  array  &$Params A list of the rule parameters.
     * @return mixed
     * @ignore
     * Evaluates the internal validation rule.
     * This method is used by the Validation class internally.
     */
    public function evalInternal($Rule, $Name, $Value, &$Params, $CustomMessage, &$DataSrc, $deferred_session_key)
    {
        $MethodName = "eval" . $Rule;
        if (!method_exists($this, $MethodName)) {
            throw new SystemException("Unknown validation rule: $Rule");
        }

        $Params['deferred_session_key'] = $deferred_session_key;

        return $this->$MethodName($Name, $Value, $Params, $CustomMessage, $DataSrc);
    }

    /**
     * Registers an internal validation rule.
     *
     * @param string $Method        Specifies the rule method name.
     * @param array  $Params        A list of the rule parameters.
     * @param string $CustomMessage Custom error message
     */
    protected function registerInternal($Method, $Params = array(), $CustomMessage = null)
    {
        if (($pos = strpos($Method, '::')) !== false) {
            $Method = substr($Method, $pos + 2);
        }

        $this->rules[] = array(
            self::ruleType => self::typeInternal,
            self::objName => $Method,
            self::params => $Params,
            self::message => $CustomMessage
        );
    }

    /*
     * ====================== Numeric rule ======================
     */

    /**
     * Checks whether the value is a valid number.
     * Correct numeric values: 10, -10.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function numeric($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is numeric.
     *
     * @param  string $Name  Specifies a field name
     * @param  $Value Specifies a value to validate.
     * @return boolean.
     */
    protected function evalNumeric($Name, $Value, &$Params, $CustomMessage)
    {
        if (!strlen($Value)) {
            return true;
        }

        $result = preg_match("/^\-?[0-9]+$/", $Value) ? true : false;

        $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
            Phpr::$lang->mod('phpr', 'numeric', 'validation'),
            $this->fieldName
        );
        if (!$result) {
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Float rule ======================
     */

    /**
     * Checks whether the value is a valid floating point number.
     * Correct numeric values: 10, 10.0, -10.0.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function float($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is a valid float number.
     *
     * @param  string $Name  Specifies a field name
     * @param  $Value Specifies a value to validate.
     * @return boolean.
     */
    protected function evalFloat($Name, $Value, &$Params, $CustomMessage)
    {
        if (!strlen($Value)) {
            return true;
        }

        // $result = Phpr::$lang->strToNum($Value);

        if (!preg_match('/^(\-?[0-9]*\.[0-9]+|\-?[0-9]+)$/', $Value)) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'float', 'validation'),
                $this->fieldName
            );

            $this->validation->setError($Message, $Name);
            return false;
        }

        $Value = trim($Value);
        if (strlen($Value)) {
            $first_char = substr($Value, 0, 1);
            if ($first_char == '.') {
                $Value = (float)('0' . $Value);
            } elseif ($first_char == '-') {
                if (substr($Value, 1, 1) == '.') {
                    $Value = (float)('-0' . substr($Value, 1));
                }
            }
        }

        return $Value;
    }

    /*
     * ====================== Min length rule ======================
     */

    /**
     * Checks whether a value is not shorter than the specified length.
     *
     * @documentable
     * @param        int    $length         Specifies the minimum value length.
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function minLength($Length, $CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array($Length), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is not shorter than a specified length.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the MinLength method.
     * @return boolean.
     */
    protected function evalMinLength($Name, $Value, &$Params, $CustomMessage)
    {
        $result = mb_strlen($Value) >= $Params[0] ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'minlen', 'validation'),
                $this->fieldName,
                $Params[0]
            );

            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Max length rule ======================
     */

    /**
     * Checks whether a value is not longer than the specified length.
     *
     * @documentable
     * @param        int    $length         Specifies the maximum value length.
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function maxLength($Length, $CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array($Length), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is not longer than a specified length.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the MaxLength method.
     * @return boolean.
     */
    protected function evalMaxLength($Name, $Value, &$Params, $CustomMessage)
    {
        $result = mb_strlen($Value) <= $Params[0] ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'maxlen', 'validation'),
                $this->fieldName,
                $Params[0]
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Length rule ======================
     */

    /**
     * Checks whether a value length matches the specified value.
     *
     * @documentable
     * @param        int    $length         Specifies the required value length.
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function length($Length, $CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array($Length), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value length matches a specified value.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Length method.
     * @return boolean.
     */
    protected function evalLength($Name, $Value, &$Params, $CustomMessage)
    {
        $result = mb_strlen($Value) == $Params[0] ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'length', 'validation'),
                $this->fieldName,
                $Params[0]
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Unique rule ======================
     */

    /**
     * Checks whether a value is unique.
     * This rule is applicable only when validation is used with a {@link ActiveRecord model}.
     * The rule creates a test object (an instance of the model class) to detect whether the value
     * is unique.
     *
     * By default, if the second parameter omitted, the rule checks whether the value is unique in the entire table.
     * The second parameter allows to define a callback method in the model for configuring
     * the test model object. The method should accept 3 parameters - the test object, the model
     * object and the deferred session key value. Example:
     * <pre>
     * public function define_columns($context = null)
     * {
     *   $this->define_column('file_name', 'File Name')->validation()
     *     ->unique('File name "%s" already used by another template.', array($this, 'configure_unique_validator'));
     *   ...
     * }
     *
     * public function configure_unique_validator($checker, $page, $deferred_session_key)
     * {
     *   // Exclude pages from other themes
     *   $checker->where('theme_id=?', $page->theme_id);
     * }
     * </pre>
     *
     * @documentable
     * @param        string   $custom_message          Specifies an error message to display if the validation fails.
     *                                                 Can contain <em>%s</em> placeholder which is replaced with the
     *                                                 actual field name.

     * @param  callback $checker_filter_callback Specifies the required value length.
     * @return ValidationRules Returns the updated rule set.
     */
    public function unique($CustomMessage = null, $CheckerFilterCallback = null)
    {
        $this->registerInternal(__METHOD__, array('filter_callback' => $CheckerFilterCallback), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value length matches a specified value.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Length method.
     * @return boolean.
     */
    protected function evalUnique($Name, $Value, &$Params, $CustomMessage, &$obj)
    {
        if (!($obj instanceof ActiveRecord) || !strlen($Value)) {
            return true;
        }

        $modelClassName = get_class($obj);

        $checker = new $modelClassName();
        $checker->where("$Name = ?", $Value);
        if (!$obj->is_new_record()) {
            $checker->where("{$obj->primary_key} <> ?", $obj->get_primary_key_value());
        }

        if ($Params['filter_callback']) {
            call_user_func($Params['filter_callback'], $checker, $obj, $Params['deferred_session_key']);
        }

        if ($checker->find()) {
            $Message = strlen($CustomMessage) ? sprintf($CustomMessage, $Value) : sprintf(
                Phpr::$lang->mod('phpr', 'unique', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
            return false;
        }

        return true;
    }

    /*
     * ====================== Required rule ======================
     */

    /**
     * Makes the field required.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function required($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        $this->required = true;
        return $this;
    }

    /**
     * Determines whether a value is not empty.
     *
     * @param  string $Name  Specifies a field name
     * @param  $Value Specifies a value to validate.
     * @return boolean.
     */
    protected function evalRequired($Name, $Value, &$Params, $CustomMessage)
    {
        if (!is_array($Value) && !($Value instanceof DataCollection)) {
            $result = trim($Value) != '' ? true : false;
        } elseif ($Value instanceof DataCollection) {
            $result = $Value->count() ? true : false;
        } else {
            $result = count($Value) ? true : false;
        }

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'required', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Optional rule ======================
     */

    /**
     * Makes the field optional.
     *
     * @documentable
     * @return       ValidationRules Returns the updated rule set.
     */
    public function optional()
    {
        $this->required = false;

        $required_index = null;
        foreach ($this->rules as $index => $rule) {
            if ($rule['name'] == 'required') {
                $required_index = $index;
                break;
            }
        }

        if ($required_index !== null) {
            unset($this->rules[$required_index]);
        }

        return $this;
    }

    /*
     * ====================== Alpha rule ======================
     */

    /**
     * Checks whether the value contains only Latin characters.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function alpha($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value contains only alphabetical characters.
     *
     * @param  string $Name  Specifies a field name
     * @param  $Value Specifies a value to validate.
     * @return boolean.
     */
    protected function evalAlpha($Name, $Value, &$Params, $CustomMessage)
    {
        $result = preg_match("/^([-a-z])+$/i", $Value) ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'alpha', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Alphanumeric rule ======================
     */

    /**
     * Checks whether the value contains only Latin characters and digits.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function alphanum($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value contains only alpha-numeric characters.
     *
     * @param  string $Name  Specifies a field name
     * @param  $Value Specifies a value to validate.
     * @return boolean.
     */
    protected function evalAlphanum($Name, $Value, &$Params, $CustomMessage)
    {
        $result = preg_match("/^([-a-z0-9])+$/i", $Value) ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'alphanum', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Email rule ======================
     */

    /**
     * Checks whether the value is a valid email address.
     *
     * @documentable
     * @param        boolean $allow_empty    Determines whether the value can be empty.
     * @param        string  $custom_message Specifies an error message to display if the validation fails.
     *                                       Can contain <em>%s</em> placeholder which is replaced with the
     *                                       actual field name.

     * @return ValidationRules Returns the updated rule set.
     */
    public function email($AllowEmpty = false, $CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array($AllowEmpty), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is a valid email address.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Regexp method.
     * @return boolean.
     */
    protected function evalEmail($Name, $Value, &$Params, $CustomMessage)
    {
        if (!strlen($Value) && $Params[0]) {
            return true;
        }

        $is_valid_email = filter_var($Value, FILTER_VALIDATE_EMAIL) !== false;

        if (!$is_valid_email) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'email', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
        }

        return $is_valid_email;
    }

    /*
     * ====================== Url rule ======================
     */

    /**
     * Checks whether the value is a valid URL.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function url($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is a valid email address.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Regexp method.
     * @return boolean.
     */
    protected function evalUrl($Name, $Value, &$Params, $CustomMessage)
    {
        if (!strlen($Value)) {
            return true;
        }

        $result = preg_match(
            "~^(http|https|ftp|ssh|sftp|etc)\://([a-zA-Z0-9\.\-]+(\:[a-zA-Z0-9\.&amp;%\$\-]+)*@)*((25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9])\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9]|0)\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9]|0)\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[0-9])|localhost|([a-zA-Z0-9\-]+\.)*[a-zA-Z0-9\-]+\.(com|edu|gov|int|mil|net|org|biz|arpa|info|name|pro|aero|coop|museum|[a-zA-Z]{2}))(\:[0-9]+)*(/($|[a-zA-Z0-9\.\,\?\'\\\+&amp;%\$#\=_\-]+))*$~",
            mb_strtolower($Value)
        ) ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'url', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== IP rule ======================
     */

    /**
     * Checks whether the value is a valid IP address.
     *
     * @documentable
     * @param        string $custom_message Specifies an error message to display if the validation fails.
     *                                      Can contain <em>%s</em> placeholder which is replaced with the actual field name.
     * @return       ValidationRules Returns the updated rule set.
     */
    public function ip($CustomMessage = null)
    {
        $this->registerInternal(__METHOD__, array(), $CustomMessage);
        return $this;
    }

    /**
     * Determines whether a value is a valid IP address.
     *
     * @param  string $Name  Specifies a field name
     * @param  $Value Specifies a value to validate.
     * @return boolean.
     */
    protected function evalIp($Name, $Value, &$Params, $CustomMessage)
    {
        $result = preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $Value) ? true : false;

        if (!$result) {
            $Message = strlen($CustomMessage) ? $CustomMessage : sprintf(
                Phpr::$lang->mod('phpr', 'ip', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($Message, $Name);
        }

        return $result;
    }

    /*
     * ====================== Matches rule ======================
     */

    /**
     * Adds a rule that determines whether a value matches another field value.
     *
     * @param  string $Field Specifies a name of field this field value must match
     * @return ValidationRules
     */
    public function matches($Field, $errorMessage = null)
    {
        $this->registerInternal(__METHOD__, array($Field, $errorMessage));
        return $this;
    }

    /**
     * Determines whether a value matches another field value.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Matches method.
     * @return boolean.
     */
    protected function evalMatches($Name, $Value, &$Params)
    {
        $fieldToMatch = $Params[0];
        $errorMessage = $Params[1];
        $validation = $this->validation;
        if (!$validation->hasRuleFor($fieldToMatch)) {
            throw new SystemException("Unknown validation field: $fieldToMatch");
        }

        $postedValue = Phpr::$request->postField($fieldToMatch);
        $valueToMatch = $validation->fieldValues[$fieldToMatch] ?? $postedValue;
        $result = $Value == $valueToMatch;

        if (!$result) {
            if (!strlen($errorMessage)) {
                $fieldToMatchName = $this->validation->getRule($fieldToMatch)->fieldName;
                $this->validation->setError(
                    sprintf(Phpr::$lang->mod('phpr', 'matches', 'validation'), $this->fieldName, $fieldToMatchName),
                    $Name
                );
            } else {
                $this->validation->setError($errorMessage, $Name);
            }
        }

        return $result;
    }

    /*
     * ====================== Regexp rule ======================
     */

    /**
     * Checks whether the value matches the specified regular expression.
     *
     * @documentable
     * @param        string  $pattern        Specifies a Perl-compatible regular expression pattern.
     * @param        string  $custom_message Specifies an error message to display if the validation fails.
     * @param        boolean $allow_empty    Determines whether the value can be empty.
     *                                       Can contain <em>%s</em> placeholder which
     *                                       is replaced with the actual field name.

     * @return ValidationRules Returns the updated rule set.
     */
    public function regexp($Pattern, $errorMessage = null, $AllowEmpty = false)
    {
        $this->registerInternal(__METHOD__, array($Pattern, $errorMessage, $AllowEmpty));
        return $this;
    }

    /**
     * Determines whether a value matches a specified regular expression pattern.
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Regexp method.
     * @return boolean.
     */
    protected function evalRegexp($Name, $Value, &$Params)
    {
        if (!strlen($Value) && $Params[2]) {
            return true;
        }

        $result = preg_match($Params[0], $Value) ? true : false;

        if (!$result) {
            $errorMessage = $Params[1] !== null ? $Params[1] : sprintf(
                Phpr::$lang->mod('phpr', 'regexp', 'validation'),
                $this->fieldName
            );
            $this->validation->setError($errorMessage, $Name);
        }

        return $result;
    }

    /*
     * ====================== DateTime rule ======================
     */

    /**
     * Adds a rule that determines whether a value represents a date/time value, according the specified format.
     * Some formats (like %x and %X) depends on the current user language date format.
     * This rule sets the field value to a valid SQL date format converted to GMT.
     *
     * @param string $Format       Specifies an expected format.
     *                             By default the short date
     *                             format (%x) used (11/6/2006 -
     *                             for en_US).

     * @param  string $errorMessage Optional error message.
     * @return ValidationRules
     */
    public function dateTime($Format = "%x %X", $errorMessage = null, $dateAsIs = false)
    {
        $this->registerInternal(__METHOD__, array($Format, $errorMessage, $dateAsIs));
        return $this;
    }

    /**
     * Determines whether a value is a valid data and time string
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Regexp method.
     * @return boolean.
     */
    protected function evalDateTime($Name, $Value, &$Params)
    {
        if (is_object($Value)) {
            return true;
        }

        if (!strlen($Value)) {
            return null;
        }

        $timeZone = Phpr::$config->get('TIMEZONE');
        try {
            $timeZoneObj = new DateTimeZone($timeZone);
        } catch (\Exception $Ex) {
            throw new SystemException(
                'Invalid time zone specified in config.php: ' . $timeZone . '. Please refer this document for the list of correct time zones: http://docs.php.net/timezones.'
            );
        }

        $result = PhprDateTime::parse($Value, $Params[0], $timeZoneObj);

        if (!$result) {
            $errorMessage = $Params[1] !== null ? $Params[1] :
                sprintf(
                    Phpr::$lang->mod('phpr', 'datetime', 'validation'),
                    $this->fieldName,
                    PhprDateTime::now()->format($Params[0])
                );

            $this->validation->setError($errorMessage, $Name);
        } else {
            if (!$Params[2]) {
                $timeZoneObj = new DateTimeZone('GMT');
                $result->setTimeZone($timeZoneObj);
                unset($timeZoneObj);
            }

            $result = $result->toSqlDateTime();
        }

        return $result;
    }

    /**
     * Adds a rule that determines whether a value represents a date/time value, according the specified format.
     * Some formats (like %x and %X) depends on the current user language date format.
     * This rule sets the field value to a valid SQL date format.
     *
     * @param string $Format       Specifies an expected format.
     *                             By default the short date
     *                             format (%x) used (11/6/2006 -
     *                             for en_US).

     * @param  string $errorMessage Optional error message.
     * @return ValidationRules
     */
    public function date($Format = "%x", $errorMessage = null)
    {
        $this->registerInternal(__METHOD__, array($Format, $errorMessage));
        return $this;
    }

    /**
     * Determines whether a value is a valid data and time string
     *
     * @param  string $Name    Specifies a field name
     * @param  $Value   Specifies a value to validate.
     * @param  array  &$Params A list of parameters passed to the Regexp method.
     * @return string.
     */
    protected function evalDate($Name, $Value, &$Params)
    {
        if (is_object($Value)) {
            return true;
        }

        if (!strlen($Value)) {
            return null;
        }

        $result = PhprDateTime::parse($Value, $Params[0]);

        if (!$result) {
            $errorMessage = $Params[1] !== null ? $Params[1] :
                sprintf(
                    Phpr::$lang->mod('phpr', 'datetime', 'validation'),
                    $this->fieldName,
                    PhprDateTime::now()->format($Params[0])
                );

            $this->validation->setError($errorMessage, $Name);
        } else {
            $result = $result->toSqlDate();
        }

        return $result;
    }


    /**
     * Cleans HTML preventing XSS code.
     * @param string $value Specifies a controller method name.
     * @return ValidationRules
     */
    public function cleanHtml()
    {
        $this->registerInternal(__METHOD__, array());
        return $this;
    }

    protected function evalCleanHtml($name, $value, &$params)
    {
        return Html::cleanXss($value);
    }
}
