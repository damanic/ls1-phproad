<?php
namespace Shop;

use Backend;
use Db\ActiveRecord;
use Db\Helper as DbHelper;
use Db\DataCollection;
use Phpr\ApplicationException;
use Users\User;

/**
 * Represents a status of an {@link Order order}.
 * Usually you don't need to access the order status class properties directly. The {@link Order} class has hidden
 * status fields which can be accessed through the {@link ActiveRecord::displayField() displayField()}
 * method of the order object.
 * It is preferable to use this method instead of accessing the <em>$status</em> order's property directly because
 * of the performance considerations.
 * There are 2 status-related fields which you can load from an order object with
 * {@link ActiveRecord::displayField() displayField()} method:
 * <ul>
 *   <li><em>status</em> – status name.</li>
 *   <li><em>status_color</em> – status color.</li>
 * </ul>
 * Example:
 * <pre>
 * Order status:
 * <span style="color: <?= $order->displayField('status_color') ?>">
 *   <?= $order->displayField('status') ?>
 * </span>
 * </pre>
 * Use {@link OrderStatusLog::create_record()} method for changing orders' current status.
 * @property int $id Specifies the status record identifier.
 * @property string $code Specifies the status API code.
 * @property string $name Specifies the status name.
 * @property string $color Specifies the status color in HEX format (<em>#9acd32</em>).
 * This field can be used for customize the order list on the
 * {@link https://lsdomainexpired.mjman.net/docs/customer_orders_page Customer Orders} page.
 * @property bool $notify_customer Determines whether a customer should be notified when an order enters this status.
 * @property bool $notify_recipient Determines whether LSAPP users responsible for processing orders with
 *  this status should be notified when an order enters this status.
 * @documentable
 * @see https://lsdomainexpired.mjman.net/docs/configuring_the_order_route_and_user_roles
 * Configuring the order route and user roles
 * @see Order
 * @see OrderStatusLog
 * @package shop.models
 * @author LSAPP - MJMAN
 */
class OrderStatus extends ActiveRecord
{
    public $table_name = 'shop_order_statuses';
    public $enabled = true;

    protected static $code_cache = [];

    const status_new = 'new';
    const status_paid = 'paid';

    public static $colors = [
        '#32cd32',
        '#9acd32',
        '#808000',
        '#ffd700',
        '#ff8c00',
        '#daa520',
        '#ffb6c1',
        '#cc6666',
        '#a0522d',
        '#ff0000',
        '#ffcc99',
        '#9370d8',
        '#0000ff',
        '#708090',
        '#0099cc',
        '#99ccff',
        '#ff6600',
        '#fcd202',
        '#f8ff01',
        '#b0de09',
        '#04d215',
        '#0d8ecf',
        '#0d52d1',
        '#2a0cd0',
        '#8a0ccf',
        '#cd0d74',
        '#754deb',
        '#999999',
        '#dddddd',
        '#333333'
    ];

    public $has_many = [
        'outcoming_transitions' => [
            'class_name' => 'Shop\StatusTransition',
            'foreign_key' => 'from_state_id',
            'delete' => true,
            'order' => 'name'
        ]
    ];

    public $has_and_belongs_to_many = [
        'notifications' => [
            'class_name' => 'Shop\Role',
            'join_table' => 'shop_status_notifications',
            'order' => 'name',
            'foreign_key' => 'shop_role_id',
            'primary_key' => 'shop_status_id'
        ]
    ];

    public $belongs_to = [
        'customer_message_template' => [
            'class_name' => 'System\EmailTemplate',
            'foreign_key' => 'customer_message_template_id',
            'conditions' => '(is_system is null or is_system = 0)'
        ],
        'system_message_template' => [
            'class_name' => 'System\EmailTemplate',
            'foreign_key' => 'admin_message_template_id',
            'conditions' => '(is_system is not null and is_system = 1)'
        ]
    ];

    protected $api_added_columns = [];

    public static function create()
    {
        return new self();
    }

