<?php
namespace Phpr;

use Phpr\Deprecate;
use Phpr\SystemException;
use Phpr\ValidationException;
use Db\ActiveRecord;
use Db\DataCollection;

/**
 * PHPR Validation Class
 *
 * Assists in validating form data.
 */
class Validation
{
    private $owner;
    private $formId;
    private $widgetData = array();

    /**
     * Contains a list of fields validation rules
     * @var    array
     */
    protected $fields;

    /**
     * Indicates whether all validation rules are valid.
     * A value of this field is set by the Validate method.
     *
     * @var boolean
     */
    public $valid;

    /**
     * Contains a list of invalid field names.
     *
     * @var array
     */
    public $errorFields;

    /**
     * Contains a list of fields error messages.
     */
    public $fieldErrors;

    /**
     * Keeps a common error message.
     *
     * @var string
     */
    public $errorMessage;

    /**
     * @ignore
     * Contains an evaluated field values.
     * @var    array
     */
    public $fieldValues;

    /**
     * Specifies a prefix to add to field identifiers in focusField method call
     */
    public $focusPrefix = null;

    /**
     * Creates a validation object.
     *
     * @documentable
     * @param        object $owner   Specifies an optional owner model.
     * @param        string           $form_id Specifies an optional HTML form identifier.
     */
    public function __construct($Owner = null, $FormId = 'FormElement')
    {
        $this->owner = $Owner;
        $this->formId = $FormId;
        $this->fields = array();
        $this->errorFields = array();
        $this->valid = false;
        $this->errorMessage = null;
        $this->fieldErrors = array();
        $this->fieldValues = array();
    }

    /**
     * Sets a form element identifier
     */
    public function setFormId($FormId)
    {
        $this->formId = $FormId;
    }

    /**
     * Adds a field validation rule set.
     * Add the rule set object to add validation rules.
     *
     * @documentable
     * @param        string  $column_name Specifies the field name.
     * @param        string  $label       Specifies the visual field label.
     * @param        boolean $focusable   Determines whether the field is focusable.
     * @return       Phpr\ValidationRules Returns the validation rule set object.
     */
    public function add($Field, $FieldName = null, $Focusable = true)
    {
        if ($FieldName === null) {
            $FieldName = $Field;
        }

        return $this->fields[$Field] = new ValidationRules($this, $FieldName, $Focusable);
    }

    /**
     * Sets a general or field-specific error message.
     *
     * @documentable
     * @param        string  $message Specifies the error message text.
     * @param        string  $field   Specifies the field name. If this parameter is omitted, the general message will be set.
     * @param        boolean $throw   Indicates whether the validation error should be thrown.
     * @return       Phpr\Validation Returns the updated validation object.
     */
    public function setError($Message, $Field = null, $Throw = false)
    {
        $this->valid = false;

        if ($Field !== null) {
            $this->fieldErrors[$Field] = $Message;
            $this->errorFields[] = $Field;
        } else {
            $this->errorMessage = $Message;
        }

        if ($Throw) {
            $this->throwException();
        }

        return $this;
    }

    /**
     * Detects whether a field with the specified name has any errors assigned.
     *
     * @documentable
     * @param        string $field Specifies the field name.
     * @return       boolean Returns TRUE if the field has errors. Returns FALSE otherwise.
     */
    public function isError($Field)
    {
        return in_array($Field, $this->errorFields);
    }

    /**
     * Returns an error message for a specified field.
     *
     * @documentable
     * @param        string  $field Specifies the field name.
     * @param        boolean $Html  Indicates whether the message must be prepared to HTML output.
     * @return       string Returns the error text or NULL.
     */
    public function getError($Field, $Html = true)
    {
        if (!isset($this->fieldErrors[$Field])) {
            return null;
        }

        $Message = $this->fieldErrors[$Field];
        return $Html ? Html::encode($Message) : $Message;
    }

