<?php
namespace Phpr;

use Phpr;
use Phpr\Strings;

/**
 * Encapsulates information about the HTTP request.
 * An instance of this class is always available through the <em>$Phpr</em>
 * class and you never need to create it manually:
 * <pre>$ip = Phpr::$request->getUserIp();</pre>
 * Use this class for reading GET or POST values from the request, loading cookie information,
 * obtaining the visitor's IP address, etc.
 *
 */
class Request
{
    private $ip = null;
    private $ip_strict = null;
    private $language = null;
    private $cachedEvendParams = null;
    private $cachedUri = null;
    private $subdirectory = null;
    private $cachedRootUrl = null;

    protected $remoteEventIndicator = 'HTTP_PHPR_REMOTE_EVENT';
    protected $postbackIndicator = 'HTTP_PHPR_POSTBACK';

    public $getFields = null;

    /**
     * Creates a new Phpr_Request instance.
     * Do not create the Request objects directly. Use the Phpr::$request object instead.
     *
     * @see Phpr
     */
    public function __construct()
    {
        $this->preprocessGlobals();
    }


    /**
     * Returns a cookie value by the cookie name.
     * If a cookie with the specified name does not exist, returns NULL.
     *
     * @documentable
     * @param        string $name Specifies the cookie name.
     * @return       mixed Returns either cookie value or NULL.
     */
    public function cookie($Name)
    {
        if (!isset($_COOKIE[$Name])) {
            return null;
        }

        return $_COOKIE[$Name];
    }

    /**
     * Returns a value of the SERVER variable.
     * @param string $name Specifies a variable name.
     * @param string $default Default value if specified name does not exist.
     * @return mixed
     */
    public function server($name = null, $default = null)
    {
        if ($name === null) {
            return $_SERVER;
        }

        return (!isset($_SERVER[$name])) ? $_SERVER[$name] : $default;
    }

    /**
     * Returns a value of the ENV variable.
     * @param string $name Specifies a variable name.
     * @param string $default Default value if specified name does not exist.
     * @return mixed
     */
    public function env($name = null, $default = null)
    {
        if ($name === null) {
            return $_ENV;
        }

        return (!isset($_ENV[$name])) ? $_ENV[$name] : $default;
    }

    /**
     * Returns a named POST parameter value.
     * If a parameter with the specified name does not exist in POST, returns <em>NULL</em> or a value
     * specified in the $default parameter.
     *
     * @documentable
     * @param        string $name    Specifies the parameter name.
     * @param        mixed  $default Specifies a default value.
     * @return       mixed Returns the POST parameter value, NULL or default value.
     * @see          post() post() function
     */
    public function postField($Name, $Default = null)
    {
        if (array_key_exists($Name . '_x', $_POST) && array_key_exists($Name . '_y', $_POST)) {
            return true;
        }

        if (!array_key_exists($Name, $_POST)) {
            return $Default;
        }

        return $_POST[$Name];
    }

    /**
     * Finds an array in the <em>POST</em> data then finds and returns an element inside this array.
     * If the array or the element do not exist, returns null or a value specified in the $default parameter.
     *
     * This method is useful for extracting form field values if you use array notation for the form input element names.
     * For example, if you have a form with the following fields
     * <pre>
     * <input type="text" name="customer_form[first_name]">
     * <input type="text" name="customer_form[last_name]">
     * </pre>
     * you can extract the first name field value with the following code:
     * <pre>$first_name = Phpr::$request->postArray('customer_form', 'first_name')</pre>
     *
     * @documentable
     * @param        string $array_name specifies the array element name in the POST data.
     * @param        string $name       specifies the array element key in the first array.
     * @param        mixed  $default    specifies a default value.
     * @return       mixed returns the found array element value or the default value.
     * @see          postArray() postArray function
     */
    public function postArray($ArrayName, $Name, $Default = null)
    {
        if (!array_key_exists($ArrayName, $_POST)) {
            return $Default;
        }

        if (!array_key_exists($Name, $_POST[$ArrayName])) {
            return $Default;
        }

        return $_POST[$ArrayName][$Name];
    }

