<?php 

namespace Db;

use Phpr;
use Phpr\Inflector;
use Phpr\DateTime as PhprDateTime;
use Phpr\Date;
use Phpr\Time;
use Phpr\Html;
use Phpr\SystemException;

/**
 * Represents a model column definition.
 * Objects of this class are used for defining presentation and validation field properties in models.
 * {@link \Db\ListBehavior List Behavior} and {@link \Db\FormBehavior Form Behavior} use data from the
 * column definition objects to output correct field labels and format field data.
 *
 * @documentable
 * @author       LSAPP
 * @package      core.classes
 */
class ColumnDefinition
{
    /**
     * @var          string Specifies the database column name.
     * @documentable
     */

    public $dbName;

    /**
     * @var          string Specifies the visual column name. This value is used
     * in list column titles and form labels.
     * @documentable
     */
    public $displayName;
    public $defaultOrder = null;

    /**
     * @var          string Specifies the column type.
     * @documentable
     */
    public $type;
    public $isCalculated;
    public $isCustom;
    public $isReference;
    public $referenceType = null;
    public $referenceValueExpr;
    public $relationName;
    public $referenceForeignKey;
    public $referenceClassName;
    public $visible = true;
    public $defaultVisible = true;
    public $listTitle = null;
    public $listNoTitle = false;
    public $noLog = false;
    public $log = false;
    public $dateAsIs = false;
    public $currency = false;
    public $noSorting = false;

    private $model;
    private $columnInfo;
    private $calculatedColumnName;
    private $validationObj = null;

    private static $relationJoins = array();
    private static $cachedModels = array();
    private static $cachedClassInstances = array();

    public $index;

    /**
     * Date/time display format
     *
     * @var string
     */
    private $dateFormat = '%x';
    private $dateTimeFormat = '%x %X';
    private $timeFormat = '%X';

    /**
     * Floating point numbers display precision.
     *
     * @var int
     */
    private $precision = 2;

    /**
     * Text display length
     */
    private $length = null;

    public function __construct(
        $model,
        $dbName,
        $displayName,
        $type = null,
        $relationName = null,
        $valueExpression = null
    ) {
        // traceLog('Column definition for '.get_class($model).':'.$dbName.' #'.$model->id);
        $this->dbName = $dbName;
        $this->displayName = $displayName;
        $this->model = $model;
        $this->isReference = strlen($relationName);
        $this->relationName = $relationName;

        if (!$this->isReference) {
            $this->columnInfo = $this->model->column($dbName);
            if ($this->columnInfo) {
                $this->type = $this->columnInfo->type;
            }

            if ($this->columnInfo) {
                $this->isCalculated = $this->columnInfo->calculated;
                $this->isCustom = $this->columnInfo->custom;
            }
        } else {
            $this->type = $type;

            if (strlen($valueExpression)) {
                $this->referenceValueExpr = $valueExpression;
                $this->defineReferenceColumn();
            }
        }

        if ($this->type == db_date || $this->type == db_datetime) {
            $this->validation();
        }
    }

    public function extendModel($model)
    {
        $this->setContext($model);

        if ($this->isReference && strlen($this->referenceValueExpr)) {
            $this->defineReferenceColumn();
        }

        return $this;
    }

    /*
     *
     * Common column properties
     *
     */

