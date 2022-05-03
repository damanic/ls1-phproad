<?php
namespace Cms;

use Phpr;
use Backend;
use MatthiasMullie\Minify\CSS as MinifyCss;
use MatthiasMullie\Minify\JS as MinifyJs;

/*
 * Frontend resource combiner (JS/CSS)
 * Called via direct http request to yoursite.com/cms_js_combine
 * see: CMS_Controller::js_combine()
 * Called via direct http request to yoursite.com/cms_css_combine
 * see: CMS_Controller::css_combine()
 *
 * This script should be able to load minifier dependencies independently within the CMS module.
 * The bulk of this script could also be wrapped into helper class Cms_ResourceCombine
 */


$urlEncodedFiles = Phpr::$request->getValueArray('f', false); //already url decoded
if (!$urlEncodedFiles) {
    die();
}
$files = ResourceCombine::decode_param($urlEncodedFiles, $url_encoded = false);
if (!$files) {
    die();
}

$aliases = array(
    'mootools'=>'/modules/cms/resources/javascript/mootools_src.js',
    'ls_core_mootools'=>'/modules/cms/resources/javascript/ls_mootools_core_src.js',
    'ls_core_jquery'=>'/modules/cms/resources/javascript/ls_jquery_core_src.js',
    'jquery'=>'/modules/cms/resources/javascript/jquery_src.js',
    'jquery_noconflict'=>'/modules/cms/resources/javascript/jquery_noconflict.js',
    'ls_styles'=>'/modules/cms/resources/css/frontend_css.css',
    'frontend_mootools'=>'/modules/cms/resources/javascript/ls_mootools_core_src.js',
    'frontend_jquery'=>'/modules/cms/resources/javascript/ls_jquery_core_src.js',
    'frontend_styles'=>'/modules/cms/resources/css/frontend_css.css'
);

if (Theme::is_theming_enabled() && ($theme = Theme::get_active_theme())) {
    $current_theme = $theme;
}

$allowed_dir = $current_theme ? $theme->get_resources_path() : '/'.SettingsManager::get()->resources_dir_path;
$default_allowed_paths = array(
    PATH_APP.$allowed_dir,
    PATH_APP.'/modules/cms/resources/javascript',
    PATH_APP.'/modules/cms/resources/css/'
);
$config_allowed_paths = $CONFIG['ALLOWED_RESOURCE_PATHS'] ?? array();
$allowed_paths = array_merge($default_allowed_paths, $config_allowed_paths);

$symbolic_links = isset($CONFIG['RESOURCE_SYMLINKS']) ? $CONFIG['RESOURCE_SYMLINKS'] : array();
$enable_remote_resources = isset($CONFIG['ENABLE_REMOTE_RESOURCES']) ? $CONFIG['ENABLE_REMOTE_RESOURCES'] : false;
$minify = true;
$allowed_types  = array(
    'js',
    'css'
);


$resource_type = null;
$url_query = Phpr::$request->getValueArray('q', false);

if (preg_match(
    '#cms_(.+)_combine#simU',
    htmlentities($url_query, ENT_COMPAT, 'UTF-8'),
    $match
)) {
    // htmlentities just incase something malicious (just being safe)
    $resource_type = $match[1];
}
if (!$resource_type || !in_array($resource_type, $allowed_types)) {
    die();
}


$recache    = Phpr::$request->getValueArray('reset_cache', false);
$skip_cache = Phpr::$request->getValueArray('skip_cache', false);
$src_mode   = Phpr::$request->getValueArray('src_mode', false);

$assets = array();
$combined_files = array();

