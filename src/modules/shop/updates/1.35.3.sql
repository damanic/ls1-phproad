UPDATE `shop_configuration`
	SET low_stock_alert_notification_template_id = (SELECT id FROM system_email_templates WHERE code = 'shop:low_stock_internal' ),
		out_of_stock_alert_notification_template_id = (SELECT id FROM system_email_templates WHERE code = 'shop:out_of_stock_internal' );