    /**
     * Specifies the column type.
     * By default column types match the database column types, but you can use
     * this method to override the database column type and thus change the field
     * display parameters. For example, if you set the field type to db_number
     * for a varchar field, its value will be aligned to the right in {@link \Db\ListBehavior lists} and {@link \Db\FormBehavior forms}.
     *
     * @documentable
     * @param        string $typeName Specifies the type name
     *                                (see <em>db_xxx</em> constants in the description of {@link \Db\ActiveRecord} class)
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function type($typeName)
    {
        $validTypes = array(db_varchar, db_number, db_float, db_bool, db_datetime, db_date, db_time, db_text);
        if (!in_array($typeName, $validTypes)) {
            throw new SystemException('Invalid database type: ' . $typeName);
        }

        $this->type = $typeName;
        $this->columnInfo = null;

        return $this;
    }

    /**
     * Specifies the date format.
     * The date format is used for displaying date and date/time field values in {@link \Db\ListBehavior lists} and {@link \Db\FormBehavior forms}.
     *
     * @documentable
     * @param        string $displayFormat Specifies the display format, compatible with {@link http://php.net/manual/en/function.strftime.php strftime} PHP function.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function dateFormat($displayFormat)
    {
        if ($this->type == db_datetime || $this->type == db_date || $this->type == db_time) {
            $this->dateFormat = $displayFormat;
        } else {
            throw new SystemException(
                'Error in column definition for: ' . $this->dbName . ' column. Method "dateFormat" is applicable only for date or time fields.'
            );
        }
        $this->validation(null, true);

        return $this;
    }

    /**
     * Specifies the time format.
     * The date format is used for displaying date and date/time field values in {@link \Db\ListBehavior lists} and {@link \Db\FormBehavior forms}.
     *
     * @documentable
     * @param        string $displayFormat Specifies the display format, compatible with {@link http://php.net/manual/en/function.strftime.php strftime} PHP function.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function timeFormat($displayFormat)
    {
        if ($this->type == db_datetime || $this->type == db_time) {
            $this->timeFormat = $displayFormat;
        } else {
            throw new SystemException(
                'Error in column definition for: ' . $this->dbName . ' column. Method "timeFormat" is applicable only for datetime or time fields.'
            );
        }
        $this->validation(null, true);

        return $this;
    }

    public function dateTimeFormat($displayFormat)
    {
        if ($this->type == db_datetime) {
            $this->dateTimeFormat = $displayFormat;
        } else {
            throw new SystemException(
                'Error in column definition for: ' . $this->dbName . ' column. Method "dateTimeFormat" is applicable only for datetime fields.'
            );
        }

        return $this;
    }

    /**
     * Disables timezone conversion for datetime fields.
     * By default datetime fields are converted to GMT during saving and {@link \Db\ActiveRecord::displayField() displayField()} returns value converted
     * back to a time zone specified in <em>TIMEZONE</em> parameter in the configuration file (config.php). You can cancel this behavior
     * by calling this method.
     *
     * @documentable
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function dateAsIs()
    {
        $this->dateAsIs = true;
        $this->validation(null, true);
        return $this;
    }

    /**
     * Sets the precision for displaying floating point numbers in {@link \Db\ListBehavior lists}.
     *
     * @documentable
     * @param        integer $precision Specifies the number of decimal places.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function precision($precision)
    {
        if ($this->type == db_float) {
            $this->precision = $precision;
        } else {
            throw new SystemException(
                'Error in column definition for: ' . $this->dbName . ' column. Method "precision" is applicable only for floating point number fields.'
            );
        }

        return $this;
    }

    /**
     * Sets the maximum length for displaying varchar and text values in {@link \Db\ListBehavior lists}.
     * Text values longer than the specified length get truncated.
     *
     * @documentable
     * @param        integer $length Specifies the length value.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function length($length)
    {
        if ($this->type == db_varchar || $this->type == db_text) {
            $this->length = $length;
        } else {
            throw new SystemException(
                'Error in column definition for: ' . $this->dbName . ' column. Method "length" is applicable only for varchar or text fields.'
            );
        }

        return $this;
    }

    /**
     * Hides the column from {@link \Db\ListBehavior lists}.
     *
     * @documentable
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function invisible()
    {
        $this->visible = false;
        return $this;
    }

    /**
     * Makes the column invisible in {@link \Db\ListBehavior lists} by default.
     * Users can make the column visible by updating the list settings.
     *
     * @documentable
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function defaultInvisible()
    {
        $this->defaultVisible = false;
        return $this;
    }

    /**
     * Sets column title for {@link \Db\ListBehavior lists}.
     * By default list column titles match column names. You can override the column name with this method.
     *
     * @documentable
     * @param        string $title Specifies the column {@link \Db\ListBehavior list} title.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function listTitle($title)
    {
        $this->listTitle = $title;
        return $this;
    }

    /**
     * Allows to hide the column {@link \Db\ListBehavior list} title.
     *
     * @documentable
     * @param        boolean $value Determines whether the title is invisible.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function listNoTitle($value = true)
    {
        $this->listNoTitle = $value;
        return $this;
    }

    /**
     * Do not log changes of the column.
     */
    public function noLog()
    {
        $this->noLog = true;
        return $this;
    }

    /**
     * Disables or enables sorting for the column in {@link \Db\ListBehavior lists}.
     * By default all columns are sortable in {@link \Db\ListBehavior lists}. You can use this method to disable sorting by a specific column.
     *
     * @documentable
     * @param        boolean $value Determines whether the column is not sortable.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function noSorting($value = true)
    {
        $this->noSorting = true;
        return $this;
    }

    /**
     * Log changes of the column. By default changes are not logged for calculated and custom columns.
     */
    public function log()
    {
        $this->log = true;
        return $this;
    }

