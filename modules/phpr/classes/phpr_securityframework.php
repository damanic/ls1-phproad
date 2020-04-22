<?

class Phpr_SecurityFramework {
	private static $instance;

	private $mode_descriptor = null;
	private $config_content = null;
	private $salt;
	private $salt_cookie;
	private $key;
	private $data_cache = array();
	private $encryption_handler = null;
	public $debug = false;

	protected function __construct() {
		$this->encryption_handler = new Phpr_Mcrypt();
	}

	public static function create() {
		if ( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function reset_instance() {
		$this->mode_descriptor = null;
		$this->config_content  = null;
		$this->salt            = null;
		return self::$instance = new self();
	}

	public function __destruct() {
	}

	public function encrypt( $data, $key = null, $salt = null ) {
		$data = serialize( $data );

		if ( $key === null ) {
			$key = $this->get_key();
		}

		if ( $salt === null ) {
			$salt = $this->salt( $key );
		}

		$strong_key = substr( md5( $salt . $key ), 0, $this->encryption_handler->get_key_size() );
		$result     = $this->encryption_handler->encrypt( $data, $strong_key );

		return self::obfuscate_data( $result, $strong_key );
	}

	public function decrypt( $data, $key = null, $salt = null ) {

		if($this->encrypted_string_has_tag($data)){
			return $this->tagged_decrypt($data);
		}

		if ( $key === null ) {
			$key = $this->get_key();
		}

		if ( $salt === null ) {
			$salt = $this->salt( $key );
		}

		$data_key = 'sf-' . md5( $data . '^|^' . $key . '^|^' . $salt );
		if ( array_key_exists( $data_key, $this->data_cache ) ) {
			return $this->data_cache[$data_key];
		}

		if ( Phpr::$config->get( 'ENABLE_SECURE_DATA_CACHING', false ) ) {
			$cache  = Core_CacheBase::create();
			$result = $cache->get( $data_key );
			if ( $result !== false ) {
				return $result;
			}
		}

		$strong_key = substr( md5( $salt . $key ), 0, $this->encryption_handler->get_key_size() );
		$data       = self::deobfuscate_data( $data, $strong_key );
		$result     = $this->encryption_handler->decrypt( $data, $strong_key );
		$res = null;
		try {
			$unserialized = @unserialize( $result );
			if ( $unserialized !== false ) {
				$res = $unserialized;
			}
		} catch ( Exception $ex ) {
		}

		if ( Phpr::$config->get( 'ENABLE_SECURE_DATA_CACHING', false ) ) {
			Core_CacheBase::create()->set( $data_key, $res );
		}

		return $res;
	}

	/*
	 * Tags the encryption string with the encryption handler name
	 * This should be used when storing encrypted data in DB
	 */
	public function tagged_encrypt($data, $key = null, $salt = null){
		return "<handler>".get_class($this->encryption_handler)."</handler>".$this->encrypt($data, $key, $salt);
	}

	protected function tagged_decrypt($data, $key = null, $salt = null){
		$regex = '#<\s*?handler\b[^>]*>(.*?)</handler\b[^>]*>#s';
		$matches = array();
		preg_match($regex, $data, $matches);
		$handler_tag = ($matches[1]) ? $matches[1] :false;
		if($handler_tag){
			$data = str_replace('<handler>'.$handler_tag.'</handler>','',$data);
			if(class_exists($handler_tag) && (get_class($this->encryption_handler)) !== $handler_tag){
				$this->encryption_handler = new $handler_tag();
			}
		} else {
			throw new Phpr_ApplicationException('Encryption string is missing the handler tag');
		}
		return $this->decrypt($data, $key, $salt);
	}

	public function encrypted_string_has_tag($string){
		return (substr($string,0, 9) == '<handler>');
	}

	public function use_legacy_encryption_handler(){
		$this->encryption_handler = new Phpr_Mcrypt();
	}


	protected function get_key() {
		if ( !is_null( $this->key ) ) {
			return $this->key;
		}

		$config_data = $this->get_config_content();
		if ( !array_key_exists( 'config_key', $config_data ) ) {
			throw new Phpr_SystemException( 'Invalid configuration file.' );
		}

		return $this->key = $config_data['config_key'];
	}

	protected function obfuscate_data( &$data, &$key ) {
		$strong_key = md5( $key );

		$key_size  = strlen( $strong_key );
		$data_size = strlen( $data );
		$result    = str_repeat( ' ', $data_size );

		$key_index = $data_index = 0;

		while ( $data_index < $data_size ) {
			if ( $key_index >= $key_size ) {
				$key_index = 0;
			}

			$result[$data_index] = chr( ( ord( $data[$data_index] ) + ord( $strong_key[$key_index] ) ) % 256 );

			++ $data_index;
			++ $key_index;
		}

		return $result;
	}

	protected function deobfuscate_data( &$data, &$key ) {
		$strong_key = md5( $key );

		$result    = str_repeat( ' ', strlen( $data ) );
		$key_size  = strlen( $strong_key );
		$data_size = strlen( $data );

		$key_index = $data_index = 0;

		while ( $data_index < $data_size ) {
			if ( $key_index >= $key_size ) {
				$key_index = 0;
			}

			$byte = ord( $data[$data_index] ) - ord( $strong_key[$key_index] );
			if ( $byte < 0 ) {
				$byte += 256;
			}

			$result[$data_index] = chr( $byte );
			++ $data_index;
			++ $key_index;
		}

		return $result;
	}

	/*
	 * @deprecated
	 */
	protected function get_mode_descriptor() {
		if ( method_exists( $this->encryption_handler, 'get_mode_descriptor' ) ) {
			return $this->encryption_handler->get_mode_descriptor();
		}
	}

	public function set_config_content( $content ) {
		$this->config_content = $content;

		$file_path = Phpr::$config->get( 'SECURE_CONFIG_PATH', PATH_APP . '/config/config.dat' );

		$data = $this->encrypt( $content,
			Phpr::$config->get( 'CONFIG_KEY1', '@#$7as23' ),
			Phpr::$config->get( 'CONFIG_KEY2', '#0qw4-3dk' ) );

//			@chmod($file_path, Phpr::$config->get('FILE_FOLDER_PERMISSIONS'));
		file_put_contents( $file_path, $data );
	}

	public function get_config_content() {
		if ( $this->config_content ) {
			return $this->config_content;
		}

		$file_path = Phpr::$config->get( 'SECURE_CONFIG_PATH', PATH_APP . '/config/config.dat' );
		if ( !file_exists( $file_path ) ) {
			throw new Phpr_ApplicationException( 'Secure configuration file is not found.' );
		}

		try {
			$data = $this->decrypt( file_get_contents( $file_path ),
				Phpr::$config->get( 'CONFIG_KEY1', '@#$7as23' ),
				Phpr::$config->get( 'CONFIG_KEY2', '#0qw4-3dk' ) );
		} catch ( Exception $ex ) {
			throw new Phpr_SystemException( 'Error loading configuration file.' );
		}

		if ( !is_array( $data ) ) {
			return array();
		}

		return $this->config_content = $data;
	}

	public function salt( $salt_key = null ) {
		if ( $salt_key ) {
			return md5( $salt_key );
		}

		if ( strlen( $this->salt ) ) {
			return $this->salt;
		}

		$config_data = $this->get_config_content();
		if ( !array_key_exists( 'config_key', $config_data ) ) {
			throw new Phpr_SystemException( 'Invalid configuration file.' );
		}

		return $this->salt = md5( $config_data['config_key'] );
	}

	public function salted_hash( $value, $salt_key = null ) {
		return md5( $this->salt( $salt_key ) . $value );
	}

	public function salted_cookie( $salt_key = null ) {
		if ( $salt_key ) {
			return md5( $salt_key );
		}

		if ( strlen( $this->salt_cookie ) ) {
			return $this->salt_cookie;
		}

		$salt = Phpr::$config->get( 'COOKIE_SALT' );

		if ( $salt === null ) {
			throw new Phpr_SystemException( 'Missing configuration value (COOKIE_SALT)' );
		}

		if ( strlen( $salt ) < 10 ) {
			throw new Phpr_SystemException( 'Invalid configuration value (COOKIE_SALT)' );
		}

		return $this->salt_cookie = md5( $salt );
	}
}

?>