<?php namespace Db;

use Phpr;
use Phpr\Extension;
use Db\Helper as Db_Helper;
use Db\ActiveRecordProxy;

/**
 * Allows to extend models with tree functionality.
 * Any {@link Db\ActiveRecord} descendant can be extended with this class.
 * To extend a model class add this class name to the model's <em>implement</em>
 * property. Example:
 * <pre>public $implement = 'Db\Act_As_Tree';</pre>
 *
 * @documentable
 * @author       LSAPP
 * @package      core.classes
 */
class Act_As_Tree extends Extension
{
    private $modelClass;
    private $model;
    private static $objectCache = array();
    private static $parentCache = array();
    private static $cacheSortColumn = array();

    /**
     * @var          string Specifies a database column which contains a reference to a parent record.
     * Default value of this property is <em>parent_id</em>, but it can be overridden in the
     * extended model.
     * @documentable
     */
    public $act_as_tree_parent_key = 'parent_id';
    public $act_as_tree_name_field = 'name';
    public $act_as_tree_level = 0;
    public $act_as_tree_sql_filter = null;

    public function __construct($model)
    {
        $this->modelClass = is_a($model, 'Db\ActiveRecordProxy') ? $model->get_proxied_model_class() : get_class($model);
        $this->model = $model;
    }

    public static function clear_cache()
    {
        self::$objectCache = array();
        self::$parentCache = array();
        self::$cacheSortColumn = array();
    }

    /**
     * Returns a list of children records.
     * Usage example:
     * <pre>$subcategories = $category->list_children('front_end_sort_order');</pre>
     *
     * @documentable
     * @param        string $order_by Specifies a database column name to sort the items by.
     * @return       Db\DataCollection Returns a collection of children records.
     */
    public function list_children($order_by = 'name')
    {
        if (!$this->cache_exists($order_by)) {
            $this->init_cache($order_by);
        }

        $cache_key = $this->get_cache_key($order_by);

        if (isset(self::$objectCache[$this->modelClass][$cache_key][$this->model->id])) {
            return new DataCollection(self::$objectCache[$this->modelClass][$cache_key][$this->model->id]);
        }

        return new DataCollection();
    }

    /**
     * Returns a list of root records.
     * Usage example:
     * <pre>$root_categories = Shop_Category::create()->list_root_children('front_end_sort_order');</pre>
     *
     * @documentable
     * @param        string $order_by Specifies a database column name to sort the items by.
     * @return       Db\DataCollection Returns a collection of root records.
     */
    public function list_root_children($order_by = 'name')
    {
        if (!$this->cache_exists($order_by)) {
            $this->init_cache($order_by);
        }

        $cache_key = $this->get_cache_key($order_by);

        if (isset(self::$objectCache[$this->modelClass][$cache_key][-1])) {
            return new DataCollection(self::$objectCache[$this->modelClass][$cache_key][-1]);
        }

        return new DataCollection();
    }

    public function list_all_children($order_by = 'name')
    {
        $result = $this->list_all_children_recursive($order_by);

        return $result;
    }

    public function list_all_children_recursive($order_by)
    {
        $result = array();
        $children = $this->model->list_children($order_by);

        foreach ($children as $child) {
            $result[] = $child;

            $child_result = $child->list_all_children_recursive($order_by);
            foreach ($child_result as $sub_child) {
                $result[] = $sub_child;
            }
        }

        return $result;
    }

    public function get_path($separator = " > ", $include_this = true, $order_by = 'name')
    {
        $parents = $this->get_parents($include_this, $order_by);
        $parents = array_reverse($parents);

        $result = array();
        foreach ($parents as $parent) {
            $result[] = $parent->{$this->model->act_as_tree_name_field};
        }

        return implode($separator, array_reverse($result));
    }

    /**
     * Returns a parent record.
     *
     * @documentable
     * @param        string $order_by Specifies a database column name to sort the items by.
     *                                Due to the performance considerations the parameter value should match the value
     *                                you use for {@link Db\Act_As_Tree::list_all_children_recursive() list_all_children_recursive()}
     *                                and {@link Db\Act_As_Tree::list_children() list_children()} methods.
     * @return       Db\ActiveRecord Returns the parent model or NULL if the parent record is not found or the method is called for a root record.
     */
    public function get_parent($order_by = 'name')
    {
        if (!$this->cache_exists($order_by)) {
            $this->init_cache($order_by);
        }

        $cache_key = $this->get_cache_key($order_by);

        $parentKey = $this->model->act_as_tree_parent_key;
        if (!$this->model->$parentKey) {
            return null;
        }

        if (!isset(self::$parentCache[$this->modelClass][$cache_key][$this->model->$parentKey])) {
            return null;
        }

        return self::$parentCache[$this->modelClass][$cache_key][$this->model->$parentKey];
    }

