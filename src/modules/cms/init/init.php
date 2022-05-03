<?php
/**
 * INIT
 * Can be used to add autoloaders and includes on boot
 */
require_once(PATH_APP . '/modules/cms/vendor/autoload.php');


//
//Allow direct access to frontend resource combiner
//
if (Phpr::$request->getValueArray('q', false)) {
    $combine_access_points = array(
        'cms_js_combine',
        'cms_css_combine',
    );
    foreach ($combine_access_points as $ap) {
        if (strpos(Phpr::$request->getValueArray('q'), $ap.'/') !== false) {
            include(PATH_APP."/modules/cms/system/combine_resources.php");
            die();
        }
    }
}
