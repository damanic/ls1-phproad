insert into shop_tax_classes(name, description, code) values ('Shipping', 'Use this tax class to apply tax rules to shipping services.', 'shipping');

insert into shop_tax_classes(name, description) values ('Product', 'Default product tax class.');
insert into shop_tax_classes(name, description) values ('Downloadable product', 'Default downloadable product tax class.');

update shop_tax_classes set rates='a:1:{i:0;a:8:{s:7:"country";s:1:"*";s:5:"state";s:1:"*";s:3:"zip";s:1:"*";s:4:"city";s:1:"*";s:4:"rate";s:1:"0";s:8:"priority";s:1:"1";s:8:"tax_name";s:0:"";s:8:"compound";s:0:"";}}';

insert into shop_order_statuses(code, name, color) values ('new', 'New', '#0099cc');
insert into shop_order_statuses(code, name, color) values ('paid', 'Paid', '#9acd32');

insert into system_email_templates(code, subject, content, description) values('shop:password_reset', 'New password', '<p>Dear {customer_name}!</p><p>Here is your new password for accessing our store: {customer_password}</p>', 'Message to send to a customer after successful password reset.');
insert into system_email_templates(code, subject, content, description) values('shop:order_thankyou', 'Thank you for the order!', '<p>Dear {customer_name}!</p><p>Thank you for the order. Your order details:</p><p>Number: {order_id}<br />Date: {order_date}<br />Total: {order_total}</p><p>Order items:</p><p>{order_content}</p>', 'This order is sent to customers on new order.');

insert into shop_product_types(id, name, shipping, files, inventory, is_default) values (1, 'Goods', 1, 0, 1, 1);
insert into shop_product_types(id, name, shipping, files, inventory) values (2, 'Downloadable', 0, 1, 0);
insert into shop_product_types(id, name, shipping, files, inventory) values (3, 'Service', 0, 0, 0);
update shop_order_statuses set customer_message_template_id=(select id from system_email_templates where code='shop:order_thankyou') where code='new';


insert into shop_roles(name, description, can_create_orders, notified_on_out_of_stock) values ('Default', 'Default general role.', 1, 1);

insert into shop_status_transitions(from_state_id, to_state_id, role_id) values (
                                                                                 (select id from shop_order_statuses where code='new'),
                                                                                 (select id from shop_order_statuses where code='paid'),
                                                                                 1
                                                                                 );

insert into shop_order_deleted_status(name, code) values ('Active', 'active');
insert into shop_order_deleted_status(name, code) values ('Deleted', 'deleted');

#SEED COUNTRIES
#SEED STATES

insert into shop_currency_converter_params(class_name, refresh_interval) values ('Shop_Ecb_Converter', 24);

insert into shop_customer_groups(code, name) values ('guest', 'Guest');
insert into shop_customer_groups(code, name) values ('registered', 'Registered');
insert into shop_customer_groups(name) values ('Wholesale');
insert into shop_customer_groups(name) values ('Retail');

