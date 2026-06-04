<?php
namespace App\Core;

class Config {
	
    private static $instance 	= null;
    private $config 			= [];
    private $configFilePath;
    
    private function __construct() {
        // Set the path to config file
        $this->configFilePath = dirname(__DIR__) . '/config.conf';
        $this->loadConfig();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load configuration from file
     */
    private function loadConfig() {
        if (!file_exists($this->configFilePath)) {
            throw new \RuntimeException("Config file not found: " . $this->configFilePath);
        }
        
		$configFilePath = str_replace( "\\", "/", $this->configFilePath );
		#	die( $configFilePath );
        $content = file_get_contents( $configFilePath );
		
        // Parse the config file based on format
        if (strpos($content, '{') === 0) {
            // JSON format
            $this->config = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON in config file: " . json_last_error_msg());
            }
        } else {
            // Assume INI format or custom format
            $this->parseCustomConfig($content);
        }
    }
    
    /**
     * Parse custom config format (modify based on your config.conf format)
     */
    private function parseCustomConfig($content) {
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
			
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if ( strpos( $line, '=' ) !== false ) {
                list( $key, $value ) = explode( '=', $line, 2 );
                $key 	= trim($key);
                $value 	= trim($value);
                
                // Handle arrays: key[]=value
                if (preg_match('/^(\w+)\[\]$/', $key, $matches)) {
                    $this->config[$matches[1]][] = $value;
                } 
                // Handle nested: section.key=value
                elseif (strpos($key, '.') !== false) {
                    $this->setNestedValue($key, $value);
                }
                // Simple key=value
                else {
                    $this->config[$key] = $value;
                }
            }
        }
    }
    
    /**
     * Set nested config value using dot notation
     */
    private function setNestedValue($key, $value) 
	{
		
        $keys 		= explode( '.', $key );
        $current 	= &$this->config;        
        foreach ( $keys as $k ) 
		{
            if ( !isset( $current[$k] ) || !is_array( $current[$k] ) ) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }        
        $current = $value;
		
    }
    
    /**
     * Get config value using dot notation
     * Example: get('database.host') or get('mail.from.address')
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set config value (runtime, not saved to file)
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $current = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    /**
     * Check if config key exists
     */
    public function has($key) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }
        
        return true;
    }
    
    /**
     * Get all config
     */
    public function all() {
        return $this->config;
    }
    
    /**
     * Reload config from file
     */
    public function reload() {
        $this->loadConfig();
        return $this;
    }
    
    /**
     * Get config file path
     */
    public function getConfigFilePath() {
        return $this->configFilePath;
    }
}