foreach ($files as $file_path) {
    $allowed = false; // is this file allowed to be an asset?

    if (array_key_exists($file_path, $aliases)) {
        $file_path = $aliases[$file_path];
    }

    $file = $orig_url = str_replace(chr(0), '', urldecode($file_path));
    $file_type = pathinfo(strtolower($file), PATHINFO_EXTENSION);
    if ($file_type !== $resource_type) {
        continue;
    }

    if (isset($combined_files[$orig_url])) {
        continue; //already included
    }

    if (!ResourceCombine::is_remote_resource($file)) {
        $file = str_replace('\\', '/', realpath(PATH_APP . $file));

        foreach ($allowed_paths as $allowed_path) {
            $allowed_path = realpath($allowed_path); //no symbolic links allowed
            if (!$allowed_path) {
                continue;
            }
            $allowed_path = str_replace('\\', '/', $allowed_path);
            $is_relative = strpos($allowed_path, '/') !== 0 && strpos($allowed_path, ':') !== 1;

            if ($is_relative) {
                //relative paths not accepted
                continue;
            }


            if (strpos($file, $allowed_path) === 0) {
                $allowed = true;
                // the file is allowed to be an asset because it matches the requirements (allowed paths)
                break;
            }
        }
    } else {
        $allowed = true; // always allow remote files
    }

    if ($allowed) {
        $combined_files[$orig_url] = 1;
        $assets[$orig_url] = $file; //approved asset
    }
}

/*
 * Check whether GZIP is supported by the browser
 */
$supportsGzip = false;
$encodings    = array();
if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    $encodings = explode(',', strtolower(preg_replace('/\s+/', '', $_SERVER['HTTP_ACCEPT_ENCODING'])));
}

if (( in_array('gzip', $encodings) || in_array('x-gzip', $encodings) || isset($_SERVER['---------------']) )
    && function_exists('ob_gzhandler')
    && !ini_get('zlib.output_compression')
) {
    $enc          = in_array('x-gzip', $encodings) ? 'x-gzip' : 'gzip';
    $supportsGzip = true;
}

/*
 * Caching
 */

$mime = 'text/' . ( $resource_type == 'js' ? 'javascript' : $resource_type );

$cache_path = PATH_APP . '/temp/resource_cache';
if (!file_exists($cache_path)) {
    mkdir($cache_path);
}

$cache_hash = sha1(implode(',', $assets));

$cache_filename = $cache_path . '/' . $cache_hash . '.' . $resource_type;
if ($supportsGzip) {
    $cache_filename .= '.gz';
}

$cache_exists = file_exists($cache_filename);

if ($recache && $cache_exists) {
    @unlink($cache_filename);
}

$assets_mod_time = 0;
foreach ($assets as $file) {
    if (!ResourceCombine::is_remote_resource($file)) {
        if (file_exists($file)) {
            $assets_mod_time = max($assets_mod_time, filemtime($file));
        }
    } else {
        /*
         * We cannot reliably check the modification time of a remote resource,
         * because time on the remote server could not exactly match the time
         * on this server.
         */

        //$assets_mod_time = 0;
    }
}

$cached_mod_time = $cache_exists ? (int) @filemtime($cache_filename) : 0;
$content = '';
$combiner = ($resource_type == 'css') ? new MinifyCss() : new MinifyJs();

if ($skip_cache || $cached_mod_time < $assets_mod_time || !$cache_exists) {
    foreach ($assets as $orig_url => $file) {
        $is_remote = ResourceCombine::is_remote_resource($file);

        if ($is_remote && !$enable_remote_resources) {
            continue;
        }

        if ($is_remote) {
            $data =  @file_get_contents($file) . "\r\n";
            if ($data) {
                $combiner->add($data);
            }
        } else {
            $combiner->add($file);
        }
    }

    if ($supportsGzip) {
        $content = $combiner->gzip();
    } else {
        $content = $combiner->minify();
    }

    if (!$skip_cache) {
        @file_put_contents($cache_filename, $content);
    }
} elseif (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
    $assets_mod_time <= strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    header('Content-Type: ' . $mime);
    if (php_sapi_name() == 'CGI') {
        header('Status: 304 Not Modified');
    } else {
        header('HTTP/1.0 304 Not Modified');
    }

    exit();
} elseif (file_exists($cache_filename)) {
    $content = @file_get_contents($cache_filename);
}

/*
 * Output
 */

header('Content-Type: ' . $mime);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $assets_mod_time) . ' GMT');

if ($supportsGzip) {
    header('Vary: Accept-Encoding');  // Handle proxies
    header('Content-Encoding: ' . $enc);
}

echo $content;