    /**
     * Returns name of the first field with error.
     *
     * @documentable
     * @return       string Returns the field name or null.
     */
    public function firstErrorField()
    {
        if (isset($this->errorFields[0])) {
            return $this->errorFields[0];
        }

        return null;
    }

    /**
     * Runs the validation rules.
     *
     * @documentable
     * @param        mixed  $data                 Specifies a data source - an array or object.
     *                                            If this parameter is omitted, the data from
     *                                            the POST array will be used.

     * @param  string $deferred_session_key An edit session key for deferred bindings.
     * @return boolean Returns TRUE if the validation passed.
     */
    public function validate($Data = null, $deferred_session_key = null)
    {
        $ErrorFound = false;

        if ($Data === null) {
            $SrcArr = $_POST;
        } elseif (is_object($Data)) {
            $SrcArr = (array)$Data;
        } elseif (is_array($Data)) {
            $SrcArr = $Data;
        } else {
            throw SystemException("Invalid validation data object");
        }

        foreach ($this->fields as $ParamName => $RuleSet) {
            if (!is_object($Data)) {
                $FieldValue = isset($SrcArr[$ParamName]) ? $SrcArr[$ParamName] : null;
            } else {
                if (!($Data instanceof Db_ActiveRecord)) {
                    $FieldValue = $Data->$ParamName;
                } else {
                    $FieldValue = $Data->getDeferredValue($ParamName, $deferred_session_key);
                }
            }

            if ($FieldValue instanceof DataCollection) {
                $FieldValue = $FieldValue->as_array('id');
            }

            foreach ($RuleSet->rules as $Rule) {
                $RuleObj = $Rule[ValidationRules::objName];

                switch ($Rule[ValidationRules::ruleType]) {
                    case ValidationRules::typeInternal:
                        $RuleResult = $RuleSet->evalInternal(
                            $RuleObj,
                            $ParamName,
                            $FieldValue,
                            $Rule[ValidationRules::params],
                            $Rule[ValidationRules::message],
                            $Data,
                            $deferred_session_key
                        );
                        break;

                    case ValidationRules::typeFunction:
                        if (!function_exists($RuleObj)) {
                            throw new SystemException("Unknown validation function: $RuleObj");
                        }

                        $RuleResult = $RuleObj($FieldValue);
                        break;

                    case ValidationRules::typeMethod:
                        if ($this->owner === null) {
                            throw new SystemException(
                                "Can not execute the method-type rule $RuleObj without an owner object"
                            );
                        }

                        if (is_string($RuleObj)) {
                            $RuleResult = $this->owner->_execValidation($RuleObj, $ParamName, $FieldValue);
                        } elseif (is_callable($RuleObj)) {
                            $RuleResult = call_user_func($RuleObj, $ParamName, $FieldValue, $this, $this->owner);
                        }
                        break;
                }

                if ($RuleResult === false) {
                    $this->errorFields[] = $ParamName;
                    $ErrorFound = true;
                    continue 2;
                }

                if ($RuleResult === true) {
                    continue;
                }

                $FieldValue = $RuleResult;
            }

            $this->fieldValues[$ParamName] = $FieldValue;
        }

        $this->valid = !$ErrorFound;

        if ($this->valid) {
            foreach ($this->fieldValues as $fieldName => $fieldValue) {
                if ($Data === null) {
                    $_POST[$fieldName] = $fieldValue;
                } elseif (is_object($Data)) {
                    if (!($Data instanceof Db_ActiveRecord)) {
                        $Data->$fieldName = $fieldValue;
                    } else {
                        $Data->setDeferredValue($fieldName, $fieldValue, $deferred_session_key);
                    }
                }
            }
        }

        return $this->valid;
    }

