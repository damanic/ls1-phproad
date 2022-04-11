<?php
namespace Db;

use Phpr\Pagination;
use Db\Helper as DbHelper;

/**
 * This model type allows you to combine various models in a query,
 * paginate them and return as one data set.
 */
class DataFeed
{
    public $contextVar = 'context_name';
    public $classnameVar = 'class_name';

    protected $collection = array(); // Empty model collection
    protected $contextList = array(); // Used for "tagging" models, returned as $model->context_name
    protected $orderList = array(); // Used for applying sort rules to each model
    protected $selectList = array(); // Used to pass a common alias and use it as a condition
    protected $havingList = array(); // Used to pass a common condition

    protected $removeDuplicates = false;
    protected $limitCount = null;
    protected $limitOffset = null;

    protected $useCustomTimestamp = false; // (Used internally) Merge created_at and updated_at as timestamp_at
    protected $orderTimestamp = null;
    protected $orderDirection = 'DESC';

    // Example:
    //
    //   $feed->select('1 + 2 as answer');
    //   $feed->having('answer = 3');
    //
    //   OR
    //
    //   $model->having('answer = 3');
    //   $feed->add($model, 'tag_name', '@sent_at');

    public static function create()
    {
        return new self();
    }

    /**
     * Add a ActiveRecord model before find_all()
     */
    public function add($record, $contextName = null, $orderByField = null)
    {
        $this->collection[] = clone $record;
        $this->contextList[] = $contextName;
        $this->orderList[] = $orderByField;
        return $this;
    }

    /**
     * Creates a lean sql query to return id, class_name and time stamps
     */
    public function buildSql()
    {
        $sql = array();
        $count = 0;
        foreach ($this->collection as $key => $record) {
            if ($count++ != 0) {
                $sql[] = ($this->removeDuplicates) ? "UNION" : "UNION ALL";
            }

            // Pass Class name
            $record_obj = $record->from($record->table_name, 'id', true);
            $record_obj->select("(SELECT '" . get_class_id($record) . "') as " . $this->classnameVar);

            // Pass Context name
            $context_name = $this->contextList[$key];
            $record_obj->select("(SELECT '" . $context_name . "') as " . $this->contextVar);

            // Pass Select aliases
            foreach ($this->selectList as $select_string) {
                $record_obj->select($select_string);
            }

            // Apply Having conditions
            foreach ($this->havingList as $having_string) {
                $record_obj->having($having_string);
            }

            // Ordering
            if ($this->useCustomTimestamp) {
                $record_obj->select(str_replace('@', $record->table_name . '.',
                        $this->orderTimestamp) . ' as timestamp_at');
            } else {
                if ($this->orderList[$key] !== null) {
                    $record_obj->select(str_replace('@', $record->table_name . '.',
                            $this->orderList[$key]) . ' as timestamp_at');
                } else {
                    $record_obj->select('ifnull(' . $record->table_name . '.updated_at, ' . $record->table_name . '.created_at) as timestamp_at');
                }
            }

            $sql[] = "(" . $record_obj->build_sql() . ")";
        }

        $sql[] = "ORDER BY timestamp_at " . $this->orderDirection;

        if ($this->limitCount !== null && $this->limitOffset !== null) {
            $sql[] = "LIMIT " . $this->limitOffset . ", " . $this->limitCount;
        }

        $sql = implode(' ', $sql);

        return $sql;
    }

    public function countSql()
    {
        $sql = array();

        $sql[] = "SELECT COUNT(*) AS total FROM (";
        $count = 0;
        foreach ($this->collection as $record) {
            if ($count++ != 0) {
                $sql[] = ($this->removeDuplicates) ? "UNION" : "UNION ALL";
            }

            $record_obj = $record->from($record->table_name, 'id', true);
            $sql[] = "(" . $record_obj->build_sql() . ")";
        }

        $sql[] = ") as records";
        $sql = implode(' ', $sql);

        return $sql;
    }

    public function findAll()
    {
        // Build lean SQL statement
        $collection = DbHelper::objectArray($this->buildSql());

        // Build a collection of class_names and the id we need
        $mixed_array = array();
        foreach ($collection as $record) {
            $class_name = $record->{$this->classnameVar};
            $mixed_array[$class_name][] = $record->id;
        }

        // Eager load our data collection
        $collection_array = array();
        foreach ($mixed_array as $class_name => $ids) {
            $obj = new $class_name();
            $collection_array[$class_name] = $obj->where('id in (?)', array($ids))->find_all();
        }

        // Now load our data objects into a final array
        $data_array = array();
        foreach ($collection as $record) {
            // Set Class name
            $class_name = $record->{$this->classnameVar};
            $obj = $collection_array[$class_name]->find($record->id);
            $obj->{$this->classnameVar} = $class_name;

            // Set Context name
            $context_name = $record->{$this->contextVar};
            $obj->{$this->contextVar} = $context_name;

            $data_array[] = $obj;
        }

        return new DataCollection($data_array);
    }

    public function paginate($page_index, $records_per_page)
    {
        $pagination = new Pagination($records_per_page);
        $pagination->setRowCount($this->getRowCount());
        $pagination->setCurrentPageIndex($page_index);

        $this->limit($records_per_page, ($records_per_page * $page_index));

        return $pagination;
    }

    public function getRowCount()
    {
        return DbHelper::scalar($this->countSql());
    }

    public function select($query)
    {
        $this->selectList[] = $query;
        return $this;
    }

    public function having($query)
    {
        $this->havingList[] = $query;
        return $this;
    }

    public function order($order_by_field = null, $direction = null)
    {
        if (is_null($order_by_field) && is_null($direction)) {
            return $this;
        }

        $this->useCustomTimestamp = true;

        if ($order_by_field == 'timestamp_at' || $order_by_field === null) {
            $this->useCustomTimestamp = false;
        } else {
            $this->orderTimestamp = $order_by_field;
        }

        $this->orderDirection = ($direction)
            ? $direction
            : $this->orderDirection;

        return $this;
    }

    public function limit($count = null, $offset = null)
    {
        if (is_null($count) && is_null($offset)) {
            return $this;
        }

        $this->limitCount = (int)$count;
        $this->limitOffset = (int)$offset;

        return $this;
    }

}