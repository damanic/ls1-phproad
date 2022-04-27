NAMESPACE ISSUE

The following ROUTE is not compatible with namespaced controller classes

$route = Phpr::$router->addRule($backend_url . '/:module/:controller/:action/:param1/:param2/:param3/:param4');
$route->folder('modules/:module/controllers');
$route->def('module', 'backend');
$route->def('controller', 'index');
$route->def('action', 'index');
$route->def('param1',null);
$route->def('param2', null);
$route->def('param3', null);
$route->def('param4', null);
$route->convert('controller', '/^.*$/', ':module_$0');