    /**
     * Indicates that lists should use this column as a sorting column by default.
     * {@link \Db\ListBehavior List Behavior} uses this feature until the user
     * chooses selects another sorting column.
     *
     * @documentable
     * @param        string $direction Specifies the sorting direction - <em>asc</em> or <em>desc</em>.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function order($directon = 'asc')
    {
        $this->defaultOrder = $directon;

        return $this;
    }

    /**
     * Indicates that the column value should be formatted as currency.
     *
     * @documentable
     * @param        boolean $value Enables or disables the feature. Pass TRUE value to display values as currency.
     * @return       \Db\ColumnDefinition Returns the updated column definition object.
     */
    public function currency($value)
    {
        $this->currency = $value;
        return $this;
    }

    /**
     * Initializes and returns the validation rule set object.
     * Use the {@link Phpr\ValidationRules validation rule set object} to configure the column validation parameters.
     * Note that validation is automatically enabled for date, datetime, float and numeric fields.
     *
     * @documentable
     * @param        string  $customFormatMessage Specifies the format-specific validation message.
     * @param        boolean $re_add              This parameter is for internal use.
     * @return       Phpr\ValidationRules Returns the validation object.
     */
    public function validation($customFormatMessage = null, $readd = false)
    {
        if (!strlen($this->type)) {
            throw new SystemException(
                'Error applying validation to ' . $this->dbName . ' column. Column type is unknown. Probably this is a calculated column. Please call the "type" method to set the column type.'
            );
        }

        if ($this->validationObj && !$readd) {
            return $this->validationObj;
        }

        $dbName = $this->isReference ? $this->referenceForeignKey : $this->dbName;

        $rule = $this->model->validation->add($dbName, $this->displayName);
        if ($this->type == db_date) {
            $rule->date($this->dateFormat, $customFormatMessage);
        } elseif ($this->type == db_datetime) {
            $rule->dateTime($this->dateFormat . ' ' . $this->timeFormat, $customFormatMessage, $this->dateAsIs);
        } elseif ($this->type == db_float) {
            $rule->float($customFormatMessage);
        } elseif ($this->type == db_number) {
            $rule->numeric($customFormatMessage);
        }

        return $this->validationObj = $rule;
    }

    /*
     *
     * Internal methods - used by the framework
     *
     */

    public function getColumnInfo()
    {
        return $this->columnInfo;
    }

    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    public function getTimeFormat()
    {
        return $this->timeFormat;
    }

    /*
     * Datetime fields are converted to GMT during saving and displayValue returns value converted
      * back to a time zone specified in the configuration file.
      */
    public function displayValue($media)
    {
        $dbName = $this->dbName;

        if (!$this->isReference) {
            $value = $this->model->$dbName;
        } else {
            $columName = $this->calculatedColumnName;
            $value = $this->model->$columName;
        }

        switch ($this->type) {
        case db_varchar:
        case db_text:
            if ($media == 'form' || $this->length === null) {
                return $value;
            }

            return Html::strTrim($value, $this->length);
        case db_number:
        case db_bool:
            return $value;
        case db_float:
            if ($media != 'form') {
                if ($this->currency) {
                    if (method_exists($this->model, 'format_currency')) {
                        return $this->model->format_currency($value);
                    } else {
                        return Phpr::$lang->currency($value);
                    }
                }
                return Phpr::$lang->num($value, $this->precision);
            } else {
                return $value;
            }
            // no break
        case db_date:
            if (gettype($value) == 'string' && strlen($value)) {
                $value = new PhprDateTime($value . ' 00:00:00');
            }
            return $value ? $value->format($this->dateFormat) : null;
        case db_datetime:
            if (gettype($value) == 'string' && strlen($value)) {
                if (strlen($value) == 10) {
                    $value .= ' 00:00:00';
                }
                $value = new PhprDateTime($value);
            }
            if (!$this->dateAsIs) {
                if ($media == 'time') {
                    return $value ? Date::display($value, $this->timeFormat) : null;
                } elseif ($media == 'date') {
                    return $value ? Date::display($value, $this->dateFormat) : null;
                } else {
                    return $value ? Date::display($value, $this->dateTimeFormat) : null;
                }
            } else {
                if ($media == 'time') {
                    return $value ? $value->format($this->timeFormat) : null;
                } elseif ($media == 'date') {
                    return $value ? $value->format($this->dateFormat) : null;
                } else {
                    return $value ? $value->format($this->dateTimeFormat) : null;
                }
            }
            // no break
        case db_time:
            return $value;
        default:
            return $value;
        }
    }

    public function getSortingColumnName()
    {
        if (!$this->isReference) {
            return $this->dbName;
        }

        return $this->calculatedColumnName;
    }

