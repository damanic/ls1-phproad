<?php
namespace Net;

class Response
{
    public $request;
    public $headers;
    public $data;
    public $info;
    public $status_code;
    public $error_code;
    public $error_info;

    public function __construct()
    {
        $this->data = '';
        $this->info = array();
        $this->status_code = 0;
        $this->request = null;
        $this->headers = array();
    }
}
