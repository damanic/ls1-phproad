<?php
namespace Shop;

/**
 * RetailItemInterface
 * Establishes common methods for interaction with Cart_Item and Order_Item
 */

interface RetailItemInterface
{

    /**
     * List price before tax
     * @return float Price of a single unit of this item without discounts
     */
    public function get_list_price();

    /**
     * Offer price before tax
     * @return float Price of a single unit of this item as offered to customer (after discounts)
     */
    public function get_offer_price();

    /**
     * Total list price for all units (quantity) before tax including extras and bundled items
     * @param int If null, the quantity associated with the item is used, otherwise uses the quantity given
     * @return float The total list price for this items quantity
     */
    public function get_total_list_price($quantity = null);

    /**
     * Total offer price for all units (quantity) before tax
     * @param int If null, the quantity associated with the item is used, otherwise uses the quantity given
     * @return float The total offer price for this items quantity (after discounts)
     */
    public function get_total_offer_price($quantity = null);

    /**
     * Tax class ID associated with the Item
     * @return integer The ID if a tax class is assigned, or NULL
     */
    public function get_tax_class_id();
}
