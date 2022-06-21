<?php
namespace Shop;

use Phpr;
use Backend\Controller;

class Reports extends Controller
{
    public function index()
    {
        Phpr::$response->redirect(url('/shop/orders_report/'));
    }
}