    public function define_columns($context = null)
    {
        $this->define_column('name', 'Name')
            ->order('asc')
            ->validation()
            ->fn('trim')
            ->required("Please specify status name.");

        $this->define_column('color', 'Color')
            ->invisible()
            ->validation()
            ->required("Please select status color.");

        $this->define_multi_relation_column(
            'transitions',
            'outcoming_transitions',
            'Transitions',
            "CONCAT((SELECT name
                            FROM shop_order_statuses
                            WHERE shop_order_statuses.id = shop_status_transitions.to_state_id),
                        ' (',
                        (SELECT name
                            FROM shop_roles
                            WHERE shop_roles.id = shop_status_transitions.role_id),
                        ')')"
        );

        $this->define_column('notify_customer', 'Notify Customer')
            ->validation();

        $this->define_column('notify_attach_document', 'Attach Order Document');

        $this->define_column('notify_recipient', 'Notify Transition Recipients')
            ->validation();

        $this->define_column('update_stock', 'Update Stock')
            ->validation();

        $this->define_column('requires_payment_transaction_refunds', 'Requires Payment Refunds')
            ->validation();

        $this->define_column('order_lock_action', 'Order Lock Action')
            ->validation();

        $this->define_relation_column(
            'customer_message_template',
            'customer_message_template',
            'Customer Message Template',
            db_varchar,
            '@code'
        )
            ->validation()
            ->method('validate_message_template');

        $this->define_relation_column(
            'system_message_template',
            'system_message_template',
            'System Message Template',
            db_varchar,
            '@code'
        )
            ->validation()
            ->method('validate_system_message_template');

        $this->define_column('code', 'API Code')
            ->validation()
            ->fn('trim')
            ->unique('The code "%s" already in use.');

        $front_end = ActiveRecord::$execution_context == 'front-end';
        if (!$front_end) {
            $this->define_multi_relation_column(
                'notifications',
                'notifications',
                'Notify User Roles',
                '@name'
            )
                ->defaultInvisible();
        }

        $this->defined_column_list = array();
        Backend::$events->fireEvent('shop:onExtendOrderStatusModel', $this, $context);
        $this->api_added_columns = array_keys($this->defined_column_list);
    }

    public function define_form_fields($context = null)
    {
        $this->add_form_field('name')
            ->tab('Order Status');

        $this->add_form_field('color')
            ->tab('Order Status')
            ->renderAs('state_colors')
            ->comment('Color for indicating the status in the order list.', 'above');

        if ($this->code != self::status_new && $this->code != self::status_paid) {
            $this->add_form_field('code')
                ->tab('Order Status')
                ->comment('You can use the API code for identifying the status in API calls.', 'above');
        }


        $this->add_form_field('update_stock')
            ->tab('Actions')
            ->comment('Update stock values when an order enters this status.', 'above');

        $this->add_form_field('order_lock_action')
            ->renderAs(frm_dropdown)
            ->emptyOption('no action')
            ->tab('Actions')
            ->comment('Order transitions can be set to lock or unlock the order from being edited', 'above');


        $this->add_form_field('requires_payment_transaction_refunds')
            ->tab('Requirements')
            ->comment(
                'When an order has payment transaction records, ticking this box will require those 
                transactions to be refunded before allowing this status to be applied.',
                'above'
            );


        $this->add_form_field('transitions')
            ->tab('Transitions')
            ->renderAs('status_transitions')
            ->comment(
                'A list of order statuses an order can be transferred from 
                this status and user roles responsible for transitions.',
                'above'
            )
            ->referenceSort('id');
        
        $this->add_form_field('notify_customer', 'left')
            ->tab('Notifications')
            ->comment('Notify customer when orders enter this status.');
       
        $this->add_form_field('customer_message_template')
            ->tab('Notifications')
            ->comment(
                'Please select an email message template to send to customer. To manage email templates open 
                <a target="_blank" href="' . url('/system/email_templates') . '">Email Templates</a> page.',
                'above',
                true
            )
            ->renderAs(frm_dropdown)
            ->emptyOption('<please select template>')
            ->cssClassName('checkbox_align');
        
        $this->add_form_field('notify_attach_document')
            ->renderAs(frm_dropdown)
            ->tab('Notifications')->comment('Adds a PDF copy of the order document to the email notification.', 'above')
            ->cssClassName('checkbox_align');

        $this->add_form_field('notify_recipient')
            ->tab('Notifications')
            ->comment(
                'Notify users responsible for processing orders with this status when an order enters this status.'
            );
        
        $this->add_form_field('notifications')
            ->tab('Notifications')
            ->comment(
                'Alternatively you can select user roles which should receive a 
                notification when orders enter this status.',
                'above'
            );
        
        $this->add_form_field('system_message_template')
            ->tab('Notifications')
            ->comment(
                'Please select an email message template to send to users. To manage email templates open 
                <a target="_blank" href="' . url('/system/email_templates') . '">Email Templates</a> page. 
                The notification is sent only if the Notify Transition Recipients option is enabled or a user role 
                is selected in the Notify User Roles list above.',
                'above',
                true
            )
            ->renderAs(frm_dropdown)
            ->emptyOption('<please select template>');

        Backend::$events->fireEvent('shop:onExtendOrderStatusForm', $this, $context);
        foreach ($this->api_added_columns as $column_name) {
            $form_field = $this->find_form_field($column_name);
            if ($form_field) {
                $form_field->optionsMethod('get_added_field_options');
                $form_field->optionStateMethod('get_added_field_option_state');
            }
        }
    }

    public function get_added_field_options($db_name, $current_key_value = -1)
    {
        $result = Backend::$events->fireEvent('shop:onGetOrderStatusFieldOptions', $db_name, $current_key_value);
        foreach ($result as $options) {
            if (is_array($options) || (strlen($options && $current_key_value != -1))) {
                return $options;
            }
        }

        return false;
    }

    public function get_added_field_option_state($db_name, $key_value)
    {
        $result = Backend::$events->fireEvent('shop:onGetOrderStatusFieldState', $db_name, $key_value, $this);
        foreach ($result as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return false;
    }

    public function get_notify_attach_document_options($key_value = -1)
    {
        $options = array(
            null => 'No',
            'invoice' => 'Invoice',
        );
        $supported_variants = OrderDocsHelper::getVariants();
        if ($supported_variants) {
            foreach ($supported_variants as $variant => $variantInfo) {
                $title = isset($variantInfo['title']) ? $variantInfo['title'] : $variant;
                $options[$variant] = $title;
            }
        }

        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            return $options[$key_value] ? $options[$key_value] : null;
        }

        return $options;
    }

    public function get_order_lock_action_options($key_value = -1)
    {
        $options = array(
            1 => 'Lock',
            2 => 'Unlock',
        );
        if ($key_value != -1) {
            if (!strlen($key_value)) {
                return null;
            }

            return $options[$key_value] ? $options[$key_value] : null;
        }

        return $options;
    }

    public function unlocks_order()
    {
        if ($this->order_lock_action == 2) {
            return true;
        }
        return false;
    }

    public function locks_order()
    {
        if ($this->order_lock_action == 1) {
            return true;
        }
        return false;
    }

    public function accepts_order($order, $throw_exception = false)
    {
        if ($this->requires_payment_transaction_refunds && $order->transaction_operations_allowed()) {
            $transaction_paid = PaymentTransaction::get_order_balance($order);
            if ($transaction_paid !== null && $transaction_paid > 0) {
                if ($throw_exception) {
                    throw new ApplicationException(
                        'Payment transactions on this order must be refunded before progressing to this status.'
                    );
                }
                return false;
            }
        }

        return true;
    }

    public function before_delete($id = null)
    {
        if ($this->code == self::status_new || $this->code == self::status_paid) {
            throw new ApplicationException("Statuses New and Paid cannot be deleted.");
        }

        $bind = array('id' => $this->id);
        $in_use = DbHelper::scalar('select count(*) from shop_orders where status_id=:id', $bind);
        if ($in_use) {
            throw new ApplicationException("Cannot delete status because it is in use.");
        }

        PaymentMethod::order_status_deletion_check($this);
    }

    public function after_delete()
    {
        $transitions = StatusTransition::create()->where('to_state_id=?', $this->id)->find_all();
        foreach ($transitions as $transition) {
            $transition->delete();
        }

        $transitions = StatusTransition::create()->where('from_state_id=?', $this->id)->find_all();
        foreach ($transitions as $transition) {
            $transition->delete();
        }
    }

    public static function get_status_new()
    {
        return self::create()->find_by_code(self::status_new);
    }

    public static function get_status_paid()
    {
        return self::create()->find_by_code(self::status_paid);
    }

    public function validate_message_template($name, $value)
    {
        if (!$value && $this->notify_customer) {
            $this->validation->setError('Please select email message template', $name, true);
        }

        return true;
    }

    public function validate_system_message_template($name, $value)
    {
        if (!$value && $this->notify_recipient) {
            $this->validation->setError('Please select email message template', $name, true);
        }

        return true;
    }

    public function send_notifications($order, $comment)
    {
        /*
             * Check whether the New Order Notification is allowed
         */

        $notification_allowed = true;

        if ($this->code == OrderStatus::status_new) {
            $payment_method = PaymentMethod::create()->find($order->payment_method_id);
            if ($payment_method) {
                $payment_method->define_form_fields();
                $notification_allowed = $payment_method->get_paymenttype_object()->allow_new_order_notification(
                    $payment_method,
                    $order
                );
            }
        }

        if (!$notification_allowed) {
            return;
        }

        /*
             * Send notifications to the application users
         */
        if ($this->notify_recipient || $this->notifications->count()) {
            $this->sendUserNotification($order, $comment);
        }

        /*
             * Send notification to customer
         */
        if ($this->notify_customer) {
            $this->sendCustomerNotification($order, $comment);
        }
    }

    protected function sendUserNotification($order, $comment = null)
    {
        $template = $this->system_message_template;
        if (!$template) {
            return false;
        }

        $notify_transition_roles = $this->notify_recipient;
        $notify_selected_roles = $this->notifications->count();
        if (!$notify_transition_roles && !$notify_selected_roles) {
            return false;
        }

        $role_ids = array();
        $users_to_notify = null;

        if ($notify_selected_roles) {
            $role_ids = array_merge($role_ids, $this->notifications->as_array('id'));
        }
        if ($notify_transition_roles) {
            $transition_role_ids = DbHelper::scalarArray(
                'SELECT role_id FROM shop_status_transitions WHERE from_state_id = ?',
                $this->id
            );
            if ($transition_role_ids) {
                $role_ids = array_merge($role_ids, $transition_role_ids);
            }
        }

        if (count($role_ids)) {
            $users = User::create()->where('(users.status is null OR users.status = 0)');
            $users->where('users.shop_role_id IN (?)', array($role_ids));
            $users = $users->find_all();
            $users_to_notify = $users->as_array(null, 'email');
        }

        if ($users_to_notify) {
            if ($this->code == OrderStatus::status_new) {
                $order = Order::create()->find($order->id);
            }

            $result = Backend::$events->fireEvent('shop:onBeforeOrderInternalStatusMessageSent', $order, $this);
            foreach ($result as $value) {
                if ($value === false) {
                    return false; //abort sendUserNotification
                }
            }
            $order->send_team_notifications(
                $template,
                new DataCollection($users_to_notify),
                $comment,
                array('prev_status' => $this)
            );
        }

        return true;
    }

    protected function sendCustomerNotification($order, $comment = null)
    {
        $template = $this->customer_message_template;
        if ($template) {
            try {
                $results = Backend::$events->fireEvent('shop:onBeforeOrderCustomerStatusMessageSent', $order, $this);
                foreach ($results as $value) {
                    if ($value === false) {
                        return false; //abort sendCustomerNotification
                    }
                }

                $attachmentIdentifier = $order->order_hash . '-oscna';
                if ($this->notify_attach_document) {
                    $template = $this->addCustomerNotificationAttachments($order, $template, $attachmentIdentifier);
                }

                $order->send_customer_notification($template, $comment, array('prev_status' => $this));

                if ($this->notify_attach_document) {
                    $this->deleteCustomerNotificationAttachments($template, $attachmentIdentifier);
                }
            } catch (\Exception $ex) {
            }
        }
    }

    /**
     * Adds order invoice to customer notification template as PDF attachment
     * @param Order $order
     * @param \System\EmailTemplate $notificationTemplate
     * @return \System\EmailTemplate The updated notification template
     */
    protected function addCustomerNotificationAttachments($order, $notificationTemplate, $identifier = null)
    {
        try {
            $companyInfo = CompanyInformation::get();
            $templateInfo = $companyInfo->get_invoice_template();
            if ($templateInfo) {
                if (OrderDocsHelper::variantExists($this->notify_attach_document)) {
                    $variant = $this->notify_attach_document;
                } else {
                    $variant = OrderDocsHelper::get_default_variant($order, $templateInfo);
                }
                $pdfOutput = OrderDocsHelper::getPdfOutput($order, $templateInfo, $variant);
                if ($pdfOutput) {
                    if (!$identifier) {
                        $identifier = $order->order_hash;
                    }
                    $filename = $variant . '_' . $identifier . '.pdf';
                    $fullPath = PATH_APP . '/temp/' . $filename;
                    $pdfFileSaved = @file_put_contents($fullPath, $pdfOutput);
                    if ($pdfFileSaved !== false) {
                        $attachmentName = $variant ? $variant . 'pdf' : 'invoice.pdf';
                        $notificationTemplate->addEmailAttachment($fullPath, $attachmentName);
                    }
                }
            }
        } catch (\Exception $e) {
            traceLog($e->getMessage());
        }

        return $notificationTemplate;
    }

    /**
     * Deletes attachments added by addCustomerNotificationAttachments() from filesystem.
     * @param \System\EmailTemplate $notificationTemplate
     * @param string A string identifier present in filepath or filename.
     * @return void
     */
    protected function deleteCustomerNotificationAttachments($notificationTemplate, $identifier)
    {
        try {
            $attachments = $notificationTemplate->getEmailAttachments();
            if ($attachments) {
                foreach ($attachments as $attachmentPath => $attachmentFileName) {
                    if (stristr($attachmentPath, $identifier) && is_file($attachmentPath)) {
                        @unlink($attachmentPath);
                    }
                }
            }
        } catch (\Exception $e) {
            traceLog($e->getMessage());
        }
    }


    public static function list_all_statuses()
    {
        $result = self::create();
        return $result->order('name asc')->find_all();
    }

    /**
     * Returns a status object by its API code.
     * @documentable
     * @param string $code Specifies the status API code.
     * @return OrderStatus Returns the order status object. Returns NULL if the status is not found.
     */
    public static function get_by_code($code)
    {
        if (array_key_exists($code, self::$code_cache)) {
            return self::$code_cache[$code];
        }

        $status = OrderStatus::create()->find_by_code($code);

        return self::$code_cache[$code] = $status;
    }

    /**
     * Checks if an order meets the status requirements
     * @documentable
     * @param string $status_id Specifies the status ID.
     * @param Order $order Specifies the status ID.
     * @return mixed True if accepted, Exception if not accepted
     */
    public static function order_meets_status_requirements($status_id, $order)
    {
        $status = OrderStatus::create()->find_proxy($status_id);
        return $status->accepts_order($order, true);
    }

    /*
         * Event descriptions
     */

    /**
     * Allows to define new columns in the order status model.
     * The event handler should accept two parameters - the order status object and the form execution context string
     * To add new columns to the order status model, call the {@link ActiveRecord::define_column() define_column()}
     * method of the status object. Before you add new columns to the model, you should add them to the
     * database (the <em>shop_order_statuses</em> table).
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderStatusModel', $this, 'extend_order_status_model');
     *   Backend::$events->addEvent('shop:onExtendOrderStatusForm', $this, 'extend_order_status_form');
     * }
     *
     * public function extend_order_status_model($status, $context)
     * {
     *   $status->define_column('x_custom_column', 'Custom column')->invisible();
     * }
     *
     * public function extend_order_status_form($status, $context)
     * {
     *   $status->add_form_field('x_custom_column')->tab('Order Status');
     * }
     * </pre>
     * @event shop:onExtendOrderStatusModel
     * @param OrderStatus $status Specifies the order status object.
     * @param string $context Specifies the execution context.
     * @see shop:onExtendOrderStatusForm
     * @see shop:onGetOrderStatusFieldOptions
     * @see shop:onGetOrderStatusFieldState
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onExtendOrderStatusModel($status, $context)
    {
    }

    /**
     * Allows to add new fields to the Create/Edit Order Status form in the Administration Area.
     * Usually this event is used together with the {@link shop:onExtendOrderStatusModel} event.
     * To add new fields to the status form, call the
     * {@link ActiveRecord::add_form_field() add_form_field()} method of the
     * status object.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderStatusModel', $this, 'extend_order_status_model');
     *   Backend::$events->addEvent('shop:onExtendOrderStatusForm', $this, 'extend_order_status_form');
     * }
     *
     * public function extend_order_status_model($status, $context)
     * {
     *   $status->define_column('x_custom_column', 'Custom column')->invisible();
     * }
     *
     * public function extend_order_status_form($status, $context)
     * {
     *   $status->add_form_field('x_custom_column')->tab('Order Status');
     * }
     * </pre>
     * @event shop:onExtendOrderStatusForm
     * @param OrderStatus $status Specifies the order status object.
     * @param string $context Specifies the execution context.
     * @see shop:onExtendOrderStatusModel
     * @see shop:onGetOrderStatusFieldOptions
     * @see shop:onGetOrderStatusFieldState
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     * @package shop.events
     * @author LSAPP - MJMAN
     */
    private function event_onExtendOrderStatusForm($status, $context)
    {
    }

    /**
     * Allows to populate drop-down, radio- or checkbox list fields, which have been added with
     * {@link shop:onExtendOrderStatusForm} event.
     * Usually you do not need to use this event for fields which represent
     * {@link https://lsdomainexpired.mjman.net/docs/extending_models_with_related_columns data relations}.
     * But if you want a standard field (corresponding an integer-typed database column, for example),
     * to be rendered as a drop-down list, you should handle this event.
     *
     * The event handler should accept 2 parameters - the field name and a current field value. If the current
     * field value is -1, the handler should return an array containing a list of options. If the current
     * field value is not -1, the handler should return a string (label), corresponding the value.
     * <pre>
     * public function subscribeEvents()
     * {
     *   Backend::$events->addEvent('shop:onExtendOrderStatusModel', $this, 'extend_order_status_model');
     *   Backend::$events->addEvent('shop:onExtendOrderStatusForm', $this, 'extend_order_status_form');
     *   Backend::$events->addEvent('shop:onGetOrderStatusFieldOptions', $this, 'get_order_status_field_options');
     * }
     *
     * public function extend_order_status_model($status, $context)
     * {
     *   $status->define_column('x_custom_column', 'Custom column')->invisible();
     * }
     *
     * public function extend_order_status_form($status, $context)
     * {
     *   $status->add_form_field('x_custom_column')->tab('Order Status')->renderAs(frm_dropdown);
     * }
     *
     * public function get_order_status_field_options($field_name, $current_key_value)
     * {
     *   if ($field_name == 'x_custom_column')
     *   {
     *     $options = array(
     *       0 => 'Option 1',
     *       1 => 'Option 2'
     *     );
     *
     *     if ($current_key_value == -1)
     *       return $options;
     *
     *     if (array_key_exists($current_key_value, $options))
     *       return $options[$current_key_value];
     *   }
     * }
     * </pre>
     * @event shop:onGetOrderStatusFieldOptions
     * @param string $db_name Specifies the field name.
     * @param string $field_value Specifies the field value.
     * @return mixed Returns a list of options or a specific option label.
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onExtendOrderStatusModel
     * @see shop:onExtendOrderStatusForm
     * @see shop:onGetOrderStatusFieldState
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     */
    private function event_onGetOrderStatusFieldOptions($db_name, $field_value)
    {
    }

    /**
     * Determines whether a custom radio button or checkbox list option is checked.
     * This event should be handled if you added custom radio-button and or
     * checkbox list fields with {@link shop:onExtendOrderStatusForm} event.
     * @event shop:onGetOrderStatusFieldState
     * @param string $db_name Specifies the field name.
     * @param string $field_value Specifies the field value.
     * @param OrderStatus $status Specifies the order status object.
     * @return bool Returns TRUE if the field is checked. Returns FALSE otherwise.
     * @package shop.events
     * @author LSAPP - MJMAN
     * @see shop:onExtendOrderStatusModel
     * @see shop:onExtendOrderStatusForm
     * @see shop:onGetOrderStatusFieldOptions
     * @see https://lsdomainexpired.mjman.net/docs/extending_existing_models Extending existing models
     * @see https://lsdomainexpired.mjman.net/docs/creating_and_updating_database_tables
     *  Creating and updating database tables
     */
    private function event_onGetOrderStatusFieldState($db_name, $field_value, $status)
    {
    }

    /**
     * Allows to cancel the internal user notification when an order changes its status.
     * The handler should return FALSE value if the notification should not be sent.
     * @event shop:onBeforeOrderInternalStatusMessageSent
     * @param Order $order Specifies the order object.
     * @param OrderStatus $status Specifies the order status object.
     * @return bool Returns FALSE if the internal notification should be stopped.
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onBeforeOrderInternalStatusMessageSent($order, $status)
    {
    }

    /**
     * Allows to cancel the customer notification when an order changes its status.
     * The handler should return FALSE if the notification should not be sent.
     * <pre>
     * public function subscribeEvents() {
     *   Backend::$events->addEvent(
     *      'shop:onBeforeOrderCustomerStatusMessageSent',
     *      $this,
     *      'onBeforeOrderCustomerStatusMessageSent'
     *   );
     * }
     * public function onBeforeOrderCustomerStatusMessageSent($order, $status)
     * {
     *   //skip sending customer order status change notifications for free orders
     *   if($order->total == 0)
     *     return false;
     * }
     * </pre>
     * @event shop:onBeforeOrderCustomerStatusMessageSent
     * @param Order $order Specifies the order object.
     * @param OrderStatus $status Specifies the order status object.
     * @return bool Returns FALSE if the customer notification should be stopped.
     * @author LSAPP - MJMAN
     * @package shop.events
     */
    private function event_onBeforeOrderCustomerStatusMessageSent($order, $status)
    {
    }
}
