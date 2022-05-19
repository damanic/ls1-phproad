<?php
require_once(PATH_APP . '/modules/shop/vendor/autoload.php');
Phpr::$classLoader->add_module_directory('shipping_types');
Phpr::$classLoader->add_module_directory('payment_types');
