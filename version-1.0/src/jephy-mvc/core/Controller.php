<?php
namespace App\Core;
use App\Core\Services\JwtService;
use App\Core\Request;

abstract class Controller
{
    protected $smarty;
    protected $hooks;
    protected $globalData;
    protected $appPath;
	protected JwtService $jwtService;
	protected Request $request;
    
    public function __construct()
    {
        
		// Get Framework instance to ensure initialization
        $framework 			= Framework::getInstance();        
        $this->smarty 		= Framework::getSmarty();
        $this->hooks 		= Framework::getHooks();
        $this->globalData 	= Framework::getGlobalDataHook();
        $this->appPath 		= Framework::getAppPathStatic();
        $this->jwtService 	= new JwtService();
        $this->request 		= new Request(); // Initialize request
        
        // Verify Smarty is available
        if ($this->smarty === null) {
            throw new \RuntimeException('Smarty not initialized. Check Framework initialization.');
        }
        
        $this->registerCustomSmartyFunctions();
        
    }
    
    protected function assign($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->smarty->assign($k, $v);
            }
        } else {
            $this->smarty->assign($key, $value);
        }
        return $this;
    }
    
    protected function render($template, $data = [])
    {
        // Assign all data
        foreach ($data as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        
        // Execute hooks before rendering
        $this->hooks->exec('beforeRender', [
            'template' => $template,
            'data' => $data
        ]);
        
        // Display the template
        $this->smarty->display($template);
    }
    
    /**
     * Render template with hook support (returns string)
     */
    protected function fetch($template, $data = [])
    {
        foreach ($data as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        
        // Execute beforeRender hook
        $this->hooks->exec('beforeRender', [
            'template' => $template,
            'data' => $data
        ]);
        
        // Fetch template content
        $content = $this->smarty->fetch($template);
        
        // Execute afterRender hook
        $hookResults = $this->hooks->exec('afterRender', [
            'template' => $template,
            'data' => $data,
            'content' => $content
        ]);
        
        // Get the last modified content from hooks
        if (is_array($hookResults) && !empty($hookResults)) {
            $lastResult = end($hookResults);
            if (is_string($lastResult)) {
                $content = $lastResult;
            } elseif (is_array($lastResult) && isset($lastResult['content'])) {
                $content = $lastResult['content'];
            }
        }
        
        return $content;
    }
    
    /**
     * Display template using Smarty's display() with hook support
     */
	protected function display($template, $data = [])
	{
		// Execute beforeRender hook - pass template and data
		$hookResult = $this->hooks->exec('beforeRender', [$template, $data]);
		
		// If hook returned modified data, use it
		if (is_array($hookResult) && count($hookResult) === 2) {
			list($template, $data) = $hookResult;
		}
		
		// Assign data to Smarty
		foreach ($data as $key => $value) {
			$this->smarty->assign($key, $value);
		}
		
		ob_start();
		$this->smarty->display($template);
		$output = ob_get_clean();
		
		// Execute afterRender hook
		$hookResults = $this->hooks->execWithReturn('afterRender', [$template, $data, $output]);
		
		// Use the last hook result if available
		if (!empty($hookResults)) {
			$lastResult = end($hookResults);
			if (is_string($lastResult)) {
				$output = $lastResult;
			} elseif (is_array($lastResult) && isset($lastResult[2])) {
				$output = $lastResult[2];
			}
		}
		
		echo $output;
	}
    
    /**
     * Quick render without hooks (for performance)
     */
    protected function quickRender($template, $data = [])
    {
        foreach ($data as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        
        $this->smarty->display($template);
    }
    
    protected function json($data, $statusCode = 200)
    {		
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode( $data, JSON_PRETTY_PRINT );
        exit;
    }
    
    protected function redirect($url, $statusCode = 302)
    {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
    
    // ==================== VALIDATION METHODS ====================
    
    protected function validate($data, $rules)
    {
        if (!class_exists('App\\Core\\Validator')) {
            // Simple fallback validation
            return $data;
        }
        
        $validator = new Validator($data);
        $validator->make($data, $rules);
        
        if (!$validator->validate()) {
            $this->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        return $validator->validated();
    }
    
    protected function validateWithRedirect($data, $rules, $redirectUrl)
    {
        
		if (!class_exists('App\\Core\\Validator')) {
            return $data;
        }
        
        $validator = new Validator($data);
        $validator->make($data, $rules);
        
        if (!$validator->validate()) {
            // Store errors and old input in session
            $_SESSION['validation_errors'] 	= $validator->errors();
            $_SESSION['old_input'] 			= $data;
            
            // Add flash message
            if ($this->globalData && method_exists($this->globalData, 'addFlash')) {
                $this->globalData->addFlash( 'error', 'Please fix the errors below.' );
            }
            
            $this->redirect($redirectUrl);
        }
        
        return $validator->validated();
		
    }
    
    protected function sanitizeInput($input, $rules = [])
    {
        
		$sanitized = [];        
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeValue($value, $rules[$key] ?? '');
            }
        }        
        return $sanitized;
		
    }
    
    private function sanitizeArray($array)
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitizeValue($value);
            }
        }
        
        return $sanitized;
    }
    
    private function sanitizeValue($value, $rule = '')
    {
        if (is_numeric($value)) {
            return $value;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        if ($value === null) {
            return $value;
        }
        
        // Default sanitization
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    protected function getValidationErrors()
    {
        return $_SESSION['validation_errors'] ?? [];
    }
    
    protected function getOldInput($field = null, $default = '')
    {
        if ($field === null) {
            return $_SESSION['old_input'] ?? [];
        }        
        return $_SESSION['old_input'][$field] ?? $default;
    }
    
    protected function setSession($key, $value = '')
    {           
        $_SESSION[$key] = $value;
    }
    
    protected function getSession($key = null, $default = '')
    {     
        if ($key === null) {
            return $_SESSION;
        }        
        return $_SESSION[$key] ?? $default;
    }
    
    protected function sessionExists( $key = null  )
    {           
        return isset( $_SESSION[ $key ] ) ? true:false;
    }
    
    protected function clearValidation()
    {
        unset( $_SESSION[ 'validation_errors' ], $_SESSION[ 'old_input' ] );
    }
    
    protected function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    protected function passwordVerify($password, $hash)
    {
        return password_verify($password, $hash);
    }
    
    protected function isLoggedIn($sessionKey)
    {
        return isset($_SESSION["user_session"][$sessionKey]);
    }

      
    protected function loginUser($sessionKey, $userData )
    {
		
        if (!isset($_SESSION["user_session"][$sessionKey])) {
            $encryptionKey 	= $this->globalData ?  $this->globalData->get( 'app.encryption_key', 'default_key' ) : 'default_key';            
            $loginDataStr 	= "";
			forEach( $userData as $k => $v ){
				if( $loginDataStr 	== "" ){
					$loginDataStr 	= "{$k}:{$v}";
				}else{
					$loginDataStr 	.= ";{$k}:{$v}";
				}
			}					
			$encryptedSessionData 	= $this->AESEncrypt( $loginDataStr, $encryptionKey );            
            $_SESSION["user_session"][$sessionKey] = $encryptedSessionData;
        }
		
    }
	
	protected function getLoginData($sessionKey)
    {
        
		if (!isset($_SESSION["user_session"][$sessionKey])) {
            return [];
        }
        
        $encryptionKey = $this->globalData ? $this->globalData->get( 'app.encryption_key', 'default_key' ) : 'default_key';
        
        $loginData = $this->AESDecrypt(
            $_SESSION["user_session"][$sessionKey], 
            $encryptionKey
        );
		
		
		
		$keyValueArray 			= [];

		$keyValueStringArray 	= explode( ";", $loginData );
		forEach( $keyValueStringArray as $item ){
			$keyVal = explode( ":", $item );
			$keyValueArray[ $keyVal[ 0 ] ] 	= $keyVal[ 1 ];			
		}
		
		
        return $keyValueArray;
		
    }
    
    protected function logoutUser($sessionKey, $redirectUrl = "")
    {
        if (isset($_SESSION["user_session"][$sessionKey])) {
            unset($_SESSION["user_session"][$sessionKey]);
            
            if ($redirectUrl) {
                $this->redirect($redirectUrl);
            }
        }
    }
    

    
    /**
     * Encrypt data using AES-256-CBC
     */
    protected function AESEncrypt($data, $password) 
    {
        // Set a random salt
        $salt = openssl_random_pseudo_bytes(16);

        $salted = '';
        $dx = '';
        // Salt the key(32) and iv(16) = 48
        while (strlen($salted) < 48) {
            $dx = hash('sha256', $dx . $password . $salt, true);
            $salted .= $dx;
        }

        $key = substr($salted, 0, 32);
        $iv  = substr($salted, 32, 16);

        $encrypted_data = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($salt . $encrypted_data);
    }
    
    /**
     * Decrypt AES-256-CBC encrypted data
     */
    protected function AESDecrypt($edata, $password) 
    {
        $data = base64_decode($edata);
        $salt = substr($data, 0, 16);
        $ct = substr($data, 16);

        $rounds = 3; // depends on key length
        $data00 = $password . $salt;
        $hash = array();
        $hash[0] = hash('sha256', $data00, true);
        $result = $hash[0];
        for ($i = 1; $i < $rounds; $i++) {
            $hash[$i] = hash('sha256', $hash[$i - 1] . $data00, true);
            $result .= $hash[$i];
        }
        $key = substr($result, 0, 32);
        $iv  = substr($result, 32, 16);

        return openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
    
    /**
     * Create a URL-friendly slug from a string
     */
    protected function createSlug($string, $separator = '-', $lowercase = true, $language = 'en')
    {
        // Handle empty input
        if (empty($string)) {
            return '';
        }
        
        // Convert to lowercase if requested
        if ($lowercase) {
            $string = mb_strtolower($string, 'UTF-8');
        }
        
        // Transliterate non-ASCII characters
        $string = $this->transliterate($string, $language);
        
        // Replace non-alphanumeric characters with separator
        $string = preg_replace('/[^\p{L}\p{N}]+/u', $separator, $string);
        
        // Trim separators from beginning and end
        $string = trim($string, $separator);
        
        // Remove duplicate separators
        $string = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $string);
        
        return $string;
    }
    
    /**
     * Transliterate international characters to ASCII
     */
    protected function transliterate($string, $language = 'en')
    {
        // If intl extension is available, use Transliterator
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                return $transliterator->transliterate($string);
            }
        }
        
        // Fallback: Manual transliteration map
        $transliterationMap = [
            // German umlauts
            'Ä' => 'Ae', 'ä' => 'ae',
            'Ö' => 'Oe', 'ö' => 'oe',
            'Ü' => 'Ue', 'ü' => 'ue',
            'ß' => 'ss',
            
            // French accents
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
            'Ç' => 'C', 'ç' => 'c',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            
            // Others
            'Å' => 'Aa', 'å' => 'aa',
            'Ø' => 'Oe', 'ø' => 'oe',
            'Æ' => 'Ae', 'æ' => 'ae',
        ];
        
        return strtr($string, $transliterationMap);
    }
    
    protected function isSubdomain($domain)
    {
        // Remove protocol and path
        $domain = parse_url($domain, PHP_URL_HOST) ?? $domain;
        
        // Remove www. prefix
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Split by dots
        $parts = explode('.', $domain);
        
        // A domain needs at least 2 parts to be valid (example.com)
        // If it has 3 or more parts, it's likely a subdomain
        return count($parts) > 2;
    }
	
	
	protected function registerCustomSmartyFunctions()
	{
		
		// Check if smarty is available
		if (!$this->smarty) {
			error_log( 'Smarty is not available when registering custom functions' );
			return;
		}
		
		// Register isLoggedIn function
		$this->smarty->registerPlugin( 'function', 'isLoggedIn', [ $this, 'smartyIsLoggedIn' ] );
		
		// For debugging, let's log what functions are registered
		error_log( 'isLoggedIn function registered with Smarty' );
		
	}
	
	public function smartyIsLoggedIn($params, $smarty = null)
	{
		// Debug: log the parameters
		error_log('smartyIsLoggedIn called with params: ' . print_r($params, true));
		
		// Check if 'type' parameter is passed (from template: isLoggedIn('guest'))
		if (isset($params['type'])) {
			$type = $params['type'];
			
			if ($type === 'guest') {
				// Check for guest session
				return isset($_SESSION['guest_user']) || 
					   (isset($_SESSION['user_session']) && isset($_SESSION['user_session']['guest'])) ||
					   (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'guest');
			} else {
				// Check for specific user type
				return isset($_SESSION['user_session']) && isset($_SESSION['user_session'][$type]);
			}
		}
		
		// Default: check if any user is logged in
		return !empty($_SESSION['user_session']) || isset($_SESSION['guest_user']);
	}
	
	
	protected function getJWTAuthenticatedUser( $userIdKey = 'user_id' )
	{
		
		$headers 	= getallheaders();
		$authHeader = $headers['Authorization'] ?? '';

		if ( empty( $authHeader ) || !preg_match( '/Bearer\s(\S+)/', $authHeader, $matches ) ) {
			$this->json([ 'error' => 'Token not provided' ], 401 );
			exit;
		}

		$token = $matches[1];
		
		try {
			$payload = $this->jwtService->validateToken($token);
			// Fetch and return the full User entity using the ID from the token
			return isset( $payload[ $userIdKey ] ) ? User::find($payload['user_id']): null;
		} catch (RuntimeException $e) {
			$this->json(['error' => $e->getMessage()], 401);
			exit;
		}
		
	}
	
	/**
     * Get validated input from request (POST, GET, or JSON)
     */
    protected function validateRequest($rules, $jsonResponse = true)
    {
        $data = $this->request->input();
        
        if (!class_exists('App\\Core\\Validator')) {
            return $data;
        }
        
        $validator = new Validator($data);
        $validator->make($data, $rules);
        
        if (!$validator->validate()) {
            if ($jsonResponse) {
                $this->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 400);
            }
            return false;
        }
        
        return $validator->validated();
    }

	// 	In a protected endpoint, like BlogController@store
	//	public function store()
	//	{
	//		// Ensure the user is authenticated
	//		$user = $this->getAuthenticatedUser();
	//		
	//		// Now you can use $user->id to associate the new blog post
	//		$rules = ['title' => 'required|string', 'content' => 'required|string'];
	//		$validatedData = $this->validate($_POST, $rules, true);
	//		
	//		$validatedData['user_id'] = $user->id;
	//		// ... create the blog post with the associated user ID
	//	}
	
}
