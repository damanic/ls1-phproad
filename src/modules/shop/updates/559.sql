insert into shop_automated_billing_settings(id, billing_period, enabled) values (1, 5, 0);

alter table shop_orders add column automated_billing_fail datetime;
alter table shop_orders add column automated_billing_success datetime;

create index automated_billing_fail on shop_orders(automated_billing_fail);
create index automated_billing_success on shop_orders(automated_billing_success);

insert into system_email_templates(code, subject, content, description, is_system) values('shop:auto_billing_report', 'Automated billing report', '<p>Hi!</p><p>LSAPP finished processing the automated billing. Below is a detailed report.</p><p>{autobilling_report}</p>', 'This message is sent to administrators when LSAPP finishes processing the automated billing.', 1);