<?php

/**
 * Represents the application HTTP response.
 * An instance of this class is always available through the <em>$Phpr</em> class and you never need to create it manually:
 * <pre>Phpr::$response->redirect('http://google.com');</pre>
 * @documentable
 * @see Phpr_Request
 * @author LemonStand eCommerce Inc.
 * @package core.classes
 */
class Phpr_Response
{
    const actionOn404Action = 'On404';
    const actionOnException = 'OnException';
    const controllerApplication = 'Application';

    public static $defaultJsScripts = array('mootools.js', 'popups.js', 'phproad.js');

    /**
     * Opens a local URL (like "blog/edit/1")
     * @param string $URI Specifies the URI to open.
     */
    public function open($URI)
    {
        $Controller = null;
        $Action = null;
        $Parameters = null;
        $Folder = null;

        Phpr::$router->route($URI, $Controller, $Action, $Parameters, $Folder);

        if (!strlen($Controller)) {
            $ControllerObj = Phpr::$classLoader->load_controller(self::controllerApplication);
        } else {
            $ControllerObj = Phpr::$classLoader->load_controller($Controller, $Folder);
        }

        if (!$ControllerObj) {
            $this->open404();
        }

        if ($Action == $Controller) {
            $Action = 'Index';
        }

        if (!$ControllerObj->_actionExists($Action)) {
            if ($ControllerObj->_actionExists(self::actionOn404Action)) {
                $ControllerObj->_run(self::actionOn404Action, array());
                exit;
            }
            $this->open404();
        }

        $ControllerObj->_run($Action, $Parameters);
    }

    /**
     * Opens the "Page not found" page.
     * By default this method opens a page provided by the PHP Road.
     * You may supply the application 404 page by creating the On404() action in the Application Controller.
     */
    public function open404()
    {
        // Output the default 404 message.
        //
        include PATH_SYSTEM . "/errorpages/404.htm";
        exit;
    }

    /**
     * Opens the Error Page.
     * By default this method opens a page provided by the PHP Road.
     * You may supply the application error page by creating the OnException($Exception) action in the Application Controller.
     */
    public function openErrorPage($exception)
    {
        if (ob_get_length()) {
            ob_clean();
        }

        // try to execute the application controller On404 action.
        $application = Phpr::$classLoader->load_controller(self::controllerApplication);

        if ($application != null && $application->_actionExists(self::actionOnException)) {
            $application->executeAction(self::actionOnException, array($exception));
            exit;
        }

        $error = Phpr_ErrorLog::get_exception_details($exception);

        // Output the default exception message.
        include PATH_SYSTEM . "/errorpages/exception.htm";
        exit;
    }

    /**
     * Redirects the browser to a specific URL.
     * Note that this method terminates the script execution.
     * @documentable
     * @param string $url Specifies the target URL.
     * @param boolean $send_301_header Determines whether HTTP header <em>301 Moved Permanently</em> should be sent.
     */
    public function redirect($Uri, $Send301Header = false)
    {
        if (!Phpr::$request->isRemoteEvent()) {
            if ($Send301Header) {
                header('HTTP/1.1 301 Moved Permanently');
            }

            switch (Phpr::$config->get("REDIRECT", 'location')) {
                case 'refresh':
                    header("Refresh:0;url=" . $Uri);
                    break;
                default:
                    header("location:" . $Uri);
                    break;
            }
        } else {
            $event_output = '
					if ( typeof window.CustomEvent === "function" ){
						var event = new CustomEvent("on_phpr_response_redirect", { 
							"detail": {
								"Uri" : "' . $Uri . '"
							} 
						});
						window.dispatchEvent(event);
					}
				';
            $output = "<script type='text/javascript'>";
            $output .= $event_output;
            $output .= "(function(){window.location='" . $Uri . "';}).delay(100)";
            $output .= "</script>";
            echo $output;
        }

        die;
    }

