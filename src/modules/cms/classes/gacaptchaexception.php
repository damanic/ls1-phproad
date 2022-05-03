<?php
namespace Cms;

use Phpr\ApplicationException;

class GaCaptchaException extends ApplicationException
{
    public $captcha_url;
    public $captcha_token;
        
    public function __construct($message, $url, $token)
    {
        $this->captcha_url = $url;
        $this->captcha_token = $token;
        parent::__construct($message);
    }
}