    protected function defineReferenceColumn()
    {
        if (!array_key_exists($this->relationName, $this->model->has_models)) {
            throw new SystemException(
                'Error defining reference "' . $this->relationName . '". Relation ' . $this->relationName . ' is not found in model ' . get_class(
                    $this->model
                )
            );
        }

        $relationType = $this->model->has_models[$this->relationName];

        $has_primary_key = $has_foreign_key = false;
        $options = $this->model->get_relation_options(
            $relationType,
            $this->relationName,
            $has_primary_key,
            $has_foreign_key
        );

        if (!is_null($options['finder_sql'])) {
            throw new SystemException(
                'Error defining reference "' . $this->relationName . '". Relation finder_sql option is not supported.'
            );
        }

        $this->referenceType = $relationType;

        $columnName = $this->calculatedColumnName = $this->dbName . '_calculated';

        $colDefinition = array();
        $colDefinition['type'] = $this->type;

        $this->referenceClassName = $options['class_name'];

        if (!array_key_exists($options['class_name'], self::$cachedClassInstances)) {
            $object = new $options['class_name'](null, array('no_column_init' => true, 'no_validation' => true));
            self::$cachedClassInstances[$options['class_name']] = $object;
        }

        $object = self::$cachedClassInstances[$options['class_name']];

        if ($relationType == 'has_one' || $relationType == 'belongs_to') {
            $objectTableName = $this->relationName . '_calculated_join';
            $colDefinition['sql'] = str_replace('@', $objectTableName . '.', $this->referenceValueExpr);

            $joinExists = isset(self::$relationJoins[$this->model->objectId][$this->relationName]);

            if (!$joinExists) {
                switch ($relationType) {
                case 'has_one':
                    if (!$has_foreign_key) {
                        $options['foreign_key'] = Inflector::foreignKey(
                            $this->model->table_name,
                            $object->primary_key
                        );
                    }

                    $this->referenceForeignKey = $options['foreign_key'];
                    $condition = "{$objectTableName}.{$options['foreign_key']} = {$this->model->table_name}.{$options['primary_key']}";
                    $colDefinition['join'] = array("{$object->table_name} as {$objectTableName}" => $condition);
                    break;
                case 'belongs_to':
                    $condition = "{$objectTableName}.{$options['primary_key']} = {$this->model->table_name}.{$options['foreign_key']}";
                    $this->referenceForeignKey = $options['foreign_key'];
                    $colDefinition['join'] = array("{$object->table_name} as {$objectTableName}" => $condition);

                    break;
                }
                self::$relationJoins[$this->model->objectId][$this->relationName] = $this->referenceForeignKey;
            } else {
                $this->referenceForeignKey = self::$relationJoins[$this->model->objectId][$this->relationName];
            }
        } else {
            $this->referenceForeignKey = $this->relationName;

            switch ($relationType) {
            case 'has_many':
                $valueExpr = str_replace('@', $object->table_name . '.', $this->referenceValueExpr);
                $colDefinition['sql'] = "select group_concat($valueExpr ORDER BY 1 SEPARATOR ', ') from {$object->table_name} where
							{$object->table_name}.{$options['foreign_key']} = {$this->model->table_name}.{$options['primary_key']}";

                if ($options['conditions']) {
                    $colDefinition['sql'] .= " and ({$options['conditions']})";
                }

                break;
            case 'has_and_belongs_to_many':
                $join_table_alias = $this->relationName . '_relation_table';
                $valueExpr = str_replace('@', $join_table_alias . '.', $this->referenceValueExpr);

                if (!isset($options['join_table'])) {
                    $options['join_table'] = $this->model->get_join_table_name(
                        $this->model->table_name,
                        $object->table_name
                    );
                }

                if (!$has_primary_key) {
                    $options['primary_key'] = Inflector::foreignKey(
                        $this->model->table_name,
                        $this->model->primary_key
                    );
                }

                if (!$has_foreign_key) {
                    $options['foreign_key'] = Inflector::foreignKey(
                        $object->table_name,
                        $object->primary_key
                    );
                }

                $colDefinition['sql'] = "select group_concat($valueExpr ORDER BY 1 SEPARATOR ', ') from {$object->table_name} as {$join_table_alias}, {$options['join_table']} where
							{$join_table_alias}.{$object->primary_key}={$options['join_table']}.{$options['foreign_key']} and
							{$options['join_table']}.{$options['primary_key']}={$this->model->table_name}.{$this->model->primary_key}";

                if ($options['conditions']) {
                    $colDefinition['sql'] .= " and ({$options['conditions']})";
                }
                break;
            }
        }

        $this->model->calculated_columns[$columnName] = $colDefinition;
    }

    public function setContext($model)
    {
        $this->model = $model;
        return $this;
    }
}