    /**
     * Sends a cookie.
     * @documentable
     * @param string $name Specifies the name of the cookie.
     * @param string $value Specifies the cookie value.
     * @param string $expire Specifies a time the cookie expires, in days.
     * @param string $path Specifies the path on the server in which the cookie will be available on.
     * @param string $domain Specifies a domain that the cookie is available to.
     * @param string $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection.
     */
    public function setCookie($Name, $Value, $Expire = 0, $Path = '/', $Domain = '', $Secure = null)
    {
        $_COOKIE[$Name] = $Value;

        if ($Secure === null) {
            if (Phpr::$request->protocol() === 'https' && Phpr::$config->get('SECURE_COOKIES', true)) {
                $Secure = true;
            } else {
                $Secure = false;
            }
        }

        if (Phpr::$request->isRemoteEvent()) {
            if (post('no_cookies')) {
                return;
            }

            $output = "<script type='text/javascript'>";
            $duration = $Expire;
            $Secure = $Secure ? 'true' : 'false';

            $output .= "Cookie.write('$Name', '$Value', {duration: $duration, path: '$Path', domain: '$Domain', secure: $Secure});";
            $output .= "</script>";
            echo $output;
        } else {
            if ($Expire > 0) {
                $Expire = time() + $Expire * 24 * 3600;
            }

            setcookie($Name, $Value, $Expire, $Path, $Domain, $Secure);
        }
    }

    /**
     * Adds <script> section to AJAX response containing a list of JavaScript and CSS
     * resources which should be loaded before the response text is rendered.
     */
    public function addRemoteResources($css = array(), $javascript = array())
    {
        if (!$css && !$javascript) {
            return;
        }

        $result = "<script type='text/javascript'>var phpr_resource_list_marker = 1;";
        if ($css) {
            $result .= 'phpr_css_list = ["' . implode('","', $css) . '"];';
        }

        if ($javascript) {
            $result .= 'phrp_js_list = ["' . implode('","', $javascript) . '"];';
        }

        $result .= '</script>';

        echo $result;
    }

    /**
     * Deletes a cookie.
     * @documentable
     * @param string $name Specifies a name of the cookie.
     * @param string $path Specifies the path on the server
     * @param string $domain Specifies a domain that the cookie is available to.
     * @param string $Secure Indicates that the cookie should only be transmitted over a secure HTTPS connection.
     * @see Phpr_Response::setCookie() setCookie()
     */
    public function deleteCookie($Name, $Path = '/', $Domain = '', $Secure = false)
    {
        if (Phpr::$request->isRemoteEvent()) {
            if (post('no_cookies')) {
                return;
            }

            $output = "<script type='text/javascript'>";
            $output .= "Cookie.dispose('$Name', {duration: 0, path: '$Path', domain: '$Domain'});";
            $output .= "</script>";
            echo $output;
        } else {
            setcookie($Name, '', time() - 360000, $Path, $Domain, $Secure);
        }
    }

    /**
     * Sends AJAX response with information about exception.
     * Note that this method terminates the script execution.
     * @documentable
     * @param mixed $exception Specifies the exception object or message.
     * @param boolean $html Determines whether the response message should be in HTML format.
     * @param boolean $focus Determines whether the focusing Java Script code must be added to the response.
     * This parameter will work only if $exception is a Phpr_ValidationException object
     */
    public function ajaxReportException($Exception, $Html = false, $Focus = false)
    {
        /*
         * Prepare the message
         */
        $Message = is_object($Exception) ? $Exception->getMessage() : $Exception;
        if ($Html) {
            $Message = nl2br($Message);
        }

        /*
         * Add focusing Java Script code
         */
        if ($Focus && $Exception instanceof Phpr_ValidationException) {
            $Message .= $Exception->validation->getFocusErrorScript();
        }

        /*
         * Output headers and result
         */
        echo "@AJAX-ERROR@";
        echo $Message;

        /*
         * Stop the script execution
         */
        die();
    }

}
