<?php

/**
 * Allows to extend models with tree functionality.
 * Any {@link Db_ActiveRecord} descendant can be extended with this class.
 * To extend a model class add this class name to the model's <em>implement</em>
 * property. Example:
 * <pre>public $implement = 'Db_Act_As_Tree';</pre>
 * @documentable
 * @author LemonStand eCommerce Inc.
 * @package core.classes
 */
class Db_Act_As_Tree extends Phpr_Extension
{
    private $_model_class;
    private $_model;
    private static $_object_cache = array();
    private static $_parent_cache = array();
    private static $_cache_sort_column = array();

    /**
     * @var string Specifies a database column which contains a reference to a parent record.
     * Default value of this property is <em>parent_id</em>, but it can be overridden in the
     * extended model.
     * @documentable
     */
    public $act_as_tree_parent_key = 'parent_id';
    public $act_as_tree_name_field = 'name';
    public $act_as_tree_level = 0;
    public $act_as_tree_sql_filter = null;

    public function __construct($model, $proxy_model_class = null)
    {
        $this->_model_class = $proxy_model_class ? $proxy_model_class : get_class($model);
        $this->_model = $model;
    }

    public static function clear_cache()
    {
        self::$_object_cache = array();
        self::$_parent_cache = array();
        self::$_cache_sort_column = array();
    }

    /**
     * Returns a list of children records.
     * Usage example:
     * <pre>$subcategories = $category->list_children('front_end_sort_order');</pre>
     * @documentable
     * @param string $order_by Specifies a database column name to sort the items by.
     * @return Db_DataCollection Returns a collection of children records.
     */
    public function list_children($order_by = 'name')
    {
        if (!$this->cache_exists($order_by)) {
            $this->init_cache($order_by);
        }

        $cache_key = $this->get_cache_key($order_by);

        if (isset(self::$_object_cache[$this->_model_class][$cache_key][$this->_model->id])) {
            return new Db_DataCollection(self::$_object_cache[$this->_model_class][$cache_key][$this->_model->id]);
        }

        return new Db_DataCollection();
    }

    /**
     * Returns a list of root records.
     * Usage example:
     * <pre>$root_categories = Shop_Category::create()->list_root_children('front_end_sort_order');</pre>
     * @documentable
     * @param string $order_by Specifies a database column name to sort the items by.
     * @return Db_DataCollection Returns a collection of root records.
     */
    public function list_root_children($order_by = 'name')
    {
        if (!$this->cache_exists($order_by)) {
            $this->init_cache($order_by);
        }

        $cache_key = $this->get_cache_key($order_by);

        if (isset(self::$_object_cache[$this->_model_class][$cache_key][-1])) {
            return new Db_DataCollection(self::$_object_cache[$this->_model_class][$cache_key][-1]);
        }

        return new Db_DataCollection();
    }

    public function list_all_children($order_by = 'name')
    {
        $result = $this->list_all_children_recursive($order_by);

        return $result;
    }

    public function list_all_children_recursive($order_by)
    {
        $result = array();
        $children = $this->_model->list_children($order_by);

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
            $result[] = $parent->{$this->_model->act_as_tree_name_field};
        }

        return implode($separator, array_reverse($result));
    }

    /**
     * Returns a parent record.
     * @documentable
     * @param string $order_by Specifies a database column name to sort the items by.
     * Due to the performance considerations the parameter value should match the value
     * you use for {@link Db_Act_As_Tree::list_all_children_recursive() list_all_children_recursive()}
     * and {@link Db_Act_As_Tree::list_children() list_children()} methods.
     * @return Db_ActiveRecord Returns the parent model or NULL if the parent record is not found or the method is called for a root record.
     */
    public function get_parent($order_by = 'name')
    {
        if (!$this->cache_exists($order_by)) {
            $this->init_cache($order_by);
        }

        $cache_key = $this->get_cache_key($order_by);

        $parentKey = $this->_model->act_as_tree_parent_key;
        if (!$this->_model->$parentKey) {
            return null;
        }

        if (!isset(self::$_parent_cache[$this->_model_class][$cache_key][$this->_model->$parentKey])) {
            return null;
        }

        return self::$_parent_cache[$this->_model_class][$cache_key][$this->_model->$parentKey];
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
     * @documentable
     * @param boolean $include_this Determines whether the current model should be included to the list.
     * @return array Returns an array of parent records.
     */
    public function get_parents($include_this = false, $order_by = 'name')
    {
        $parent = $this->_model->get_parent($order_by);
        $result = array();

        if ($include_this) {
            $result[] = $this->_model;
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
            $model = clone $this->_model;
            $cache_key = $this->get_cache_key($order_by);

            if ($model->act_as_tree_sql_filter) {
                $model->where($model->act_as_tree_sql_filter);
            }

            $model->applyCalculatedColumns();
            $sql = $model->order($order_by)->build_sql();

            $records = Db_DbHelper::queryArray($sql);

            $_object_cache = array();
            $_parent_cache = array();

            $parentKey = $this->_model->act_as_tree_parent_key;
            foreach ($records as $record_data) {
                $record_data['act_as_tree_parent_key'] = $parentKey;
                $record_data['act_as_tree_sql_filter'] = $model->act_as_tree_sql_filter;

                $record = new Db_ActiverecordProxy(
                    $record_data['id'],
                    $this->_model_class,
                    $record_data,
                    $this->_model->strict
                );
                $record->extend_with('Db_Act_As_Tree', false, $this->_model_class);

                $parent_id = $record->$parentKey != null ? $record->$parentKey : -1;

                if (!isset($_object_cache[$parent_id])) {
                    $_object_cache[$parent_id] = array();
                }

                $_object_cache[$parent_id][] = $record;
                $_parent_cache[$record->id] = $record;
            }

            self::$_object_cache[$this->_model_class][$cache_key] = $_object_cache;
            self::$_parent_cache[$this->_model_class][$cache_key] = $_parent_cache;
            self::$_cache_sort_column[$this->_model_class][$cache_key] = $order_by;

            return;
        }

        $class_name = $this->_model_class;
        $cache_key = $this->get_cache_key($order_by);

        $model = clone $this->_model;

        $model->order($order_by);

        if ($model->act_as_tree_sql_filter) {
            $model->where($model->act_as_tree_sql_filter);
        }

        $records = $model->find_all();
        $_object_cache = array();
        $_parent_cache = array();

        $parentKey = $this->_model->act_as_tree_parent_key;
        foreach ($records as $record) {
            $parent_id = $record->$parentKey !== null ? $record->$parentKey : -1;

            if (!isset($_object_cache[$parent_id])) {
                $_object_cache[$parent_id] = array();
            }

            $_object_cache[$parent_id][] = $record;
            $_parent_cache[$record->id] = $record;
        }

        self::$_object_cache[$this->_model_class][$cache_key] = $_object_cache;
        self::$_parent_cache[$this->_model_class][$cache_key] = $_parent_cache;
        self::$_cache_sort_column[$this->_model_class][$cache_key] = $order_by;
    }

    private function get_cache_key($order_by)
    {
        return $order_by . $this->_model->act_as_tree_sql_filter;
    }

    private function cache_exists($order_by)
    {
        $cache_key = $this->get_cache_key($order_by);
        return array_key_exists($this->_model_class, self::$_object_cache) && array_key_exists(
            $cache_key,
            self::$_object_cache[$this->_model_class]
        );
    }

    private function cache_key_match($order_by)
    {
        if (!array_key_exists($this->_model_class, self::$_cache_sort_column)) {
            return false;
        }

        return self::$_cache_sort_column[$this->_model_class] == $order_by;
    }
}
