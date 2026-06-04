<?php
namespace App\Hooks;

use App\Core\{ Framework, Encryption, HookManager, QueryBuilder, Config, ShoppingCart };
use App\Entities\WishList;
use App\Classes\{ HomePageSetting, Compare };

class GlobalDataHook
{
    private static $instance = null;
    private $globalData = [];
    private $mainCategories = [];
    private $productCategories = [];
    private $initialized 	= false;
    private $encryptionKey 	= null;
    private $config 		= null;
    
    private function __construct()
    {
        // Private constructor for singleton
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Public method to initialize data
     */
    public function initialize()
    {
        if (!$this->initialized) {
            $this->initializeGlobalData();
            $this->initialized = true;
        }
        return $this;
    }
    
    /**
     * This method is called by Framework to register hooks
     */
    public function registerHooks(HookManager $hooks)
    {
        // Register hooks at priority 1 to ensure they run first
        $hooks->registerHook( 'beforeController', [ $this, 'loadGlobalData' ], 1 );
        $hooks->registerHook( 'beforeRender', [ $this, 'injectTemplateData' ], 1 );
    }
    
    /**
     * Initialize global data (private)
     */
    public function initializeGlobalData()
    {
        if (!empty($this->globalData)) {
            return; // Already initialized
        }
        
        try {
			
			$this->config 			= Config::getInstance();
			
            // Basic configuration
            $this->globalData = [
                'app' => [
					'name' 		=> $this->config->get( 'site.name', '' ),
					'version' 	=> $this->config->get( 'site.version', '' ),
					'year' 		=> date('Y'),
					'form_validation_code' 	=> $this->generateRandomCode( 21 ),                
					'encryption_key' 		=> $this->config->get( 'site.encryption_key', '' ),             
					'public' 				=> $this->config->get( 'site.home_root_path', '' )           
				],
				'site' => [
					'author' 		=> [
						'fullname' 	=> $this->config->get( 'author.fullname', '' ),
						'email' 	=> $this->config->get( 'author.email', '' ),
					],
					'title' 		=> $this->config->get( 'site.title', '' ),
					'name' 			=> $this->config->get( 'site.name', '' ),
					'description' 	=> $this->config->get( 'site.description', '' ),
					'keywords' 		=> $this->config->get( 'site.keywords', '' ),
					'phone' 		=> $this->config->get( 'site.phone', '' ),
					'email' 		=> $this->config->get( 'site.email', '' ),
					'address' 		=> $this->config->get( 'site.address', '' ),
					'url' => [
						'home' 		=> $this->config->get( 'site.url.home', '' ),
						'third_party_platform' 	=> [
							'github' 	=> $this->config->get( 'site.url.third_party_platform.github', '' )
						]
					]
				]
            ];
            
        } catch (\Exception $e) {
			
            error_log("GlobalDataHook initialization error: " . $e->getMessage());            
            // Set defaults
            $this->globalData = [
                'app' => ['name' => 'Jephy MVC', 'version' => '1.0.0', 'year' => date('Y')],
                'site' => ['author' => 'Jephy MVC', 'title' => 'Jephy MVC PHP framework'],                
            ];
			
        }
		
    }
    
    /**
     * Load global data before controller execution
     * Now accepts a single array parameter
     */
    public function loadGlobalData($params)
    {
        // $params should be an array with: ['controller', 'action', 'routeParams']
        if (!is_array($params)) {
            // If it's not an array, wrap it
            $params = [$params];
        }
        
        // Ensure data is initialized
        $this->initialize();
        
        // Load user data from session
        $this->loadUserData();
        
        // Load flash messages
        $this->loadFlashMessages();
        
        // Return unchanged params
        return $params;
    }
    
    /**
     * Inject template data before rendering
     * Now accepts a single array parameter
     */
	
	public function injectTemplateData($params)
	{
		// Ensure data is initialized
		$this->initialize();
		
		// Always inject data, even if params is not array
		try {
			$smarty = Framework::getSmarty();
			
			if ($smarty === null) {
				error_log("Smarty not available in injectTemplateData");
				return $params;
			}
			
			// Load user data (always refresh)
			$this->loadUserData();
			
			// Load flash messages
			$this->loadFlashMessages();
			
			// Inject all global data into Smarty
			$globalData = $this->getAllData();
			
			foreach ($globalData as $key => $value) {
				$smarty->assign($key, $value);
			}
			
			// Also inject individual variables for easier access
			if (isset($globalData['site'])) {
				$smarty->assign('site', $globalData['site']);
			}
			
			if (isset($globalData['app'])) {
				$smarty->assign('app', $globalData['app']);
			}
			
			if (isset($globalData['auth'])) {
				$smarty->assign('auth', $globalData['auth']);
			}
			
			if (isset($globalData['flash_messages'])) {
				$smarty->assign('flash_messages', $globalData['flash_messages']);
			}
			
		} catch (\Exception $e) {
			error_log("Error injecting template data: " . $e->getMessage());
		}
		
		return $params;
	}

	/**
	 * Force inject data into Smarty (useful for error pages and direct view calls)
	 */
	public function forceInjectToSmarty()
	{
		$this->initialize();
		$this->injectTemplateData([]);
	}
    private function loadUserData()
    {
        if (isset($_SESSION['user_id'])) {
            $this->globalData['auth']['is_logged_in'] = true;
            $this->globalData['auth']['user'] = $_SESSION['user'] ?? [
                'id' => $_SESSION['user_id'], 
                'name' => 'User'
            ];
        } else {
            $this->globalData['auth']['is_logged_in'] = false;
            $this->globalData['auth']['user'] = null;
        }
    }
    
    private function loadFlashMessages()
    {
        if (isset($_SESSION['flash_messages'])) {
            $this->globalData['flash_messages'] = $_SESSION['flash_messages'];
            unset($_SESSION['flash_messages']);
        } else {
            $this->globalData['flash_messages'] = [];
        }
    }
    
    private function getMainMenu()
    {
        return [
            [ 'title' => 'Home', 'url' => '/', 'icon' => '🏠' ],
            [ 'title' => 'Products', 'url' => '/products', 'icon' => '📦' ],
            [ 'title' => 'About', 'url' => '/about', 'icon' => 'ℹ️' ],
            [ 'title' => 'Contact', 'url' => '/contact', 'icon' => '📞' ],
        ];
    }
    
    /**
     * Get all global data
     */
    public function getAllData()
    {
        $this->initialize();
        return $this->globalData;
    }
    
    /**
     * Get specific data by key
     */
    public function get($key, $default = null)
    {
        $this->initialize();
        
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $this->globalData;
            
            foreach ($keys as $k) {
                if (is_array($value) && isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return $default;
                }
            }
            
            return $value;
        }
        
        return $this->globalData[$key] ?? $default;
    }
    
