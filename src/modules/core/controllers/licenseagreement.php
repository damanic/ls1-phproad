<?php
namespace Core;

use Backend\Controller;

class LicenseAgreement extends Controller
{
    public $implement = 'Db_FormBehavior';
    public $no_agreement_redirect = true;
        
    public function index()
    {
        Phpr::$response->redirect(url('/'));
    }
}
