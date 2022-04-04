<?php
namespace Phpr;

/*
 * Mcrypt helper to help resolve compatibility issues when upgrading older PHP installs
 */

class Mcrypt implements EncryptionHandler
{
    protected $initialised = null;
    protected $modeDescriptor = null;
    protected $keySize = 256;
    protected $ivSize = 32;
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
            return $this->encryptNative($data, $key);
        }
        return $this->encryptCompat($data, $key);
    }

    public function decrypt($data, $key)
    {
        if ($this->native) {
            return $this->decryptNative($data, $key);
        }
        return $this->decryptCompat($data, $key);
    }

    public function getModeDescriptor()
    {
        if ($this->modeDescriptor == null) {
            $this->modeDescriptor = mcrypt_module_open(MCRYPT_RIJNDAEL_256, null, MCRYPT_MODE_CBC, null);
        }

        return $this->modeDescriptor;
    }

    public function getIvSize()
    {
        if (!is_numeric($this->ivSize)) {
            return mcrypt_enc_get_iv_size($this->getModeDescriptor());
        }
        return $this->ivSize;
    }

    public function getKeySize()
    {
        return $this->keySize;
    }

    public function __destruct()
    {
        if ($this->initialised) {
            mcrypt_module_close($this->modeDescriptor);
        }
    }

    /*
     * @TODO  function to help determine if encrypted data was encrypted by this class
     */
    public static function isEncryptedStringCompatible($string)
    {
    }

    protected function encryptNative($data, $key)
    {
        $descriptor = $this->getModeDescriptor();
        $key_size = mcrypt_enc_get_key_size($descriptor);
        srand();
        $iv = mcrypt_create_iv($this->getIvSize(), MCRYPT_RAND);
        mcrypt_generic_init($descriptor, $key, $iv);
        $encrypted = mcrypt_generic($descriptor, $data);
        mcrypt_generic_deinit($descriptor);
        return $iv . $encrypted;
    }

    protected function encryptCompat($data, $key)
    {
        //MCRYPT_MODE_CBC
        $rijndael = new \phpseclib\Crypt\Rijndael(\phpseclib\Crypt\Rijndael::MODE_CBC);
        $random_string = \phpseclib\Crypt\Random::string($this->ivSize);
        $rijndael->setBlockLength($this->getKeySize());
        $rijndael->setKey($key);
        $rijndael->setIV($random_string);
        return $random_string . $rijndael->encrypt($data);
    }

    protected function decryptNative($data, $key)
    {
        $descriptor = $this->getModeDescriptor();
        $key_size = mcrypt_enc_get_key_size($descriptor);
        $iv_size = $this->getIvSize();
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

    protected function decryptCompat($data, $key)
    {
        $rijndael = new \phpseclib\Crypt\Rijndael(\phpseclib\Crypt\Rijndael::MODE_CBC);
        $rijndael->setBlockLength($this->getKeySize());
        $rijndael->setKey($key);
        $rijndael->setIV($this->ivSize);
        $rijndael->disablePadding();
        return substr($rijndael->decrypt($data), $this->ivSize);
    }
}