    /**
     * Sets focus to a first error field.
     * If there are no error fields, sets focus to a first form field.
     * You may also specify explicitly with the optional parameter.
     *
     * @param string  $FieldId Optional identifier of a field to focus.
     * @param boolean $Force   Optional. Determines whether the field specified
     *                         in the first parameter must be focused even in
     *                         case of errors.
     */
    public function focus($FieldId = null, $Force = false)
    {
        $hasErrors = count($this->errorFields);

        $FormId = $this->formId === null ? 'document.forms[0]' : $this->formId;

        if ($FieldId !== null && (!$hasErrors || ($hasErrors && $Force))) {
            return "$('{$FormId}').focusField('$FieldId');";
        }

        if ($hasErrors) {
            $Field = $this->errorFields[0];
            if (isset($this->fields[$Field]) && !$this->fields[$Field]->focusable) {
                return null;
            }

            return "$('{$FormId}').focusField('{$this->errorFields[0]}');";
        }

        return "$('{$FormId}').focusFirst();";
    }

    /**
     * Sets a field name to focus for the first field having an error.
     *
     * @documentable
     * @param        string $name Specifies the field name.
     *                            If the value has the '%s' specifier it will be replaced with the field name.
     */
    public function setFirstFocusName($name)
    {
        $error_field = $this->firstErrorField();
        if (!$error_field) {
            return;
        }

        $rule = $this->getRule($error_field);
        if ($rule) {
            if (strpos($name, '%') !== false) {
                $name = sprintf($name, $error_field);
            }

            $rule->focusId($name);
        }
    }

    /**
     * Generates a Java Script code for focusing an error field
     *
     * @param  boolean $AddScriptNode Indicates whether the script node must be generated
     * @return string
     */
    public function getFocusErrorScript($AddScriptNode = true)
    {
        if (!count($this->errorFields)) {
            return null;
        }

        $Field = $this->errorFields[0];
        if (!isset($this->fields[$Field]) || !$this->fields[$Field]->focusable) {
            return null;
        }

        $result = null;
        if ($AddScriptNode) {
            $result .= "<script type='text/javascript'>";
        }

        $FormId = $this->formId === null ? 'document.forms[0]' : $this->formId;
        $FocusId = strlen($this->fields[$Field]->focusId) ? $this->fields[$Field]->focusId : $Field;

        if ($this->focusPrefix) {
            $FocusId = $this->focusPrefix . $FocusId;
        }

        $result .= "$(document.body).focusField('{$FocusId}');";
        $result .= "window.phprErrorField = '$FocusId';";
        if ($widgetData = $this->getWidgetData()) {
            $result .= 'phpr_dispatch_widget_response_data(' . json_encode($widgetData) . ');';
        }

        if ($AddScriptNode) {
            $result .= "</script>";
        }

        return $result;
    }

    public function setWidgetData($data)
    {
        $this->widgetData[] = $data;
    }

    public function getWidgetData()
    {
        return $this->widgetData;
    }

    /**
     * Throws the Validation Exception in case if data is not valid.
     *
     * @documentable
     */
    public function throwException()
    {
        throw new ValidationException($this);
    }

    /**
     * Check if a Phpr\ValidationRules exists for given field name
     * @param string $field Field name
     * @return bool
     */
    public function hasRuleFor(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * Get validation rule for given field name
     * @param string $field Field name
     * @return ValidationRules|null
     */
    public function getRule(string $field)
    {
        if ($this->hasRuleFor($field)) {
            return $this->fields[$field];
        }
        return null;
    }

    /**
     * Remove validation rule for given field name
     * @param string $field Field name
     * @return void
     */
    public function removeRule(string $field) : void
    {
        if ($this->hasRuleFor($field)) {
            unset($this->fields[$field]);
        }
    }


    /**
     * Remove all validation rules
     */
    public function removeRules() : void
    {
        foreach ($this->getFields() as $field) {
            $this->removeRule($field);
        }
    }

    /**
     * Get all validation rules for given field name
     * @return array Array of Phpr\ValidationRules
     */
    public function getFields() : array
    {
        return $this->fields;
    }

    /*
     * DEPRECATED properties
     */
    public function __get($name)
    {
        if ($name === '_fields') {
            $deprecate = new Deprecate();
            $deprecate->setClassProperty('_fields', 'fields');
            return $this->getFields();
        }
    }
}
