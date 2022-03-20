<?php

class Phpr_OpenSSL implements Phpr_Encryption
{
    const INPUT_ENC_METHOD = "AES-256-CBC";
    protected $key_size = 256;
    protected $iv_size = 32;


    public function encrypt($data, $key = null)
    {
        return base64_encode(openssl_encrypt($data, self::INPUT_ENC_METHOD, $key, 0, $this->get_iv()));
    }

    public function decrypt($data, $key)
    {
        return openssl_decrypt(base64_decode($data), self::INPUT_ENC_METHOD, $key, 0, $this->get_iv());
    }

    public function get_key_size()
    {
        return $this->key_size;
    }

    protected function get_iv()
    {
        return openssl_random_pseudo_bytes($this->iv_size);
    }

}
