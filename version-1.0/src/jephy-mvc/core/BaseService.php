<?php
// jephy-mvc/core/BaseService.php
namespace App\Core;

use App\Core\Config;
use App\Core\HookManager;

abstract class BaseService
{
    /**
     * @var Config Configuration instance
     */
    protected $config;
    
    /**
     * @var HookManager Hook manager instance
     */
    protected $hookManager;
    
    /**
     * @var array Service configuration
     */
    protected $settings = [];
    
    /**
     * @var bool Enable debug logging for this service
     */
    protected $debug = false;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config 		= Config::getInstance();
        $this->hookManager 	= $this->getHookManager();
        $this->debug 		= $this->config->get('app.debug', false);
        $this->initialize();
    }
    
    /**
     * Initialize the service (override in child classes)
     */
    protected function initialize()
    {
        // Override in child classes
    }
    
    /**
     * Get HookManager instance
     */
    protected function getHookManager()
    {
        global $hookManager;
        
        if ($hookManager instanceof HookManager) {
            return $hookManager;
        }
        
        return new HookManager();
    }
    
    /**
     * Log debug message
     */
    protected function log($message, $context = [])
    {
        if ($this->debug) {
            $className = get_called_class();
            $logMessage = "[{$className}] {$message}";
            
            if (!empty($context)) {
                $logMessage .= " | " . json_encode($context);
            }
            
            error_log($logMessage);
        }
    }
    
    /**
     * Get configuration value
     */
    protected function config($key, $default = null)
    {
        return $this->config->get($key, $default);
    }
    
    /**
     * Validate required configuration
     */
    protected function validateConfig($requiredKeys)
    {
        foreach ($requiredKeys as $key) {
            if (!$this->config->has($key)) {
                throw new \RuntimeException("Missing required configuration: {$key}");
            }
        }
    }
    
    /**
     * Execute with error handling
     */
    protected function tryExecute(callable $callback, $errorMessage = 'Operation failed')
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            $this->log($errorMessage . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
