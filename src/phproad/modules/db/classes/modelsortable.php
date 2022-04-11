<?php namespace Db;

use Phpr\Extension;
use Phpr\ApplicationException;
use Db\Helper as DbHelper;

/*
 * Sortable model extension
 */

/*
 * Usage:
 *
 * Model table must have sort_order table column.
 * In the model class definition: 
 *
 *   public $implement = 'Db\Model_Sortable';
 *
 * To set orders: 
 *
 *   $obj->setItemOrders($item_ids, $item_orders);
 *
 * You can change the sort field used by declaring:
 *
 *   public $sortable_model_field = 'my_sort_order';
 *
 */

class ModelSortable extends Extension
{
	protected $_model;
	protected $_field_name = "sort_order";
	
	public function __construct($model)
	{
		parent::__construct();
		
		$this->_model = $model;

		if (isset($model->sortable_model_field))
			$this->_field_name = $model->sortable_model_field;

		$model->add_event('db:on_after_create', $this, 'setOrderId');
	}
	
	public function setOrderId()
	{
		$new_id = DbHelper::get_last_insert_id();
		DbHelper::query('update `'.$this->_model->table_name.'` set '.$this->_field_name.'=:new_id where id=:new_id', array(
			'new_id'=>$new_id
		));
	}
	
	public function setItemOrders($item_ids, $item_orders)
	{
		if (is_string($item_ids))
			$item_ids = explode(',', $item_ids);
			
		if (is_string($item_orders))
			$item_orders = explode(',', $item_orders);

		if (count($item_ids) != count($item_orders))
			throw new ApplicationException('Invalid setItemOrders call - count of item_ids does not match a count of item_orders');

		foreach ($item_ids as $index=>$id)
		{
			$order = $item_orders[$index];
			DbHelper::query('update `'.$this->_model->table_name.'` set '.$this->_field_name.'=:sort_order where id=:id',
                array(
				'sort_order'=>$order,
				'id'=>$id
			));
		}
	}
}
