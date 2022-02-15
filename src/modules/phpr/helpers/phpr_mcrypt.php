<?php

/*
 * Mcrypt helper to help resolve compatibility issues when upgrading older PHP installs
 */

class Phpr_Mcrypt
{
    protected $initialised = null;
    protected $mode_descriptor = null;
    protected $key_size = 256;
    protected $iv_size = 32;
    private $native = true;

    public function __construct()
    {
        if (!extension_loaded('mcrypt')) {
            $this->native = false;
        }
    }

    public function encrypt($data, $key = null)
    {
        if ($this->native) {
            return $this->encrypt_native($data, $key);
        }
        return $this->encrypt_compat($data, $key);
    }

    public function decrypt($data, $key)
    {
        if ($this->native) {
            return $this->decrypt_native($data, $key);
        }
        return $this->decrypt_compat($data, $key);
    }

    public function get_mode_descriptor()
    {
        if ($this->mode_descriptor == null) {
            $this->mode_descriptor = mcrypt_module_open(MCRYPT_RIJNDAEL_256, null, MCRYPT_MODE_CBC, null);
        }

        return $this->mode_descriptor;
    }

    public function get_iv_size()
    {
        if (!is_numeric($this->iv_size)) {
            return mcrypt_enc_get_iv_size($this->get_mode_descriptor());
        }
        return $this->iv_size;
    }

    public function get_key_size()
    {
        return $this->key_size;
    }

    public function __destruct()
    {
        if ($this->initialised) {
            mcrypt_module_close($this->mode_descriptor);
        }
    }

    /*
     * @TODO  function to help determine if encrypted data was encrypted by this class
     */
    public static function is_encrypted_string_compatible($string)
    {
    }

    protected function encrypt_native($data, $key)
    {
        $descriptor = $this->get_mode_descriptor();
        $key_size = mcrypt_enc_get_key_size($descriptor);
        srand();
        $iv = mcrypt_create_iv($this->get_iv_size(), MCRYPT_RAND);
        mcrypt_generic_init($descriptor, $key, $iv);
        $encrypted = mcrypt_generic($descriptor, $data);
        mcrypt_generic_deinit($descriptor);
        return $iv . $encrypted;
    }

    protected function encrypt_compat($data, $key)
    {
        //MCRYPT_MODE_CBC
        $rijndael = new \phpseclib\Crypt\Rijndael(\phpseclib\Crypt\Rijndael::MODE_CBC);
        $random_string = \phpseclib\Crypt\Random::string($this->iv_size);
        $rijndael->setBlockLength($this->get_key_size());
        $rijndael->setKey($key);
        $rijndael->setIV($random_string);
        return $random_string . $rijndael->encrypt($data);
    }

    protected function decrypt_native($data, $key)
    {
        $descriptor = $this->get_mode_descriptor();
        $key_size = mcrypt_enc_get_key_size($descriptor);
        $iv_size = $this->get_iv_size();
        $iv = substr($data, 0, $iv_size);
        $data = substr($data, $iv_size);

        if (strlen($iv) < $iv_size) {
            return null;
        }

        mcrypt_generic_init($descriptor, $key, $iv);
        $result = mdecrypt_generic($descriptor, $data);
        mcrypt_generic_deinit($descriptor);
        return $result;
    }

    protected function decrypt_compat($data, $key)
    {
        $rijndael = new \phpseclib\Crypt\Rijndael(\phpseclib\Crypt\Rijndael::MODE_CBC);
        $rijndael->setBlockLength($this->get_key_size());
        $rijndael->setKey($key);
        $rijndael->setIV($this->iv_size);
        $rijndael->disablePadding();
        return substr($rijndael->decrypt($data), $this->iv_size);
    }
}
