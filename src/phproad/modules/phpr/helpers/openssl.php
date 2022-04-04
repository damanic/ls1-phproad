<?php
namespace Phpr;

class OpenSSL implements EncryptionHandler
{
    const INPUT_ENC_METHOD = "AES-256-CBC";
    protected $keySize = 256;
    protected $ivSize = 32;


    public function encrypt($data, $key = null)
    {
        return base64_encode(openssl_encrypt($data, self::INPUT_ENC_METHOD, $key, 0, $this->getIv()));
    }

    public function decrypt($data, $key)
    {
        return openssl_decrypt(base64_decode($data), self::INPUT_ENC_METHOD, $key, 0, $this->getIv());
    }

    public function getKeySize()
    {
        return $this->keySize;
    }

    protected function getIv()
    {
        return openssl_random_pseudo_bytes($this->ivSize);
    }

}
