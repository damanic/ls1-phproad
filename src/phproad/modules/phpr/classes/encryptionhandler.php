<?php
namespace Phpr;

interface EncryptionHandler
{

    public function encrypt($data, $key = null);

    public function decrypt($data, $key);

    public function getKeySize();

}
