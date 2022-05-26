<?php
require_once(PATH_APP . '/modules/shop/vendor/autoload.php');
Phpr::$classLoader->add_module_directory('shipping_types');
Phpr::$classLoader->add_module_directory('payment_types');
Phpr::$classLoader->add_module_directory('currency_converters');
Phpr::$classLoader->add_module_directory('price_rule_actions');
Phpr::$classLoader->add_module_directory('price_rule_conditions');
Phpr::$classLoader->add_module_directory('price_rule_conditions/condition_base');
