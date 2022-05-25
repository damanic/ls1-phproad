<?php
namespace Shop;

use Db\ActiveRecord;
use Phpr\ApplicationException;
use Db\Helper as DbHelper;

class MergeCustomersModel extends ActiveRecord
{
    public $table_name = 'core_configuration_records';
    protected $customerIds = [];
    public $customers;
        
    public $custom_columns = array(
        'destination_customer'=>db_number
    );

    public function define_columns($context = null)
    {
        $this->define_column('destination_customer', 'Destination customer')
            ->validation()
            ->required('Please select the destination customer.');
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('destination_customer')
            ->renderAs(frm_dropdown)
            ->comment(
                'Please select the destination customer to merge the others into. 
                Orders from the other selected customers will be moved to the destination customer. 
                After that the other selected customers will be deleted.', 
                'above'
            );
    }
        
    public function get_destination_customer_options()
    {
        $result = array();
            
        foreach ($this->customerIds as $customer_id) {
            $customer = Customer::create()->find($customer_id);
            if (!$customer) {
                throw new ApplicationException(
                    sprintf('The customer with the identifier %s is not found', $customer_id)
                );
            }
                
            $order_num = DbHelper::scalar(
                'select count(*) from shop_orders where customer_id=:customer_id',
                ['customer_id'=>$customer->id]
            );

            $infoString = $customer->get_display_name().' ('.$customer->email.').';
            $infoString.= 'Registered: '.($customer->guest ? 'yes' : 'no').'.';
            $infoString.= 'Orders: '.$order_num.'.';
            $infoString.= 'Created on '.$customer->displayField('created_at');
            $result[$customer->id] = $infoString;
        }
                
        return $result;
    }
        
    public function init($customer_ids)
    {
        $this->customerIds = $customer_ids;
        $this->customers = implode(',', $customer_ids);
            
        $this->define_form_fields();
    }
        
    public function apply($data)
    {
        $this->define_form_fields();
        $this->validate_data($data);
        $products = array();

        $destination_customer = Customer::create()->find($data['destination_customer']);
        if (!$destination_customer) {
            throw new ApplicationException('The destination customer is not found');
        }
                
        $customer_ids = explode(',', $data['customers']);
            
        foreach ($customer_ids as $customer_id) {
            if ($customer_id == $data['destination_customer']) {
                continue;
            }
                    
            $source_customer = Customer::create()->find($customer_id);
            $source_customer->merge_into($destination_customer);
        }
    }
}