    /**
     * Update global data
     */
    public function set($key, $value)
    {
        $this->initialize();
        
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $data = &$this->globalData;
            
            foreach ($keys as $k) {
                if (!isset($data[$k]) || !is_array($data[$k])) {
                    $data[$k] = [];
                }
                $data = &$data[$k];
            }
            
            $data = $value;
        } else {
            $this->globalData[$key] = $value;
        }
        
        // Update Smarty if available
        $this->updateSmarty();
    }
    
    /**
     * Update Smarty with current global data
     */
    private function updateSmarty()
    {
        try {
            if (class_exists('App\Core\Framework')) {
                $smarty = Framework::getSmarty();
                if ($smarty !== null) {
                    // Update top-level assignments
                    foreach ($this->globalData as $key => $value) {
                        $smarty->assign($key, $value);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }
    
    /**
     * Add flash message
     */
    public static function addFlash($type, $message)
    {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = [
            'type' => $type, 
            'message' => $message,
            'timestamp' => time()
        ];
    }
	
	public function generateRandomCode($length = 6) 
	{
		
		$characters 		= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength 	= strlen($characters);
		$randomCode 		= '';
		
		for ($i = 0; $i < $length; $i++) {
			$randomCode .= $characters[random_int(0, $charactersLength - 1)];
		}
		
		return $randomCode;
		
	}
	
	    
    protected function getLoginData( $sessionKey )
    {
        
		if (!isset($_SESSION["user_session"][$sessionKey])) {
            return [];
        }
		
        $loginData = Encryption::AESDecrypt(
            $_SESSION[ "user_session" ][ $sessionKey ], 
            $this->encryptionKey
        );
		
		$keyValueArray 			= [];

		$keyValueStringArray 	= explode( ";", $loginData );
		forEach( $keyValueStringArray as $item ){
			$keyVal 						= explode( ":", $item );
			$keyValueArray[ $keyVal[ 0 ] ] 	= $keyVal[ 1 ];			
		}		
		
        return $keyValueArray;
		
    }
	
	
}