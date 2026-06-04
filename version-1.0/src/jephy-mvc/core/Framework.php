<?php
namespace App\Core;

use Exception;
use App\Hooks\GlobalDataHook;

class Framework
{
    private static $VERSION;
    private static $instance;
    private $smarty;
    private $hooks;
    private $router;
    private $db;
    private $mailer;
    private $templateHook;
    private $config;
    private $appPath;
    private $viewPath;
    private $appDebugMode;
    private $initialized = false;
    private $globalMiddleware = [];
    
    private function __construct()
    {        
        // Initialize Config first
        $this->config 	= Config::getInstance();
        
        // Get app path from config
        $this->appPath 	= $this->config->get( 'site.app_path', '' );
		$this->viewPath = $this->config->get( 'site.view_path', '' );
        if (empty($this->appPath)) {
            $this->appPath = dirname(__DIR__, 2);
        }
        
        // Get debug mode
        $this->appDebugMode = filter_var(
            $this->config->get('site.app_debug', 'false'), 
            FILTER_VALIDATE_BOOLEAN
        );
        
        // Set error reporting
        if ($this->appDebugMode) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        
        // Load global middleware from config
        $this->loadGlobalMiddleware();
        
        $this->initialize();		
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$VERSION 	= Config::getInstance()->get( "site.version" );
        }
        return self::$instance;
    }
    
    private function loadGlobalMiddleware()
    {
        $middlewareList = $this->config->get('middleware.global', '');
        if (empty($middlewareList)) {
            return;
        }
        
        $middlewareNames = explode(',', $middlewareList);
        foreach ($middlewareNames as $middlewareName) {
            $middlewareName = trim($middlewareName);
            if (empty($middlewareName)) {
                continue;
            }
            $this->addGlobalMiddleware($middlewareName);
        }
    }
    
    public function addGlobalMiddleware($middlewareClass)
    {
        $fullClassName = "App\\Middleware\\" . $middlewareClass;
        if (!class_exists($fullClassName)) {
            if ($this->appDebugMode) {
                error_log("Global middleware not found: {$fullClassName}");
            }
            return;
        }
        $this->globalMiddleware[] = new $fullClassName();
    }
    
    private function runWithMiddleware($request, $callback)
    {
        $pipeline = $this->buildPipeline($this->globalMiddleware, $callback);
        return $pipeline($request);
    }
    
    private function buildPipeline($middlewares, $finalCallback)
    {
        $callback = $finalCallback;
        $middlewares = array_reverse($middlewares);
        
        foreach ($middlewares as $middleware) {
            $callback = function($request) use ($middleware, $callback) {
                return $middleware->handle($request, $callback);
            };
        }
        
        return $callback;
    }
    
    private function initialize()
    {
        if ($this->initialized) {
            return;
        }
        
        // Initialize Smarty first
        $this->initializeSmarty();
        
        // Initialize Hook Manager
        $this->hooks 		= new HookManager();
        
        // Initialize Router (needs hooks)
        $this->router 		= new Router($this->hooks);
        
        // Initialize Database
        $this->db 			= Database::getInstance();
        
        // Initialize Template Hook (needs hooks)
        $this->templateHook = new TemplateHook($this->hooks);
        
        // Register Smarty plugins
        $this->registerSmartyPlugins();
        
        // Load core hooks
        $this->loadCoreHooks();        
        $this->initialized = true;
		
    }
    
    private function initializeSmarty()
    {
        
		if ($this->smarty !== null) {
            return;
        }
        
        $this->smarty = new \Smarty();
        
        /// Configure Smarty paths
		if( $this->viewPath != "" ){
			$this->smarty->setTemplateDir( $this->viewPath );
		}else{
			$this->smarty->setTemplateDir( $this->appPath . '/views/' );
		}
		
		$compilePath 	= $this->config->get( 'smarty.compile_dir', $this->appPath . '/cache/views_c/' );
		$cachePath 		= $this->config->get( 'smarty.cache_dir', $this->appPath . '/cache/cache/' );
		
        $this->smarty->setCompileDir( $compilePath );
        $this->smarty->setCacheDir( $cachePath );
        $this->smarty->setConfigDir( $this->appPath . '/config/' );
        
        // Smarty configuration
        $this->smarty->caching 			= $this->config->get( 'smarty.caching', false );
        $this->smarty->cache_lifetime 	= $this->config->get( 'smarty.cache_lifetime', 3600 );
        $this->smarty->debugging 		= $this->config->get( 'site.app_debug', "false" ) == "false"? false:true;
        
        // Force compile in development
        $this->smarty->force_compile = $this->appDebugMode;
        
        $sessionKeys = explode("|", $this->config->get('session.keys', ''));
        $sessionKeys = array_map(function($key){
            return trim($key);
        }, $sessionKeys);
        
        $guestIsLoggedIn = isset($_SESSION["user_session"][$this->config->get('session.guest', '')]) && 
                           ($_SESSION["user_session"][$this->config->get('session.guest', '')] != null || 
                            $_SESSION["user_session"][$this->config->get('session.guest', '')] != "") ? true : false;
        $adminIsLoggedIn = isset($_SESSION["user_session"][$this->config->get('session.admin', '')]) && 
                           ($_SESSION["user_session"][$this->config->get('session.admin', '')] != null || 
                            $_SESSION["user_session"][$this->config->get('session.admin', '')] != "") ? true : false;
        
        // Create compile directory if it doesn't exist
        $compileDir = $this->appPath . '/cache/views_c/';
        if (!is_dir($compileDir)) {
            mkdir($compileDir, 0755, true);
        }
        
        $this->smarty->assign("guestIsLoggedIn", $guestIsLoggedIn);
        $this->smarty->assign("adminIsLoggedIn", $adminIsLoggedIn);
        
        for($i = 0; $i < count($sessionKeys); $i++){
            $session = isset($_SESSION["user_session"][$sessionKeys[$i]]) && 
                       ($_SESSION["user_session"][$sessionKeys[$i]] != null || 
                        $_SESSION["user_session"][$sessionKeys[$i]] != "") ? true : false;
            $this->smarty->assign("session".ucfirst($sessionKeys[$i]), $session);
        }
    }
    
    private function registerSmartyPlugins()
    {
        $this->smarty->registerPlugin('function', 'hook', [$this, 'smartyHookFunction']);
        $this->smarty->registerPlugin('block', 'hookblock', [$this, 'smartyHookBlock']);		
		// Also register as a modifier if needed
		$this->smarty->registerPlugin('modifier', 'hook', [$this, 'smartyHookModifier']);
    }
    
	public function smartyHookFunction($params, $smarty)
	{
		if (!isset($params['name'])) {
			return '';
		}
		
		$hookName = $params['name'];
		$hookParams = isset($params['params']) ? $params['params'] : [];
		
		// If hookParams is a string, parse it
		if (is_string($hookParams)) {
			parse_str($hookParams, $hookParams);
		}
		
		// Ensure hookParams is an array
		if (!is_array($hookParams)) {
			$hookParams = [];
		}
		
		try {
			$result = $this->templateHook->display($hookName, $hookParams);
			return $result ?? '';
		} catch (\Exception $e) {
			error_log("Hook error: " . $e->getMessage());
			return '';
		}
	}

	public function smartyHookBlock($params, $content, $smarty, &$repeat)
	{
		if (!$repeat) {
			$hookName = $params['name'] ?? '';
			
			if (empty($hookName)) {
				return $content ?? '';
			}
			
			$hookParams = $params['params'] ?? [];
			
			// If hookParams is a string, parse it
			if (is_string($hookParams)) {
				parse_str($hookParams, $hookParams);
			}
			
			// Ensure hookParams is an array
			if (!is_array($hookParams)) {
				$hookParams = [];
			}
			
			$hookParams['content'] = $content ?? '';
			
			try {
				$results = $this->hooks->execWithReturn($hookName, $hookParams);
				$output = implode('', $results);
				return $output ?? '';
			} catch (\Exception $e) {
				error_log("Hook block error: " . $e->getMessage());
				return $content ?? '';
			}
		}
		return '';
	}
	
	
	public function smartyHookModifier($value, $hookName)
	{
		// This is an optional modifier for {$value|hook:'hookName'}
		try {
			$result = $this->hooks->exec($hookName, [$value]);
			return $result[0] ?? $value;
		} catch (\Exception $e) {
			return $value;
		}
	}


    private function loadCoreHooks()
    {
        $hookPath = $this->appPath . '/app/hooks/';
        
        if (!is_dir($hookPath)) {
            $hookPath = $this->appPath . '/hooks/';
        }
        
        if (is_dir($hookPath)) {
            $hookFiles = glob($hookPath . '*.php');
            
            foreach ($hookFiles as $hookFile) {
                try {
                    require_once $hookFile;
                    
                    $className = pathinfo($hookFile, PATHINFO_FILENAME);
                    $fullClassName = "App\\Hooks\\" . $className;
                    
                    if (class_exists($fullClassName)) {
                        if ($fullClassName === 'App\\Hooks\\GlobalDataHook') {
                            $hookInstance = GlobalDataHook::getInstance();
                            if (method_exists($hookInstance, 'initialize')) {
                                $hookInstance->initialize();
                            }
                        } else {
                            $hookInstance = new $fullClassName();
                        }
                        
                        if (method_exists($hookInstance, 'registerHooks')) {
                            $hookInstance->registerHooks($this->hooks);
                        }
                    }
                } catch (\Exception $e) {
                    error_log("Failed to load hook file {$hookFile}: " . $e->getMessage());
                }
            }
        }
        
        $this->initializeGlobalDataHook();
    }
    
    private function initializeGlobalDataHook()
    {
        try {
            $globalDataHook = GlobalDataHook::getInstance();
            if (method_exists($globalDataHook, 'initializeGlobalData')) {
                $globalDataHook->initializeGlobalData();
            }
            if (method_exists($globalDataHook, 'registerHooks')) {
                $globalDataHook->registerHooks($this->hooks);
            }
        } catch (\Exception $e) {
            error_log("Failed to initialize GlobalDataHook: " . $e->getMessage());
        }
    }
    
    public function run()
    {
        try {
            $request = $this->createRequestObject();
            
            $response = $this->runWithMiddleware($request, function($request) {
                return $this->handleRequest($request);
            });
            
            if (is_string($response)) {
                echo $response;
            } elseif ($response instanceof Response) {
                $response->send();
            }
            
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function createRequestObject()
    {
        $request = new \stdClass();
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $request->path = parse_url($request->uri, PHP_URL_PATH);
        $request->query = $_GET;
        $request->post = $_POST;
        $request->headers = getallheaders();
        $request->ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $request->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return $request;
    }
    
    private function handleRequest($request)
    {
        $uri = $request->path;
        $method = $request->method;
        
        if ($this->appDebugMode) {
            error_log("Routing: URI='{$uri}', Method='{$method}'");
        }
        
        // Get route from router
        $route = $this->router->route($uri, $method);
        
        // Check if route was found
        if (!$route || ($route['type'] ?? '') === 'not_found') {
            if ($this->appDebugMode) {
                error_log("Route not found: {$uri}");
            }
            return $this->handleNotFound($request);
        }
        
        // Check if route has valid handler
        if (!isset($route['handler']) || $route['handler'] === null) {
            if ($this->appDebugMode) {
                error_log("Route missing handler");
            }
            return $this->handleNotFound($request);
        }
        
        // Determine handler type
        $handlerType = $this->determineRouteType($route);
        
        if ($this->appDebugMode) {
            error_log("Route found - Type: {$handlerType}");
            error_log("Route handler: " . (is_string($route['handler']) ? $route['handler'] : 'Closure'));
        }
        
        // Handle different handler types
        if ($handlerType === 'closure') {
            return $this->handleClosure($route, $request);
        }
        
        if ($handlerType === 'controller') {
            return $this->handleController($route, $request);
        }
        
        // If we get here, handler type is unknown
        throw new Exception("Invalid route handler type: " . print_r($route, true));
    }
    
    private function determineRouteType($route)
    {
        // Check route type from router
        if (isset($route['type'])) {
            if ($route['type'] === 'closure' || $route['type'] === 'callable' || $route['type'] === 'Closure') {
                return 'closure';
            }
            if ($route['type'] === 'instance_method' || $route['type'] === 'static_method' || 
                $route['type'] === 'callable_array' || $route['type'] === 'static_callable') {
                return 'controller';
            }
        }
        
        // Check if handler is callable
        if (is_callable($route['handler'])) {
            return 'closure';
        }
        
        // Check if handler is a string with controller syntax
        if (is_string($route['handler'])) {
            if (strpos($route['handler'], '@') !== false || strpos($route['handler'], '::') !== false) {
                return 'controller';
            }
        }
        
        // Check for array handler
        if (is_array($route['handler']) && count($route['handler']) === 2) {
            return 'controller';
        }
        
        return 'unknown';
    }
    
    private function handleClosureAlt($route, $request)
    {
        $handler = $route['handler'];
        $params = $route['params'] ?? [];
        
        if (!is_callable($handler)) {
            throw new Exception("Route handler is not callable");
        }
        
        if ($this->appDebugMode) {
            error_log("Executing closure route with params: " . print_r($params, true));
        }
        
        $this->hooks->exec('beforeController', [
            'type' => 'closure',
            'params' => $params
        ]);
        
        ob_start();
        
        try {
            $reflection = new \ReflectionFunction($handler);
            $reflectionParams = $reflection->getParameters();
            $callParams = [];
            
            foreach ($reflectionParams as $param) {
                $paramName = $param->getName();
                
                if ($paramName === 'request') {
                    $callParams[] = $request;
                } elseif (isset($params[$paramName])) {
                    $callParams[] = $params[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $callParams[] = $param->getDefaultValue();
                } else {
                    $callParams[] = null;
                }
            }
            
            call_user_func_array($handler, $callParams);
            
        } catch (\ArgumentCountError $e) {
            if ($this->appDebugMode) {
                error_log("Closure argument error: " . $e->getMessage() . ", calling without params");
            }
            $handler();
        } catch (\TypeError $e) {
            if ($this->appDebugMode) {
                error_log("Closure type error: " . $e->getMessage());
            }
            $handler();
        }
        
        $content = ob_get_clean();
        return $content;
    }
	
	
	
	private function handleClosure($route, $request)
	{
		$handler = $route['handler'];
		$params = $route['params'] ?? [];
		
		if (!is_callable($handler)) {
			throw new Exception("Route handler is not callable");
		}
		
		if ($this->appDebugMode) {
			error_log("Executing closure route with params: " . print_r($params, true));
		}
		
		// ===== INJECT GLOBAL DATA BEFORE CLOSURE EXECUTION =====
		try {
			$globalDataHook = \App\Hooks\GlobalDataHook::getInstance();
			$globalDataHook->forceInjectToSmarty();
		} catch (\Exception $e) {
			error_log("Failed to inject global data in closure: " . $e->getMessage());
		}
		
		// Execute before controller hook
		$this->hooks->exec('beforeController', [
			'type' => 'closure',
			'params' => $params
		]);
		
		ob_start();
		
		try {
			$reflection = new \ReflectionFunction($handler);
			$reflectionParams = $reflection->getParameters();
			$callParams = [];
			
			foreach ($reflectionParams as $param) {
				$paramName = $param->getName();
				
				if ($paramName === 'request') {
					$callParams[] = $request;
				} elseif (isset($params[$paramName])) {
					$callParams[] = $params[$paramName];
				} elseif ($param->isDefaultValueAvailable()) {
					$callParams[] = $param->getDefaultValue();
				} else {
					$callParams[] = null;
				}
			}
			
			$result = call_user_func_array($handler, $callParams);
			
			// If the closure returns a string (like view() function), use it
			if (is_string($result) && !empty($result)) {
				ob_clean();
				return $result;
			}
			
		} catch (\ArgumentCountError $e) {
			if ($this->appDebugMode) {
				error_log("Closure argument error: " . $e->getMessage() . ", calling without params");
			}
			$result = $handler();
			if (is_string($result) && !empty($result)) {
				ob_clean();
				return $result;
			}
		} catch (\Exception $e) {
			if ($this->appDebugMode) {
				error_log("Closure execution error: " . $e->getMessage());
			}
			throw $e;
		}
		
		$content = ob_get_clean();
		
		return $content !== false ? $content : '';
	}




	private function handleController($route, $request)
	{
		$params = $route['params'] ?? [];
		$controllerInstance = null;
		$actionName = null;
		$isStatic = false;
		$controllerName = null;
		
		// Handle different controller formats
		if (isset($route['controller']) && isset($route['action'])) {
			// Pre-parsed controller and action
			$controllerName = $route['controller'];
			$actionName = $route['action'];
			$isStatic = $route['isStatic'] ?? false;
			
			if (!$isStatic) {
				$controllerInfo = $this->loadControllerFile($controllerName);
				$controllerInstance = $controllerInfo['instance'];
				$controllerName = $controllerInfo['class'];
			}
			
		} elseif (is_array($route['handler']) && count($route['handler']) === 2) {
			// Array handler [controller, method]
			if (is_object($route['handler'][0])) {
				$controllerInstance = $route['handler'][0];
				$actionName = $route['handler'][1];
				$isStatic = false;
			} else {
				$controllerName = $route['handler'][0];
				$actionName = $route['handler'][1];
				$isStatic = true;
			}
			
		} elseif (is_string($route['handler'])) {
			// Parse string handler
			if (strpos($route['handler'], '::') !== false) {
				$parts = explode('::', $route['handler']);
				$controllerName = $parts[0];
				$actionName = $parts[1];
				$isStatic = true;
				
			} elseif (strpos($route['handler'], '@') !== false) {
				$parts = explode('@', $route['handler']);
				$controllerName = $parts[0];
				$actionName = $parts[1];
				$isStatic = false;
				
				$controllerInfo = $this->loadControllerFile($controllerName);
				$controllerInstance = $controllerInfo['instance'];
				$controllerName = $controllerInfo['class'];
				
			} else {
				throw new Exception("Invalid controller handler format: {$route['handler']}");
			}
		} else {
			throw new Exception("Invalid controller handler format");
		}
		
		if ($this->appDebugMode) {
			error_log("Executing controller: " . ($controllerName ?? get_class($controllerInstance)) . "->{$actionName}");
		}
		
		// Check if method exists
		if ($isStatic) {
			if (!method_exists($controllerName, $actionName)) {
				throw new Exception("Static method '{$actionName}' not found in '{$controllerName}'");
			}
		} else {
			if (!method_exists($controllerInstance, $actionName)) {
				throw new Exception("Method '{$actionName}' not found in controller");
			}
		}
		
		// ===== INJECT GLOBAL DATA BEFORE CONTROLLER EXECUTION =====
		try {
			$globalDataHook = \App\Hooks\GlobalDataHook::getInstance();
			$globalDataHook->forceInjectToSmarty();
		} catch (\Exception $e) {
			error_log("Failed to inject global data in controller: " . $e->getMessage());
		}
		
		// Execute before controller hook
		$this->hooks->exec('beforeController', [
			'controller' => $controllerName ?? get_class($controllerInstance),
			'action' => $actionName,
			'params' => $params
		]);
		
		ob_start();
		
		try {
			if ($isStatic) {
				$reflection = new \ReflectionMethod($controllerName, $actionName);
				$methodParams = $reflection->getParameters();
				$callParams = [];
				
				foreach ($methodParams as $param) {
					$paramName = $param->getName();
					
					if ($paramName === 'request') {
						$callParams[] = $request;
					} elseif (isset($params[$paramName])) {
						$callParams[] = $params[$paramName];
					} elseif ($param->isDefaultValueAvailable()) {
						$callParams[] = $param->getDefaultValue();
					} else {
						$callParams[] = null;
					}
				}
				
				call_user_func_array([$controllerName, $actionName], $callParams);
			} else {
				$reflection = new \ReflectionMethod($controllerInstance, $actionName);
				$methodParams = $reflection->getParameters();
				$callParams = [];
				
				foreach ($methodParams as $param) {
					$paramName = $param->getName();
					
					if ($paramName === 'request') {
						$callParams[] = $request;
					} elseif (isset($params[$paramName])) {
						$callParams[] = $params[$paramName];
					} elseif ($param->isDefaultValueAvailable()) {
						$callParams[] = $param->getDefaultValue();
					} else {
						$callParams[] = null;
					}
				}
				
				call_user_func_array([$controllerInstance, $actionName], $callParams);
			}
			
		} catch (\ArgumentCountError $e) {
			if ($this->appDebugMode) {
				error_log("Controller argument error: " . $e->getMessage() . ", trying without params");
			}
			if ($isStatic) {
				$controllerName::$actionName();
			} else {
				$controllerInstance->$actionName();
			}
		} catch (\Exception $e) {
			if ($this->appDebugMode) {
				error_log("Controller execution error: " . $e->getMessage());
			}
			throw $e;
		}
		
		$content = ob_get_clean();
		
		if (empty($content) && !$this->appDebugMode) {
			error_log("Controller produced no output: " . ($controllerName ?? get_class($controllerInstance)) . "->{$actionName}");
		}
		
		return $content !== false ? $content : '';
	}
	
    
    private function loadControllerFile($controllerName)
    {
        $originalName = $controllerName;
        
        // Try different naming conventions
        $possibleFiles = [
            $this->appPath . "/controllers/{$controllerName}.php",
            $this->appPath . "/controllers/{$controllerName}Controller.php"
        ];
        
        $loadedFile = null;
        foreach ($possibleFiles as $file) {
            if (file_exists($file)) {
                $loadedFile = $file;
                break;
            }
        }
        
        if (!$loadedFile) {
            throw new Exception("Controller file not found for: {$originalName}");
        }
        
        require_once $loadedFile;
        
        // Determine the class name
        $possibleClasses = [
            "App\\Controllers\\" . $controllerName,
            "App\\Controllers\\" . $controllerName . "Controller"
        ];
        
        $controllerClass = null;
        foreach ($possibleClasses as $class) {
            if (class_exists($class)) {
                $controllerClass = $class;
                break;
            }
        }
        
        if (!$controllerClass) {
            throw new Exception("Controller class not found for: {$originalName}");
        }
        
        return [
            'instance' => new $controllerClass(),
            'class' => $controllerClass
        ];
    }
	
	private function handleNotFound($request)
	{
		http_response_code(404);
		
		// Execute 404 hook with request data
		$this->hooks->exec('onNotFound', [
			'uri' => $request->path,
			'method' => $request->method,
			'request' => $request
		]);
		
		// Also execute beforeRender hook to inject global data
		$this->hooks->exec('beforeRender', [
			'template' => 'errors/404.tpl',
			'data' => []
		]);
		
		// Check if there's a custom 404 view
		$notFoundView = $this->appPath . '/views/errors/404.tpl';
		if (file_exists($notFoundView) && $this->smarty) {
			// Assign global data from GlobalDataHook before rendering
			try {
				$globalDataHook = GlobalDataHook::getInstance();
				$globalDataHook->initialize();
				$globalData = $globalDataHook->getAllData();
				
				foreach ($globalData as $key => $value) {
					$this->smarty->assign($key, $value);
				}
			} catch (\Exception $e) {
				error_log("Failed to load global data for 404: " . $e->getMessage());
			}
			
			// Also assign 404 specific data
			$this->smarty->assign('requested_uri', $request->path);
			$this->smarty->assign('requested_method', $request->method);
			$this->smarty->assign('error_code', 404);
			$this->smarty->assign('error_message', 'Page Not Found');
			
			$this->smarty->display('errors/404.tpl');
			return '';
		}
		
		// Return JSON for API requests
		$isApiRequest = strpos($request->path, '/api/') === 0;
		if ($isApiRequest) {
			header('Content-Type: application/json');
			echo json_encode([
				'error' => 'Not Found',
				'message' => 'The requested resource was not found',
				'path' => $request->path,
				'method' => $request->method
			]);
			return '';
		}
		
		// Default HTML 404
		return '<!DOCTYPE html>
		<html>
		<head>
			<title>404 - Page Not Found</title>
			<style>
				body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
				h1 { font-size: 50px; color: #333; }
				p { color: #666; }
				.info { background: #f5f5f5; padding: 10px; margin-top: 20px; font-family: monospace; }
			</style>
		</head>
		<body>
			<h1>404</h1>
			<h2>Page Not Found</h2>
			<p>The requested URL <strong>' . htmlspecialchars($request->path) . '</strong> was not found on this server.</p>
			<div class="info">
				Method: ' . htmlspecialchars($request->method) . '<br>
				URI: ' . htmlspecialchars($request->uri) . '
			</div>
			<p><a href="/">Return to Home</a></p>
		</body>
		</html>';
	}
	
	
    private function handleError(Exception $e)
    {
        $this->hooks->exec('onError', [
            'exception' => $e,
            'uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        
        http_response_code(500);
        
        if ($this->appDebugMode) {
            echo "<h1>Error</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
            echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            echo "An error occurred. Please try again later.";
        }
        
        error_log("Framework Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }
    
    private function getMailerInstance()
    {
        if ($this->mailer === null) {
            $this->mailer = new Mailer();
        }
        return $this->mailer;
    }
    
    // Public getters
    public function getSmartyInstance() { return $this->smarty; }
    public function getHooksInstance() { return $this->hooks; }
    public function getRouterInstance() { return $this->router; }
    public function getDatabaseInstance() { return $this->db; }
    public function getTemplateHookInstance() { return $this->templateHook; }
    public function getMailerInstancePublic() { return $this->getMailerInstance(); }
    public function getConfig() { return $this->config; }
    public function getAppPath() { return $this->appPath; }
    
    // Static getters
    public static function getSmarty() { return self::getInstance()->getSmartyInstance(); }
    public static function getHooks() { return self::getInstance()->getHooksInstance(); }
    public static function getRouter() { return self::getInstance()->getRouterInstance(); }
    public static function getDatabase() { return self::getInstance()->getDatabaseInstance(); }
    public static function getTemplateHook() { return self::getInstance()->getTemplateHookInstance(); }
    public static function getMailer() { return self::getInstance()->getMailerInstancePublic(); }
    public static function getConfigStatic() { return self::getInstance()->getConfig(); }
    public static function getAppPathStatic() { return self::getInstance()->getAppPath(); }
    
    public static function getGlobalDataHook()
    {
        return GlobalDataHook::getInstance();
    }
    
    public static function debug()
    {
        $instance = self::getInstance();
        return [
            'initialized' => $instance->initialized,
            'smarty' => $instance->smarty ? get_class($instance->smarty) : null,
            'appPath' => $instance->appPath,
            'debugMode' => $instance->appDebugMode
        ];
    }
}