    /**
     * Returns if the HTTP request was an AJAX request.
     * @return boolean
     */
    public function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
                $_SERVER['HTTP_X_REQUESTED_WITH']
            ) == 'xmlhttprequest';
    }

    /**
     * Returns a name of the User Agent.
     * If user agent data is not available, returns NULL.
     *
     * @documentable
     * @return       mixed Returns the user agent name or NULL.
     */
    public function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * Determines whether the remote event handling requested.
     *
     * @return boolean.
     */
    public function isRemoteEvent()
    {
        return isset($_SERVER[$this->remoteEventIndicator]);
    }

    /**
     * Returns SSL Session Id value.
     *
     * @return string.
     */
    public function getSslSessionId()
    {
        if (isset($_SERVER["SSL_SESSION_ID"])) {
            return $_SERVER["SSL_SESSION_ID"];
        }

        return null;
    }

    /**
     * Determines whether the page is loaded in response to a client postback.
     *
     * @return boolean.
     */
    public function isPostBack()
    {
        return isset($_SERVER[$this->postbackIndicator]);
    }


    /**
     * Returns true if the request is from the admin area.
     * @return boolean
     */
    public function isAdmin()
    {
        $request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
        $admin_url = '/' . Strings::normalizeUri(Phpr::$config->get('ADMIN_URL', 'admin'));
        $current_url = '/' . Strings::normalizeUri(
                isset($_REQUEST[$request_param_name]) ? $_REQUEST[$request_param_name] : ''
            );

        return (stristr($current_url, $admin_url) !== false);
    }

    /**
     * Returns the visitor IP address.
     *
     * @param  bool $strict Set to true if IP check should use most reliable IP determination
     * @return string
     */
    public function getUserIp($strict = false)
    {
        $cached_ip = $strict ? $this->ip_strict : $this->ip;

        if ($cached_ip !== null) {
            return $cached_ip;
        }

        $ip = null;

        $ip_keys = array('REMOTE_ADDR');
        if (!$strict) {
            $ip_keys = Phpr::$config->get(
                'REMOTE_IP_HEADERS',
                array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR')
            );
        }

        foreach ($ip_keys as $ip_key) {
            if (isset($_SERVER[$ip_key]) && strlen($_SERVER[$ip_key])) {
                $ip = $_SERVER[$ip_key];
                break;
            }
        }

        if (strlen(strstr($ip, ','))) {
            $ips = explode(',', $ip);
            $ip = trim(reset($ips));
        }

        if ($ip == '::1') {
            $ip = '127.0.0.1';
        }

        $this->ip = $ip;
        if ($strict) {
            $this->ip_strict = $ip;
        }
        return $ip;
    }

    /**
     * Returns the visitor language preferences.
     *
     * @return string
     */
    public function getUserLanguage()
    {
        if ($this->language !== null) {
            return $this->language;
        }

        if (!array_key_exists('HTTP_ACCEPT_language', $_SERVER)) {
            return null;
        }

        $languages = explode(",", $_SERVER['HTTP_ACCEPT_language']);
        $language = $languages[0];

        if (($pos = strpos($language, ";")) !== false) {
            $language = substr($language, 0, $pos);
        }

        return $this->language = str_replace("-", "_", $language);
    }

    /**
     * Returns a subdirectory path, starting from the server
     * root directory to directory root.
     * Example. PHPR installed to the subdirectory /phpr of a domain
     * Then the method will return the '/subdirectory/' string
     */
    public function getSubdirectory()
    {
        if ($this->subdirectory !== null) {
            return $this->subdirectory;
        }

        $request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');

        $uri = $this->getRequestUri();
        $path = $this->getField($request_param_name);

        $uri = urldecode($uri);
        $uri = preg_replace('|/\?(.*)$|', '/', $uri);

        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $pos = strpos($uri, '/&');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos + 1);
        }

        $path = mb_strtolower($path);
        $uri = mb_strtolower($uri);

        $pos = mb_strrpos($uri, $path);
        $subdir = '/';
        if ($pos !== false && $pos == mb_strlen($uri) - mb_strlen($path)) {
            $subdir = mb_substr($uri, 0, $pos) . '/';
        }

        if (!strlen($subdir)) {
            $subdir = '/';
        }

        return $this->subdirectory = $subdir;
    }

    /**
     * Returns the URL of the current request
     */
    public function getRequestUri()
    {
        $provider = Phpr::$config->get("URI_PROVIDER", null);

        if ($provider !== null) {
            return getenv($provider);
        } else {
            // Pick the provider from the server variables
            //
            $providers = array('REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO');
            foreach ($providers as $provider) {
                $val = getenv($provider);
                if ($val != '') {
                    return $val;
                }
            }
        }

        return null;
    }

    /**
     * Returns the URI of the current request relative to the PHPR applications root directory.
     *
     * @param  bool $Routing Determines whether the Uri is requested for the routing process
     * @return string
     */
    public function getCurrentUri($Routing = false)
    {
        global $bootstrapPath;

        if (!$Routing && $this->cachedUri !== null) {
            return $this->cachedUri;
        }

        $request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
        $bootstrapPathBase = pathinfo($bootstrapPath, PATHINFO_BASENAME);
        $URI = $this->getField($request_param_name);

        // Postprocess the URI
        //
        if (strlen($URI)) {
            if (($pos = strpos($URI, '?')) !== false) {
                $URI = substr($URI, 0, $pos);
            }

            if ($URI[0] == '/') {
                $URI = substr($URI, 1);
            }

            $len = strlen($bootstrapPathBase);
            if (substr($URI, 0, $len) == $bootstrapPathBase) {
                $URI = substr($URI, $len);
                if ($URI[0] == '/') {
                    $URI = substr($URI, 1);
                }
            }

            $len = strlen($URI);
            if ($len > 0 && $URI[$len - 1] == '/') {
                $URI = substr($URI, 0, $len - 1);
            }
        }

        $URI = "/" . $URI;

        if ($Routing) {
            // $DocRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : null;
            // if ( strlen($DocRoot) )
            // {
            //     if ( strpos(PATH_APP, $DocRoot) == 0 && strcmp(PATH_APP, $DocRoot) != 0 )
            //     {
            //         $dirName = substr( PATH_APP, strlen($DocRoot) );
            //         if ( strlen($dirName) )
            //         {
            //             $URI = str_replace($dirName.'/', '', $URI);
            //         }
            //     }
            // }
            //
            // $URI = str_replace('test/', '', $URI);
        } else {
            $this->cachedUri = $URI;
        }

        return $URI;
    }

    /**
     * Cleans the _POST and _COOKIE data and unsets the _GET data.
     * Replaces the new line charaters with \n.
     */
    private function preprocessGlobals()
    {
        // Unset the global variables
        //
        $this->getFields = $_GET;

        $this->unsetGlobals($_GET);
        $this->unsetGlobals($_POST);
        $this->unsetGlobals($_COOKIE);

        // Clear the _GET array
        //
        $_GET = array();

        // Clean the POST and COOKIE data
        //
        $this->cleanupArray($_POST);
        $this->cleanupArray($_COOKIE);
    }

    public function getValueArray($name, $default = array())
    {
        if (array_key_exists($name, $this->getFields)) {
            return $this->getFields[$name];
        }

        if (!isset($_SERVER['QUERY_STRING'])) {
            return $default;
        }

        $vars = explode('&', $_SERVER['QUERY_STRING']);

        $result = array();
        foreach ($vars as $var_data) {
            $var_data = urldecode($var_data);

            $var_parts = explode('=', $var_data);
            if (count($var_parts) == 2) {
                if ($var_parts[0] == $name . '[]' || $var_parts[0] == $name . '%5B%5D') {
                    $result[] = $var_parts[1];
                }
            }
        }

        if (!count($result)) {
            return $default;
        }

        return $result;
    }

    public function getQueryString($include_request_name = false)
    {
        $params = $this->getFields;
        $rpn = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
        if (is_array($params)) {
            if (!$include_request_name && isset($params[$rpn])) {
                unset($params[$rpn]);
            }
        }
        return (is_array($params) && count($params)) ? http_build_query($params, '', '&') : null;
    }


    /**
     * @param  string $Name Optional name of parameter to return.
     * @return mixed
     * @ignore
     * Returns a list of the event parameters, or a specified parameter value.
     * This method is used by the PHP Road internally.
     */
    public function getEventParams($Name = null)
    {
        if ($this->cachedEvendParams == null) {
            $this->cachedEvendParams = array();

            if (isset($_POST['phpr_handler_params'])) {
                $pairs = explode('&', $_POST['phpr_handler_params']);
                foreach ($pairs as $pair) {
                    $parts = explode("=", urldecode($pair));
                    $this->cachedEvendParams[$parts[0]] = $parts[1];
                }
            }
        }

        if ($Name === null) {
            return $this->cachedEvendParams;
        }

        if (isset($this->cachedEvendParams[$Name])) {
            return $this->cachedEvendParams[$Name];
        }

        return null;
    }

    public function getReferer($Detault = null)
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            return $_SERVER['HTTP_REFERER'];
        }

        return $Detault;
    }

    /**
     * Returns the current request method name - <em>POST</em>, <em>GET</em>, <em>HEAD</em> or <em>PUT</em>.
     *
     * @documentable
     * @return       string Returns the request method name.
     */
    public function getRequestMethod()
    {
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return null;
    }

    public function getCurrentUrl()
    {
        $protocol = $this->getProtocol();
        $port = ($_SERVER["SERVER_PORT"] == "80") ? ""
            : (":" . $_SERVER["SERVER_PORT"]);

        return $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    }

    public function getHostname()
    {
        $server_name = isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : null;
        $host_name = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : null;
        return $server_name ? $server_name : $host_name;
    }

    /**
     * Returns HTTP protocol name - <em>http</em> or <em>https</em>.
     *
     * @documentable
     * @return       string Returns HTTP protocol name.
     */
    public function getProtocol()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
            $s = 's';
        } else {
            $s = (empty($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"] === 'off')) ? '' : 's';
        }

        return $this->strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/") . $s;
    }

    /**
     * Returns HTTP port number.
     * If <em>STANDARD_HTTP_PORTS</em> parameter is set to TRUE in {@link http://https://damanic.github.io/ls1-documentation/docs/lemonstand_configuration_options/ config.php file},
     * the method returns NULL.
     *
     * @documentable
     * @return       integer Returns HTTP port number.
     */
    public function getPort()
    {
        if (Phpr::$config->get('STANDARD_HTTP_PORTS')) {
            return null;
        }

        if (array_key_exists('HTTP_HOST', $_SERVER)) {
            $matches = array();
            if (preg_match('/:([0-9]+)/', $_SERVER['HTTP_HOST'], $matches)) {
                return $matches[1];
            }
        }

        return isset($_SERVER["SERVER_PORT"]) ? $_SERVER["SERVER_PORT"] : null;
    }

    public function getRootUrl($protocol = null)
    {
        if (!isset($_SERVER['SERVER_NAME'])) {
            return null;
        }

        $protocol_specified = strlen($protocol);
        if (!$protocol_specified && $this->cachedRootUrl !== null) {
            return $this->cachedRootUrl;
        }

        if ($protocol === null) {
            $protocol = $this->getProtocol();
        }

        $port = $this->getPort();

        $current_protocol = $this->getProtocol();
        if ($protocol_specified && strtolower($protocol) != $current_protocol) {
            $port = '';
        }

        $https = strtolower($protocol) == 'https';

        if (!$https && $port == 80) {
            $port = '';
        }

        if ($https && $port == 443) {
            $port = '';
        }

        $port = !strlen($port) ? "" : ":" . $port;

        $result = $protocol . "://" . $_SERVER['SERVER_NAME'] . $port;

        if (!$protocol_specified) {
            $this->cachedRootUrl = $result;
        }

        return $result;
    }

    /**
     * Returns a named GET parameter value.
     *
     * @documentable
     * If a parameter with the specified name does not exist in GET, returns <em>null</em> or a value specified in the $default parameter.
     * @documentable
     * @param        string $name    Specifies the parameter name.
     * @param        mixed  $default Specifies a default value.
     * @return       mixed Returns the GET parameter value, NULL or default value.
     * @see          Phpr_Request::post() post()
     */
    public function getField($name, $default = false)
    {
        return array_key_exists($name, $this->getFields) ? $this->getFields[$name] : $default;
    }

    private function strleft($s1, $s2)
    {
        return substr($s1, 0, strpos($s1, $s2));
    }

    /**
     * Unsets the global variables created with from the POST, GET or COOKIE data.
     *
     * @param array &$Array The array containing a list of variables to unset.
     */
    private function unsetGlobals(&$Array)
    {
        if (!is_array($Array)) {
            unset($$Array);
        } else {
            foreach ($Array as $VarName => $VarValue) {
                unset($$VarName);
            }
        }
    }

    /**
     * Check the input array key for invalid characters and adds slashes.
     *
     * @param  string $Key Specifies the key to process.
     * @return string
     */
    private function cleanupArrayKey($Key)
    {
        if (!preg_match("/^[0-9a-z:_\/-\{\}|]+$/i", $Key)) {
            return null;
            //                throw new Phpr_SystemException( "Invalid characters in the input data key: $Key" );
        }

        return addslashes($Key);
    }

    /**
     * Fixes the new line characters in the specified value.
     *
     * @param mixed $Value Specifies a value to process.
     *                     return mixed
     */
    private function cleanupArrayValue($Value)
    {
        if (!is_array($Value)) {
            return preg_replace("/\015\012|\015|\012/", "\n", $Value);
        }

        $Result = array();
        foreach ($Value as $VarName => $VarValue) {
            $Result[$VarName] = $this->cleanupArrayValue($VarValue);
        }

        return $Result;
    }

    /**
     * Cleans the unput array keys and values.
     *
     * @param array &$Array Specifies an array to clean.
     */
    private function cleanupArray(&$Array)
    {
        if (!is_array($Array)) {
            return;
        }

        foreach ($Array as $VarName => &$VarValue) {
            if (is_array($VarValue)) {
                $this->cleanupArray($VarValue);
            } else {
                $Array[$this->cleanupArrayKey($VarName)] = $this->cleanupArrayValue($VarValue);
            }
        }
    }


    /**
     * @deprecated
     * Unused
     */
    public static function array_strip_slashes(&$value)
    {
        Phpr::$deprecate->setFunction('array_strip_slashes');
        $value = stripslashes($value);
    }

    /**
     * @deprecated
     */
    public function get_value_array($name, $default = array())
    {
        $deprecate = new Deprecate();
        $deprecate->setFunction('get_value_array', 'getValueArray');
        return $this->getValueArray($name, $default);
    }

    /**
     * @deprecated
     */
    public function get_query_string($include_request_name = false)
    {
        Phpr::$deprecate->setFunction('get_query_string', 'getQueryString');
        return $this->getQueryString($include_request_name);
    }

    /**
     * @deprecated
     */
    public function protocol()
    {
        Phpr::$deprecate->setFunction('protocol', 'getProtocol');
        return $this->getProtocol();
    }

    /**
     * @deprecated
     */
    public function port()
    {
        $deprecate = new Deprecate();
        $deprecate->setFunction('port', 'getPort');
        return $this->getPort();
    }

    /**
     * @deprecated
     */
    public function post_array_item($arrayName, $name, $default = null)
    {
        Phpr::$deprecate->setFunction('post_array_item', 'postArray');
        return $this->postArray($arrayName, $name, $default);
    }

    /**
     * @deprecated
     */
    public function post($name = null, $default = null)
    {
        $deprecate = new Deprecate();
        $deprecate->setFunction('post', 'postField');
        return $this->postField($name = null, $default);
    }

    /**
     * Handle deprecated properties
     */

    public function __get($name)
    {
        if ($name === 'get_fields') {
            Phpr::$deprecate->setClassProperty('get_fields', 'getFields');
            return $this->getFields;
        }
    }

    public function __set($name, $value)
    {
        if ($name === 'get_fields') {
            Phpr::$deprecate->setClassProperty('get_fields', 'getFields');
            $this->getFields = $value;
        }
    }
}
