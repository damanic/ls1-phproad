<?

	/**
	 * PHP Road URL helper
	 *
	 * This class contains functions that may be useful for working with URLs.
	 */
	class Phpr_Url
	{
		/**
		 * Returns an URL of a specified resource relative to the LemonStand domain root
		 */
		public static function root_url($resource, $add_host_name_and_protocol = false, $protocol = null)
		{
			if (substr($resource, 0, 1) == '/')
				$resource = substr($resource, 1);

			$result = Phpr::$request->getSubdirectory().$resource;
			$root_url = null;

			if ($add_host_name_and_protocol){
				$root_url = Phpr::$request->getRootUrl($protocol);
				if(!$root_url){ //Most likely CLI executed, try config siteUrl
					if(!$protocol){
						//check if protocol can be extracted from siteurl config
						$site_url = self::siteUrl(null, false);
						$site_protocol = parse_url($site_url, PHP_URL_SCHEME);
						$protocol =  $site_protocol ? $site_protocol : null;
					}
					$protocol = $protocol ? $protocol."://" : '//';
					$site_url = self::siteUrl(null, true);
					$root_url =  $site_url ? $protocol.$site_url : null;
				}
			}
				
			return $root_url.$result;
		}
		
		/**
		 * Returns the URL of the website, as specified in the configuration WEBSITE_URL parameter.
		 *
		 * @param string  $resource          Optional path to a resource.
		 * Use this parameter to obtain the absolute URL of a resource.
		 * Example: Phpr_Url::siteUrl( 'images/button.gif' ) will return http://www.your-company.com/images/button.gif
		 * @param boolean $suppress_protocol Indicates whether the protocol name (http, https) must be suppressed.
		 *
		 * @return string
		 */
		public static function siteUrl( $resource = null, $suppress_protocol = false )
		{
			$url = Phpr::$config->get('WEBSITE_URL', null);

			if($url){
				$parsed_url = parse_url($url);
				if($parsed_url){
					if($suppress_protocol && isset($parsed_url['host'])){
						$url = $parsed_url['host'];
						if(isset($parsed_url['path'])){
							$url .= $parsed_url['path'];
						}
					}
				}
				$url = rtrim($url,"/");
			}

			if ( $resource === null || !strlen($resource) )
				return $url;

			if ( $url !== null ) {
				if ( $resource{0} == '/' )
					$resource = substr( $resource, 1 );

				return $url.'/'.$resource;
			}

			return $resource;
		}
		
		public static function get_params($url) {
			if(strpos($url, '/') === 0)
				$url = substr($url, 1);
			
			$segments = explode('/', $url);
			$params = array();
			
			foreach($segments as $segment) {
				if(strlen($segment))
					$params[] = $segment;
			}
			
			return $params;
		}
	}