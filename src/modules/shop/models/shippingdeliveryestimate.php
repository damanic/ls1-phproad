<?php

namespace Shop;

use Db\ActiveRecord;

class ShippingDeliveryEstimate extends ActiveRecord
{
    public $table_name = 'shop_shipping_delivery_estimate';
    protected static $cache = [];

    public $belongs_to = [
        'shipping_zone' => [
            'class_name' => 'Shop\ShippingZone',
            'foreign_key' => 'shipping_zone_id'
        ],
        'service_level' => [
            'class_name' => 'Shop\ShippingServiceLevel',
            'foreign_key' => 'shipping_service_level_id'
        ]
    ];

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
        $this->define_relation_column('shipping_zone', 'shipping_zone', 'Shipping Zone ', db_varchar, '@name')
            ->validation()
            ->required('Please specify a shippping zone');

        $this->define_relation_column('service_level', 'service_level', 'Shipping Service Level ', db_varchar, '@name');

        $this->define_column('min_days', 'Minimum Days')
            ->validation()
            ->fn('trim')
            ->required('Please specify min days for delivery');

        $this->define_column('max_days', 'Maximum Days')
            ->validation()
            ->fn('trim')
            ->required('Please specify max days for delivery');

        $this->define_column('as_text', 'Description');
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('shipping_zone')
            ->emptyOption('Please select')
            ->referenceSort('name');

        //$this->add_form_field('service_level')->emptyOption('All levels')->referenceSort('name');

        $this->add_form_field('min_days', 'left')
            ->comment('Fastest possible delivery time', 'above');

        $this->add_form_field('max_days', 'right')
            ->comment('Slowest possible delivery time', 'above');

        $this->add_form_field('as_text')
            ->comment('Written time estimate, eg. 1-2 Business days', 'above');
    }
}
