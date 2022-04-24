<?php
namespace Core;

use Phpr;
use Phpr\Security as PhprSecurity;
use Phpr\Validation as Validation;
use Phpr\ApplicationException;
use Users\User as User;
use Db\DeferredBinding;
use Db\RecordLock;
use System\LoginLogRecord;
use System\Backup_Archive;

    /**
     * Core security class.
     * This class extends the standard PHP Road Security class.
     * @has_documentable_methods
     */
class Security extends PhprSecurity
{
    private $coreUser = null;

    public function __construct()
    {
        $this->userClassName = "Users\User";
    }
        
    public function login(Validation $Validation = null, $Redirect = null, $Login = null, $Password = null)
    {
        if (parent::login($Validation, null)) {
            Phpr::$events->fireEvent('onLogin');
            $this->user->update_last_login();
            Phpr::$response->redirect($Redirect);
        }
            
        return false;
    }
    
    /**
     * Determines whether the user is allowed to access the application
     *
     * @return mixed
     */
    public function baseAuthorization()
    {
        /*
         * Check if user is authenticated and redirect to the Login page of not so.
         */
        if (parent::getUser() == null) {
            $currentUri = Phpr::$request->getCurrentUri();

            if ($currentUri != url("/session/handle/loginredirect")) {
                $Uri = urlencode(str_replace('/', '|', strtolower($currentUri)));
            } else {
                $Uri = null;
            }

            Phpr::$response->redirect(url('/session/handle/create/'.$Uri));
        }
            
        $user = parent::getUser();
        if ($user->status == User::disabled) {
            $this->kickOut();
        }

        if (!post('phpr_no_cookie_update')) {
            $this->updateCookie($user->id);
        }
    }

    /**
     * Redirects a browser to the login page
     */
    public function kickOut()
    {
        Phpr::$response->redirect(url("/session/handle/create"));
    }

    /**
     * Returns identifier of a current user
     * @return int
     */
    public function getUserId()
    {
        return $this->getUser()->id;
    }
        
    protected function checkUser($user)
    {
        if ($user && $user->status == User::disabled) {
            throw new ApplicationException('Your user account has been disabled.');
        }
    }
        
    protected function afterLogin($user)
    {
        DeferredBinding::cleanUp(3);
        if (!Phpr::$config->get('DISABLE_BACKUP_FEATURE')) {
            Backup_Archive::backup();
        }
        RecordLock::cleanUp();
            
        $user = $this->getUser();
        if ($user) {
            LoginLogRecord::create_record($user);
            $user->clearPasswordResetHash();
        }
    }
        
    public function http_authentication($zone_name, $cancel_text = null)
    {
        $cancel_text = $cancel_text !== null ? $cancel_text : "You must enter a valid login ID and password to access this resource";

        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            $this->send_auth_headers($zone_name, $cancel_text);
        }

        $user = trim(strtolower($_SERVER['PHP_AUTH_USER']));

        $obj = new User();
        if (!($obj = $obj->findUser($user, $_SERVER['PHP_AUTH_PW']))) {
            self::send_auth_headers($zone_name, $cancel_text);
        }

        return $obj;
    }
        
    private function send_auth_headers($zone, $cancel_text)
    {
        header('WWW-Authenticate: Basic realm="'.$zone.'"');
        header('HTTP/1.0 401 Unauthorized');
        die($cancel_text);
    }
        
    /*
     * Event descriptions
     */
        
    /**
     * Triggered when a user logs into the Administration Area.
     * @event onLogin
     * @package core.events
     * @author LSAPP
     */
    private function event_onLogin()
    {
    }
}
