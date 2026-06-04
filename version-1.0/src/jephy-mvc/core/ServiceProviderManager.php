<?php
namespace App\Core;
// jephy-mvc/core/ServiceProviderManager.php

use App\Core\Config;

class ServiceProviderManager
{
    
	/**
     * @var array Registered service providers
     */
    protected $providers = [];
    
    /**
     * @var array Booted providers
     */
    protected $booted = [];
    
    /**
     * @var Container Container instance
     */
    protected $container;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
    }
    
    /**
     * Register a service provider
     */
    public function register($provider)
    {
        if (is_string($provider)) {
            $provider = new $provider();
        }
        
        if (!$provider instanceof ServiceProvider) {
            throw new \InvalidArgumentException('Provider must extend Core\ServiceProvider');
        }
        
        $this->providers[] = $provider;
        
        // Register the provider
        $provider->register();
        
        return $provider;
    }
    
    /**
     * Register multiple providers
     */
    public function registerMany(array $providers)
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }
    
    /**
     * Boot all registered providers
     */
    public function boot()
    {
        foreach ($this->providers as $provider) {
            if (!in_array($provider, $this->booted)) {
                $provider->boot();
                $this->booted[] = $provider;
            }
        }
    }
    
    /**
     * Get all registered providers
     */
    public function getProviders()
    {
        return $this->providers;
    }
}

