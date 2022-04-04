<?php

/**
 * PHP Road
 *
 * PHP application framework
 *
 * @package    PHPRoad
 * @author     Aleksey Bobkov, Andy Chentsov
 * @since      Version 1.0
 * @filesource
 */

/**
 * PHP Road context information class
 *
 * This class provides access to the PHP Road foundation objects
 * like the Configuration, Trace Log, Debug Log and some other.
 *
 * @package  PHPRoad
 * @category PHPRoad
 * @author   Aleksey Bobkov
 */
class Phpr
{

    /**
     * Configuration object.
     * Provides array access to the configuration options.
     *
     * @var Phpr\Config
     */
    public static $config = null;

    /**
     * Class loader.
     * This object is used by the PHP Road for finding and loading classses.
     * Use this object to register a directories containing your application classes.
     *
     * @var Phpr\ClassLoader
     */
    public static $classLoader = null;

    /**
     * Error logging object. Allows the application to maintain the error log.
     *
     * @var Phpr\ErrorLog
     */
    public static $errorLog = null;

    /**
     * Trace logging object. Allows the application to write the tracing messages to the trace log(s).
     *
     * @var Phpr\TraceLog
     */
    public static $traceLog = null;

    /**
     * URI router. Maps URI strings to the controllers and actions
     *
     * @var Phpr\Router
     */
    public static $router = null;

    /**
     * Response object. Use this object for open the local URI's, redirect browser, show the 404 page and so on.
     *
     * @var Phpr\Response
     */
    public static $response = null;

    /**
     * Request object. Use this object to access the Post and Cookie variables.
     *
     * @var Phpr\Request
     */
    public static $request = null;

    /**
     * Language object. Use this object to load localization strings in user language.
     *
     * @var Phpr_Language
     */
    public static $lang = null;

    /**
     * Security object. Provides a basic security features based on cookies.
     *
     * @var Phpr\Security
     */
    public static $security = null;

    /**
     * Front-end security object.
     *
     * @var Phpr\Security
     */
    public static $frontend_security = null;

    /**
     * Security object. The session support allows you to register arbitrary variables to be preserved across requests.
     *
     * @var Phpr\Session
     */
    public static $session = null;

    /**
     * Events object. The events allows you to add and fire events.
     *
     * @var Phpr\Events
     */
    public static $events = null;

    /**
     * Deprecate object. Allows you to mark deprecated classes and methods.
     *
     * @var Phpr\Deprecate
     */
    public static $deprecate;

    /**
     * Determine if all PHPR foundation objects have been initialized
     *
     * @return bool
     */
    public static function ready()
    {
        if (self::$config
            && self::$classLoader
            && self::$errorLog
            && self::$traceLog
            && self::$router
            && self::$response
            && self::$request
            && self::$lang
            && self::$security
            && self::$frontend_security
            && self::$session
            && self::$events
        ) {
            return true;
        }
        return false;
    }
}
