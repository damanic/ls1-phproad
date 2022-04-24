<?php
namespace Core;

use Phpr;
use Phpr\Security;
use Phpr\Validation;
use Shop\CheckoutData as UserCheckoutData;
use Shop\Customer as User;

    /**
     * Core security class.
     * This class extends the standard PHP Road Security class.
     * @has_documentable_methods
     */
class FrontEndSecurity extends Security
{
    public $userClassName = "Shop\Customer";
    public $cookieName = "eCommerce";
    protected $cookieLifetimeVar = 'FRONTEND_AUTH_COOKIE_LIFETIME';

    protected static $UserCache = array();
    protected $cookie_updated = false;
        
    /**
     * Validates user login name and password and logs user in.
     *
     * @param string $Login Specifies the user login name.
     * If you omit this parameter the 'Login' POST variable will be used.
     *
     * @param string $Password Specifies the user password
     * If you omit this parameter the 'Password' POST variable will be used.
     *
     * @param string $Redirect Optional URL to redirect the user browser in case of successful login.
     * @param Validation $Validation Optional validation object to report errors.
     *
     * @return boolean
     */
    public function login(Validation $Validation = null, $Redirect = null, $Login = null, $Password = null)
    {
        return parent::login($Validation, $Redirect, $Login, $Password);
    }

    public function getUser()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        /*
         * Determine whether the authentication cookie is available
         */

        $CookieName = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
        $Ticket = Phpr::$request->cookie($CookieName);

        $frontend_ticket_param = Phpr::$config->get('TICKET_PARAM_NAME', 'ls_frontend_ticket');

        if ($Ticket === null) {
            /*
             * Check whether the front-end ticket was passed as a GET parameter
             */
            $Ticket = $this->restoreTicket(Phpr::$request->getField($frontend_ticket_param));
        } else {
            /*
             * Delete the ticket if it was passed as a GET parameter
             */
            $this->removeTicket(Phpr::$request->getField($frontend_ticket_param));
        }
            
        if (!$Ticket) {
            return null;
        }

        /*
         * Validate the ticket
         */
        $Ticket = $this->validateTicket($Ticket);
        if ($Ticket === null) {
            return null;
        }

        /*
         * Return the ticket user
         */
        $UserId = trim(base64_decode($Ticket['user']));
        if (!strlen($UserId)) {
            return null;
        }
            
        return $this->findUser($UserId);
    }

    public function authorize_user()
    {
        if (!$this->check_session_host()) {
            return null;
        }

        $user = $this->getUser();

        if (!$user) {
            return null;
        }

        if (!$this->cookie_updated) {
            $this->updateCookie($user->id);
            $this->cookie_updated = true;
        }

        return $user;
    }

    protected function updateCookie($Id)
    {
        /*
         * Prepare the authentication ticket
         */
        $Ticket = $this->getTicket($Id);

        /*
         * Set a cookie
         */
        $CookieName = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
        $CookieLifetime = Phpr::$config->get($this->cookieLifetimeVar, $this->cookieLifetime);

        $CookiePath = Phpr::$config->get('FRONTEND_AUTH_COOKIE_PATH', $this->cookiePath);
        $CookieDomain = Phpr::$config->get('FRONTEND_AUTH_COOKIE_DOMAIN', $this->cookieDomain);

        Phpr::$response->setCookie($CookieName, $Ticket, $CookieLifetime, $CookiePath, $CookieDomain);
    }
        
    public function customerLogin($CustomerId)
    {
        $this->updateCookie($CustomerId);
        Phpr::$events->fireEvent('onFrontEndLogin');
    }

    public function logout($Redirect = null)
    {
        Phpr::$events->fireEvent('onFrontEndLogout', $this->getUser());

        $CookieName = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
        $CookiePath = Phpr::$config->get('FRONTEND_AUTH_COOKIE_PATH', $this->cookiePath);
        $CookieDomain = Phpr::$config->get('FRONTEND_AUTH_COOKIE_DOMAIN', $this->cookieDomain);

        Phpr::$response->deleteCookie($CookieName, $CookiePath, $CookieDomain);

        $this->user = null;

        Phpr::$session->destroy();

        if ($Redirect !== null) {
            Phpr::$response->redirect($Redirect);
        }
    }

    protected function beforeLoginSessionDestroy($user)
    {
        Phpr::$events->fireEvent('onFrontEndLogin');
    }
        
    protected function keepSessionData()
    {
        return strlen(UserCheckoutData::get_coupon_code());
    }

    public function findUser($UserId)
    {
        if (isset(self::$UserCache[$UserId])) {
            return self::$UserCache[$UserId];
        }
        $user = User::create()->where('deleted_at is null')->where('shop_customers.id=?', $UserId)->find();
        return self::$UserCache[$UserId] = $user;
    }
        
    protected function afterLogin($User)
    {
        Phpr::$events->fireEvent('onAfterFrontEndLogin', $User);
    }

    /*
     * Event descriptions
     */
        
    /**
     * Triggered when a registered customer logs into the store.
     * @event onFrontEndLogin
     * @see http://https://damanic.github.io/ls1-documentation/docs/customer_login_and_logout/ Customer login and logout
     * @package core.events
     * @author LSAPP
     */
    private function event_onFrontEndLogin()
    {
    }
            
    /**
     * Triggered when a registered customer logs out.
     * Please note that this event is not triggered if a customer leaves the website or closes the browser tab.
     * The event is triggered before the customer session is destroyed.
     * @event onFrontEndLogout
     * @see http://https://damanic.github.io/ls1-documentation/docs/customer_login_and_logout/ Customer login and logout
     * @package core.events
     * @author LSAPP
     * @param Shop_Customer $customer The customer object.
     */
    private function event_onFrontEndLogout($customer)
    {
    }

    /**
     * Triggered after a registered customer logs in.
     * @event onAfterFrontEndLogin
     * @see http://https://damanic.github.io/ls1-documentation/docs/customer_login_and_logout/ Customer login and logout
     * @package core.events
     * @author LSAPP
     * @param Shop_Customer $customer The customer object.
     */
    private function event_onAfterFrontEndLogin($customer)
    {
    }
}