    /**
     * Returns a list of parent records.
     * You can use this method for displaying a path to a category, for example on the product page, in the following format:
     * <em>Category: Crafts, Stationery and more  » Art</em>. Code example:
     * <pre>
     * <p>Category:
     *   <?
     *     $categories = $product->category_list[0]->get_parents(true);
     *     $cnt = count($categories);
     *     foreach ($categories as $index=>$category):
     *   ?>
     *     <a href="<?= $category->page_url('/category') ?>"><?= h($category->name) ?></a>
     *     <? if ($index < $cnt-1) echo "»" ?>
     *   <? endforeach ?>
     * </p>
     * </pre>
     *
     * @documentable
     * @param        boolean $include_this Determines whether the current model should be included to the list.
     * @return       array Returns an array of parent records.
     */
    public function get_parents($include_this = false, $order_by = 'name')
    {
        $parent = $this->model->get_parent($order_by);
        $result = array();

        if ($include_this) {
            $result[] = $this->model;
        }

        while ($parent != null) {
            $result[] = $parent;
            $parent = $parent->get_parent($order_by);
        }

        return array_reverse($result);
    }

    private function init_cache($order_by = 'name')
    {
        if (Phpr::$config->get('USE_PROXY_MODELS')) {
            $model = clone $this->model;
            $cache_key = $this->get_cache_key($order_by);

            if ($model->act_as_tree_sql_filter) {
                $model->where($model->act_as_tree_sql_filter);
            }

            $model->applyCalculatedColumns();
            $sql = $model->order($order_by)->build_sql();

            $records = DbHelper::queryArray($sql);

            $_object_cache = array();
            $_parent_cache = array();

            $parentKey = $this->model->act_as_tree_parent_key;
            foreach ($records as $record_data) {
                $record_data['act_as_tree_parent_key'] = $parentKey;
                $record_data['act_as_tree_sql_filter'] = $model->act_as_tree_sql_filter;

                $record = new ActiverecordProxy(
                    $record_data['id'],
                    $this->modelClass,
                    $record_data,
                    $this->model->strict
                );
                $record->extend_with('Db\Act_As_Tree', false);

                $parent_id = $record->$parentKey != null ? $record->$parentKey : -1;

                if (!isset($_object_cache[$parent_id])) {
                    $_object_cache[$parent_id] = array();
                }

                $_object_cache[$parent_id][] = $record;
                $_parent_cache[$record->id] = $record;
            }

            self::$objectCache[$this->modelClass][$cache_key] = $_object_cache;
            self::$parentCache[$this->modelClass][$cache_key] = $_parent_cache;
            self::$cacheSortColumn[$this->modelClass][$cache_key] = $order_by;

            return;
        }

        $class_name = $this->modelClass;
        $cache_key = $this->get_cache_key($order_by);

        $model = clone $this->model;

        $model->order($order_by);

        if ($model->act_as_tree_sql_filter) {
            $model->where($model->act_as_tree_sql_filter);
        }

        $records = $model->find_all();
        $_object_cache = array();
        $_parent_cache = array();

        $parentKey = $this->model->act_as_tree_parent_key;
        foreach ($records as $record) {
            $parent_id = $record->$parentKey !== null ? $record->$parentKey : -1;

            if (!isset($_object_cache[$parent_id])) {
                $_object_cache[$parent_id] = array();
            }

            $_object_cache[$parent_id][] = $record;
            $_parent_cache[$record->id] = $record;
        }

        self::$objectCache[$this->modelClass][$cache_key] = $_object_cache;
        self::$parentCache[$this->modelClass][$cache_key] = $_parent_cache;
        self::$cacheSortColumn[$this->modelClass][$cache_key] = $order_by;
    }

    private function get_cache_key($order_by)
    {
        return $order_by . $this->model->act_as_tree_sql_filter;
    }

    private function cache_exists($order_by)
    {
        $cache_key = $this->get_cache_key($order_by);
        return array_key_exists($this->modelClass, self::$objectCache) && array_key_exists(
            $cache_key,
            self::$objectCache[$this->modelClass]
        );
    }

    private function cache_key_match($order_by)
    {
        if (!array_key_exists($this->modelClass, self::$cacheSortColumn)) {
            return false;
        }

        return self::$cacheSortColumn[$this->modelClass] == $order_by;
    }
}
