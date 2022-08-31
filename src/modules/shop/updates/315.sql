INSERT INTO `system_compound_email_vars` (`code`,`content`,`scope`,`description`) VALUES ('order_billing_address','<?= nl2br(h($order->billing_street_addr)) ?>, <?= h($order->billing_city) ?><br/>\n<? if ($order->billing_state): ?>\n  <?= h($order->billing_state->name) ?>, \n<? endif ?>\n<?= h($order->billing_zip) ?>, <?= h($order->billing_country->name) ?>','shop:order','Outputs a formatted billing address');
INSERT INTO `system_compound_email_vars` (`code`,`content`,`scope`,`description`) VALUES ('order_shipping_address','<?= nl2br(h($order->shipping_street_addr)) ?>, <?= h($order->shipping_city) ?><br/>\n<? if ($order->shipping_state): ?>\n  <?= h($order->shipping_state->name) ?>,\n<? endif ?>\n<?= h($order->shipping_zip) ?>, <?= h($order->shipping_country->name) ?>','shop:order','Outputs a formatted shipping address');
INSERT INTO `system_compound_email_vars` (`code`,`content`,`scope`,`description`) VALUES ('order_content','<? $include_tax = Shop_CheckoutData::display_prices_incl_tax($order); ?>\n<table class=\"simpleList\" style=\"font: normal 11px/150% Arial, Verdana, sans-serif; width: 100%\">\n  <thead style=\"text-align: left; background: #e5e5e5\">\n    <tr>\n      <th>Item</th>\n      <th>SKU</th>\n      <th class=\"number\">Price</th>\n      <th class=\"number\">Discount</th>\n      <th class=\"number\">Quantity</th>\n      <th class=\"number last\">Total</th>\n    </tr>\n  </thead>\n  <tbody style=\"text-align: left; background: white\">\n    <?          \n      $last_index = $items->count-1;\n      foreach ($items as $index=>$item):\n    ?>\n    <tr>\n      <td>\n        <div <? if ($item->bundle_master_order_item_id): ?>style=\"padding-left: 30px\"<? endif ?>>\n          <?= $item->output_product_name() ?>\n        </div>\n      </td>\n      <td><?= $item->product_sku ? h($item->product_sku) : h(\'<product not found>\') ?></td>\n      <td class=\"number\"><?= $include_tax ? format_currency($item->price_tax_included) : format_currency($item->single_price) ?></td>\n      <td class=\"number\"><?= $include_tax ? format_currency($item->discount_tax_included) : format_currency($item->discount) ?></td>\n      <td class=\"number\"><?= h($item->get_bundle_item_quantity()) ?></td>\n      <td class=\"number last\">\n        <?           \n          $master_item = $item->get_master_bundle_order_item();\n          $multiplier = ($master_item && $master_item->quantity > 1) ? \' x \'.$master_item->quantity : null;\n        ?>\n        <?= $include_tax ? format_currency($item->bundle_item_total_tax_incl).$multiplier : format_currency($item->bundle_item_total).$multiplier ?>\n      </td>\n    </tr>\n    \n    <? if (($item->bundle_master_order_item_id && $index == $last_index) || ($item->bundle_master_order_item_id && !$items[$index+1]->bundle_master_order_item_id)): \n      $master_item = $item->get_master_bundle_order_item();\n      if ($master_item):\n    ?>\n      <tr style=\"background: #eee\">\n        <td colspan=\"2\"><strong><?= h($master_item->product->name) ?> bundle totals</strong></td>\n        <td class=\"number\"><strong><?= format_currency($master_item->get_bundle_single_price()) ?></strong></td>\n        <td class=\"number\"><strong><?= format_currency($master_item->get_bundle_discount()) ?></strong></td>\n        <td class=\"number\"><strong><?= $master_item->quantity ?></strong></td>\n        <td class=\"number last\"><strong><?= format_currency($master_item->get_bundle_total_price()) ?></strong></td>\n      </tr>\n    <? \n        endif;\n      endif ?>\n    <?\n      endforeach;\n    ?>\n  </tbody>\n</table>','shop:order','Outputs order items table');
INSERT INTO `system_email_templates` (`code`,`subject`,`content`,`description`,`is_system`) VALUES ('shop:new_order_internal','New order: {order_id}','<p>This message is to inform you about new order order #{order_id}</p>\n<p><strong>Order details</strong></p>\n<p>Order <strong>#{order_id}</strong>, created on <strong>{order_date}</strong><br /> Subtotal: <strong>{order_subtotal}</strong><br /> Shipping: <strong>{order_shipping_quote}</strong><br /> Tax: <strong>{order_tax}</strong><br /> Discount total: <strong>{cart_discount}</strong><br /> Total: <strong>{order_total}</strong></p>\n<p>Coupon: {order_coupon}</p>\n<p>Customer notes:</p>\n<blockquote>{customer_notes}</blockquote>\n<p><strong>Order items</strong></p>\n<p>{order_content}</p>\n<p><strong>Customer details</strong></p>\n<p>Billing name: {billing_customer_name}<br />Billing address: {order_billing_address}</p>\n<p>Shipping name: {shipping_customer_name}<br />Shipping address: {order_shipping_address}</p>','This message is sent to the store team members on new order','1');
INSERT INTO `system_email_templates` (`code`,`subject`,`content`,`description`,`is_system`) VALUES ('shop:order_status_update_internal','Order status changed: {order_id}','<p>This message is to inform you that order #{order_id}&nbsp; has changed its status from {order_previous_status} to {order_status_name}. Status transition comment: {order_status_comment}</p>\n<p><strong>Order details</strong></p>\n<p>Order <strong>#{order_id}</strong>, created on <strong>{order_date}</strong><br /> Subtotal: <strong>{order_subtotal}</strong><br /> Shipping: <strong>{order_shipping_quote}</strong><br /> Tax: <strong>{order_tax}</strong><br /> Discount total: <strong>{cart_discount}</strong><br /> Total: <strong>{order_total}</strong></p>\n<p>Coupon: {order_coupon}</p>\n<p>Customer notes:</p>\n<blockquote>{customer_notes}</blockquote>\n<p><strong>Order items</strong></p>\n<p>{order_content}</p>\n<p><strong>Customer details</strong></p>\n<p>Billing name: {billing_customer_name}<br />Billing address: {order_billing_address}</p>\n<p>Shipping name: {shipping_customer_name}<br />Shipping address: {order_shipping_address}</p>','This message is sent to the store team members when an order changes its status','1');