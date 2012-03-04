<?php
/**
 * Abstract class definition for SocialLogin Processes
 */

abstract class AbstractSocialLoginProvider extends Process implements ConfigurableModule {
	
	/**
	 * Name of the provider
	 */
	protected $providerName;
	
    /**
     * Provide roles for users created within this login process
     */ 
    private static function _getUserRoles() {
        $roles = new PageArray();
        $config = Wire::getFuel('modules')->getModuleConfigData(self::className());
        if(is_array($config['user_roles'])) {
            foreach($config['user_roles'] as $user_role) {
                $roles->add($this->roles->get($user_role));
            }
        } else {
            $user_role = null;
            if(($user_role = Wire::getFuel('roles')->get($config['user_roles'])) && ($user_role->id)) {
                $roles->add($user_role);
            }
        }
        return $roles;
    }
	
	/**
	 * provide fields or buttons for implementing provider in login form
	 */
	abstract public function extendLoginForm(&$form);
	
	/**
	 * pass on 
	 */
	public static function getModuleConfigInputfields(array $data) {
		$fields = new FieldWrapper();
		return $fields;
	}
    
    /**
     * create user data page
     */
    public function createUserDataPage($user, $identifier) {
        if($user instanceof User == false) {
            $user = $this->users->get($user);
        }
        if($user->id && is_scalar($identifier)) {        
            // create user data page
            $adminPage = $this->fuel('pages')->get($this->config->adminRootPageID);
            $provider = $adminPage->child("name=setup")->child("name=sociallogin")->child('name='.strtolower($this->providerName));
            $user_data = new Page($this->templates->get('sociallogin_user'));
            $user_data->parent = $provider;
            $user_data->title = 'User '.$user->id;
            $user_data->name = $this->fuel('sanitizer')->pageName($identifier, $user_data->settings['name']);
            $user_data->sociallogin_user = $user->id;
            $user_data->save();
            if($user_data->id) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    /**
     * find user data page by identifier
     */
    public function getUserDataPage($identifier) {
        if(is_scalar($identifier)) {
            $adminPage = $this->fuel('pages')->get($this->config->adminRootPageID);
            $provider = $adminPage->child("name=setup")->child("name=sociallogin")->child('name='.strtolower($this->providerName));
            $user_data = $provider->child('name='.$identifier);
            if($user_data->id) {
                return $user_data;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    /**
     * store any user data
     */
    public function saveUserData($user, array $data) {
        if($user instanceof User == false) {
            $user = $this->users->get($user);
        }
        if($user->id) {
            // get user data page
            $user_data = $this->pages->get('template=sociallogin_user, sociallogin_user='.$user->id);
            if(!$user_data->id) {
                throw new WireException($this->_('WasnÕt able to load user data page.')); // Error message on creation of user data page
            } else {
                $json = count($data) ? wireEncodeJSON($data, true) : '{}';
                $user_data->sociallogin_user_data = $json;
                $user_data->save();
            }
            return true;            
        } else {
            return false;
        }
    }
    /**
     * load user data
     */
	public function getUserData($user) {
        if($user instanceof User == false) {
            $user = $this->users->get($user);
        }
        if($user->id) {
            $adminPage = $this->fuel('pages')->get($this->config->adminRootPageID);
            $provider = $adminPage->child("name=setup")->child("name=sociallogin")->child('name='.strtolower($this->providerName));
            $user_data = $provider->child('sociallogin_user='.$user->id);
            return wireDecodeJSON($user_data->sociallogin_user_data);      
        } else {
            return false;
        }	    
	}

    public function getRandomPassword($length=12) {
        if(!is_numeric($length)) {
            $length = 12;
        } 
        return mb_substr(base64_encode(date("Y.m.d H:i:s")), 0, $length);
    }

}

abstract class AbstractSocialLoginProviderAPI extends AbstractSocialLoginProvider {

    var $http_info = array();
    
    /**
     * Utilities to address external APIs
     */
    /**
     * Make an HTTP request
     * Based on http method of TwitterOAuth class by Abraham Williams
     * @return API results
     */
    public function http($url, $data = NULL, $options = array(), $headers=array()) {
        $ci = curl_init();
       
        /* Curl settings */
        $default_headers = array(
            'Expect:'
            //'Content-Type: application/x-www-form-urlencoded',
            //'Accept: /'
            );
        if(is_array($headers)) {
            $headers = array_merge($default_headers, $headers);
        } else {
            $headers = $default_headers;
        }
        print_r($headers);
      
        $defaults = array(
            CURLOPT_USERAGENT => "SocialLoginProvider",
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HEADERFUNCTION => array($this, 'getHeader'),
            CURLOPT_HEADER => FALSE
        );
        foreach($defaults as $index=>$option) {
            if(!array_key_exists($index, $options)) {
                $options[$index] = $option;
            }
        }
        //$options = array_merge($defaults, $options);
        curl_setopt_array($ci, $options);

        if(isset($data))
        {
            if(is_array($data)) {
                $data = $this->buildQueryString($data);
            }
            curl_setopt($ci, CURLOPT_POST, 1);
            curl_setopt($ci, CURLOPT_POSTFIELDS, $data);
        }        
        
        curl_setopt($ci, CURLOPT_URL, $url);
        if(!$response = curl_exec($ci)) { 
            trigger_error(curl_error($ci)); 
        }  
        $this->http_info['code'] = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        $this->http_info['curl_info'] = curl_getinfo($ci);
        curl_close ($ci);
        return $response;
    }   
    /**
     * Normalize url
     */
    public function normalizeUrl($url) {
        if(is_scalar($url) && $parts = parse_url($url)) {
            $port = @$parts['port'];
            $scheme = $parts['scheme'];
            $host = $parts['host'];
            $path = @$parts['path'];
            
            $port or $port = ($scheme == 'https') ? '443' : '80';
            
            if (($scheme == 'https' && $port != '443')
                || ($scheme == 'http' && $port != '80')) {
              $host = "$host:$port";
            }
            return "$scheme://$host$path";
        } else {
            return false;
        }
    }
    
    /**
     * Get the header info
     */
    function getHeader($ch, $header) {
        $i = strpos($header, ':');
        if (!empty($i)) {
            $key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
            $value = trim(substr($header, $i + 2));
            $this->http_info['header'][$key] = $value;
        }
        return strlen($header);
    }
    
    /**
     * This function takes a input like a=b&a=c&d=e and returns the parsed
     * parameters like this
     * array('a' => array('b','c'), 'd' => 'e')
     * originally written by Abraham Williams
     */
    public function parseParameters( $params ) {
        if (!isset($params) || !$params) return array();
        $pairs = explode('&', $params);
        $parsed_parameters = array();
        foreach ($pairs as $pair) {
            $split = explode('=', $pair, 2);
            $parameter = $this->urlDecode($split[0]);
            $value = isset($split[1]) ? $this->urlDecode($split[1]) : '';
            if (isset($parsed_parameters[$parameter])) {
                // We have already recieved parameter(s) with this name, so add to the list
                // of parameters with this name
                if (is_scalar($parsed_parameters[$parameter])) {
                    // This is the first duplicate, so transform scalar (string) into an array
                    // so we can add the duplicates
                    $parsed_parameters[$parameter] = array($parsed_parameters[$parameter]);
                }
                $parsed_parameters[$parameter][] = $value;
            } else {
                $parsed_parameters[$parameter] = $value;
            }
        }
        return $parsed_parameters;
    }    

    public function http_req($url, $parameters=array(), $referer='') {
         // Convert the data array into URL Parameters like a=b&foo=bar etc.
        $data = $this->buildQueryString($parameters);
     
        // parse the given URL
        $url = parse_url($url);
     
        // extract host and path:
        $host = $url['host'];
        $path = $url['path'];
        $query = $url['query'];
        // open a socket connection on port 80 - timeout: 30 sec
        $fp = fsockopen($host, 80, $errno, $errstr, 30);
     
        if ($fp){
     
            // send the request headers:
            fputs($fp, "POST $path?".(strlen($query)?$query:"")." HTTP/1.1\r\n");
            fputs($fp, "Host: $host\r\n");
     
            if ($referer != '')
                fputs($fp, "Referer: $referer\r\n");
     
            fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
            fputs($fp, "Content-length: ". strlen($data) ."\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, $data);
     
            $result = ''; 
            while(!feof($fp)) {
                // receive the results of the request
                $result .= fgets($fp, 128);
            }
        }
        else { 
            return array(
                'status' => 'err', 
                'error' => "$errstr ($errno)"
            );
        }
     
        // close the socket connection:
        fclose($fp);
     
        // split the result header from the content
        $result = explode("\r\n\r\n", $result, 2);
     
        $this->http_info['header'] = isset($result[0]) ? $result[0] : '';
        $response = isset($result[1]) ? $result[1] : '';
     
        // return as structured array:
        return $response;      
    }
    
    /**
     * URL en- and decoding
     */  
    public function urlEncode($data) {
        if (is_array($data)) {
            return array_map(array($this, 'urlEncode'), $data);
        } else if (is_scalar($data)) {
            return str_replace("+", " ", str_replace("%7E", "~", rawurlencode($data)));
        } else {
            return "";
        }
    }
    public function urlDecode($string) {
        return urldecode($string);
    }
    /**
     * Build http query
     */
    public function buildQueryString($params) {
        if (!$params) return '';
    
        // Urlencode both keys and values
        $keys = $this->urlEncode(array_keys($params));
        $values = $this->urlEncode(array_values($params));
        $params = array_combine($keys, $values);
    
        uksort($params, 'strcmp');
    
        $pairs = array();
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                // If two or more parameters share the same name, they are sorted by their value
                natsort($v);
                foreach ($v as $duplicate) {
                    $pairs[] = $k . '=' . $duplicate;
                }
            } else {
                $pairs[] = $k . '=' . $v;
            }
        }
        return implode('&', $pairs);
    }    
    
}
