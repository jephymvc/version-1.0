<?php
namespace App\Core;
// jephy-mvc/core/ServiceProvider.php

use App\Core\Config;
use App\Core\HookManager;

abstract class ServiceProvider
{
    /**
     * @var Container The service container instance
     */
    protected $container;
    
    /**
     * @var Config Configuration instance
     */
    protected $config;
    
    /**
     * @var HookManager Hook manager instance
     */
    protected $hookManager;
    
    /**
     * @var bool Whether the provider is deferred (loaded on demand)
     */
    protected $deferred = false;
    
    /**
     * @var array The services provided by this provider
     */
    protected $provides = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->container 	= Container::getInstance();
        $this->config 		= Config::getInstance();
        $this->hookManager 	= $this->getHookManager();
    }
    
    /**
     * Get HookManager instance (global or from container)
     */
    protected function getHookManager()
    {
        global $hookManager;
        
        if ($hookManager instanceof HookManager) {
            return $hookManager;
        }
        
        // Try to get from container
        if ($this->container->has('hookManager')) {
            return $this->container->make('hookManager');
        }
        
        // Create new instance if not exists
        return new HookManager();
    }
    
    /**
     * Register any application services.
     * This method is called when the provider is bootstrapped.
     */
    abstract public function register();
    
    /**
     * Bootstrap any application services.
     * This method is called after all providers have been registered.
     */
    public function boot()
    {
        // Optional: Override in child classes
    }
    
    /**
     * Check if the provider is deferred
     */
    public function isDeferred()
    {
        return $this->deferred;
    }
    
    /**
     * Get the services provided by the provider
     */
    public function provides()
    {
        return $this->provides;
    }
    
    /**
     * Register a singleton binding in the container
     */
    protected function singleton($abstract, $concrete = null)
    {
        $this->container->singleton($abstract, $concrete);
    }
    
    /**
     * Register a binding in the container
     */
    protected function bind($abstract, $concrete = null)
    {
        $this->container->bind($abstract, $concrete);
    }
    
    /**
     * Register a hook
     */
    protected function registerHook($hookName, $callback, $priority = 10)
    {
        $this->hookManager->registerHook($hookName, $callback, $priority);
    }
    
    /**
     * Get a configuration value
     */
    protected function config($key, $default = null)
    {
        return $this->config->get($key, $default);
    }
    
    /**
     * Publish configuration file to app directory
     */
    protected function publishes($filePath, $destination = null)
    {
        if ($destination === null) {
            $destination = dirname(__DIR__) . '/config/' . basename($filePath);
        }
        
        if (!file_exists($destination)) {
            copy($filePath, $destination);
        }
    }
}

