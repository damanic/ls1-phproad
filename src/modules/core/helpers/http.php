<?php
namespace Core;

use Net\Request;
use Phpr\SystemException;
use Phpr\ApplicationException;

/**
 * @deprecated
 */
class Http
{
    /**
     * @deprecated
     * Use Net\Request
     */
    public static function post_data($endpoint, $fields = array(), $ssl = true)
    {
        $slash_pos = strpos($endpoint, '/');
        if ($slash_pos === false) {
            $url = "/nvp";
        } else {
            $url = substr($endpoint, $slash_pos);
            $endpoint = substr($endpoint, 0, $slash_pos);
        }
        $prefix = $ssl ? 'https://' : 'http://';
        $request = Request::create($prefix.$endpoint);
        $request->disable_redirects();
        $request->set_post($fields);
        $response = $request->send();
        if ($response->status_code != 200) {
            throw new SystemException("Error number: $response->status_code, error: $response->error_info");
        }
        return $response->data;
    }

    /**
     * @deprecated
     * Use Net\Request
     */
    public static function sub_request($url, $fields, $timeout = 60)
    {
        $endpoint = root_url(Core_String::normalizeUri($url), true);
        $request = Request::create($endpoint);
        $request->set_post($fields);
        $request->set_option(CURLOPT_SSL_VERIFYHOST, false);
        $request->set_option(CURLOPT_SSL_VERIFYPEER, false);
        $request->set_timeout($timeout);
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $request->set_option(CURLOPT_COOKIE, $_SERVER['HTTP_COOKIE']);
        }
        $request->set_headers(array('LS_SUBQUERY: 1', 'LS-SUBQUERY: 1'));
        $response = $request->send();
        return $response->data;
    }

    /**
     * @deprecated
     * No need for this, use Net\Request
     */
    public static function parse_http_response($response)
    {
        $matches = array();
        preg_match('/Content\-Length:\s([0-9]+)/i', $response, $matches);
        if (!count($matches)) {
            throw new ApplicationException('Invalid response');
        }

        $elements = substr($response, $matches[1]*-1);
        $elements = explode('&', $elements);

        $result = array();
        foreach ($elements as $element) {
            $element = explode('=', $element);
            if (isset($element[0]) && isset($element[1])) {
                $result[$element[0]] = urldecode($element[1]);
            }
        }

        return $result;
    }
}
