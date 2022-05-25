<?php

namespace Shop;

use Db\ActiveRecord;

/**
 * Represents a product bundle offer.
 * A product bundle contains a master $product to which a set of bundle item products are assigned
 *
 * Example: A ProductBundleOffer is created with a computer product assigned as the master $product
 * and a keyboard product and mouse product are assigned as $items (@link ProductBundleOfferItem}.
 * This allows a customer to add a keyboard and mouse to their computer purchase as a bundle.
 *
 * @property string $name Specifies the bundle item name (e.g. Computer Package).
 * @property string $description Specifies the bundle item description.
 * @property bool $is_required Determines whether the bundle is required. (e.g must choose a mouse/keyboard)
 * @property string $control_type Determines the control type to be used on the store pages.
 * Possible values are: <em>dropdown</em>, <em>checkbox</em>, <em>radio</em>.
 * @property \Db\DataCollection $images A collection of images associated with the bundle item.
 * Each element in the collection is an object of the {@link \Db\File} class.
 * @property Product $product A reference to the master bundle product.
 * @property \Db\DataCollection $items A collection of item products.
 * Each element in the collection is an object of {@link ProductBundleOfferItem} class.
 * @documentable
 * @see https://lsdomainexpired.mjman.net/docs/managing_bundle_products/ Managing bundle products
 * @see https://lsdomainexpired.mjman.net/docs/displaying_product_bundle_items/ Displaying product bundle items
 * @see BundleHelper
 * @see ProductBundleOfferItem
 * @author LSAPP - MJMAN
 * @package shop.models
 */
class ProductBundleOffer extends ActiveRecord
{
    public $table_name = 'shop_product_bundle_items';
    public $implement = 'Db_Sortable';

    const control_dropdown = 'dropdown';
    const control_checkbox = 'checkbox';
    const control_radio = 'radio';

    protected static $cache = [];

    public $belongs_to = [
        'product' => [
            'class_name' => 'Shop\Product',
            'foreign_key' => 'product_id'
        ]
    ];

    public $has_many = [
        'items_all' => [
            'class_name' => 'Shop\ProductBundleOfferItem',
            'delete' => true,
            'order' => 'sort_order',
            'foreign_key' => 'item_id'
        ],
        'items' => [
            'class_name' => 'Shop\ProductBundleOfferItem',
            'delete' => true,
            'order' => 'sort_order',
            'foreign_key' => 'item_id',
            'conditions' => '
					(is_active is not null and is_active=1) and 
					(
						exists(
						    select * from shop_products where shop_products.id=shop_bundle_item_products.product_id and
						((
							shop_products.enabled=1 and not (
								ifnull(shop_products.track_inventory, 0)=1 and
								ifnull(shop_products.hide_if_out_of_stock, 0)=1 and
								(
									(
									    shop_products.stock_alert_threshold is not null 
									    and shop_products.in_stock <= shop_products.stock_alert_threshold
									) or
									(shop_products.stock_alert_threshold is null and shop_products.in_stock<=0)
								)
							)
						) or exists(
							select * from shop_products grouped_products 
							where grouped_products.product_id is not null 
							and grouped_products.product_id=shop_products.id  
							and grouped_products.enabled=1 and not (
									ifnull(grouped_products.track_inventory,0)=1 and
									ifnull(grouped_products.hide_if_out_of_stock,0)=1 and
									(
										(
										    grouped_products.stock_alert_threshold is not null 
										    and grouped_products.in_stock <= grouped_products.stock_alert_threshold
										) or
										(
										    grouped_products.stock_alert_threshold is null 
										    and grouped_products.in_stock<=0
										)
									)
								)
							)
						) and ifnull(shop_products.disable_completely,0)=0 and ifnull(shop_products.grouped, 0)=0
					))
				'
        ],
        'images' => [
            'class_name' => 'Db\File',
            'foreign_key' => 'master_object_id',
            'conditions' => "master_object_class='Shop_ProductBundleOffer' and field='images'",
            'order' => 'sort_order, id',
            'delete' => true
        ]
    ];

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
        $front_end = ActiveRecord::$execution_context == 'front-end';

        $this->define_column('name', 'Name')
            ->validation()
            ->fn('trim')
            ->required('Please specify the bundle item name.');

        $this->define_column('description', 'Description')
            ->validation()
            ->fn('trim');

        $this->define_column('is_required', 'Required');

        $this->define_column('control_type', 'Control type');

        $this->define_multi_relation_column('images', 'images', 'Images', $front_end ? null : '@name')
            ->invisible();
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('name')
            ->comment('Bundle item name will be displayed on the product page.', 'above')
            ->tab('Name and description');

        $this->add_form_field('description')
            ->size('small')
            ->tab('Name and description');;

        $this->add_form_field('control_type')
            ->renderAs(frm_dropdown)
            ->tab('Name and description');;
        $this->add_form_field('is_required')
            ->tab('Name and description');

        $this->add_form_field('images')
            ->renderAs(frm_file_attachments)
            ->renderFilesAs('image_list')
            ->addDocumentLabel('Add image(s)')
            ->tab('Images')
            ->noAttachmentsLabel('There are no images uploaded')
            ->fileDownloadBaseUrl(url('ls_backend/files/get/'));
    }

    public function get_control_type_options($key = -1)
    {
        $options = array(
            self::control_dropdown => 'Drop-down menu',
            self::control_checkbox => 'Checkbox list',
            self::control_radio => 'Radio buttons'
        );

        if ($key == -1) {
            return $options;
        }

        return isset($options[$key]) ? $options[$key] : null;
    }

    public function add_products($product_ids, $session_key)
    {
        foreach ($product_ids as $id) {
            $items = $this->list_related_records_deferred('items_all', $session_key);
            foreach ($items as $item) {
                if ($item->product_id == $id) {
                    continue 2;
                }
            }

            $new_item = ProductBundleOfferItem::create();
            $new_item->product_id = $id;
            $new_item->default_quantity = 1;
            $new_item->allow_manual_quantity = 1;
            $new_item->price_override_mode = ProductBundleOfferItem::price_override_default;
            $new_item->is_active = 1;
            $new_item->save();

            $this->items_all->add($new_item, $session_key);
        }

        $this->save();
    }

    public function remove_products($product_ids, $session_key)
    {
        foreach ($product_ids as $product_id) {
            $bundle_product = ProductBundleOfferItem::create()->find($product_id);
            if ($bundle_product) {
                $this->items_all->delete($bundle_product, $session_key);
            }
        }
    }

    /**
     * Returns a bundle item product (rule) for a given shop product
     * @param Product $shop_product Shop product object
     * @return mixed Returns a ProductBundleOfferItem rule for the given Product or NULL if not found
     */
    public function get_item_product(Product $shop_product)
    {
        foreach ($this->items as $item) {
            if ($item->product_id == $shop_product->id) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Finds a product bundle item by its identifier.
     * @documentable
     * @param integer $id Specifies the product bundle item identifier.
     * @return ProductBundleOffer Returns the product bundle item object. Returns NULL if the object is not found.
     */
    public static function find_by_id($id)
    {
        if (!array_key_exists($id, self::$cache)) {
            self::$cache[$id] = self::create()->find($id);
        }

        return self::$cache[$id];
    }


    /**
     * Temporary support for deprecated class properties
     */
    public $custom_columns = array(
        'item_products' => array(),
        'item_products_all' => array()
    );

    public function eval_item_products()
    {
        return $this->items;
    }

    public function eval_item_products_all()
    {
        return $this->items_all;
    }
}
