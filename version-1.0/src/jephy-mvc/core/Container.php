<?php

// jephy-mvc/core/Container.php
namespace Core;

class Container {
	
    private static $instance 	= null;
    private $bindings 			= [];
    private $instances 			= [];
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Bind an interface/class to a concrete implementation
    public function bind($abstract, $concrete = null, $singleton = false) {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
    }
    
    // Bind a singleton (one instance for entire application)
    public function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }
    
    // Resolve a class from the container
    public function make($abstract, $parameters = []) {
        // Return existing instance if singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // Get binding or use abstract as concrete
        $binding = $this->bindings[$abstract] ?? [
            'concrete' => $abstract,
            'singleton' => false
        ];
        
        $concrete = $binding['concrete'];
        
        // Build the object
        $object = $this->build($concrete, $parameters);
        
        // Store if singleton
        if ($binding['singleton']) {
            $this->instances[$abstract] = $object;
        }
        
        return $object;
    }
    
    // Build an object using reflection
    private function build($class, $parameters = []) {
        $reflection = new \ReflectionClass($class);
        
        if (!$reflection->isInstantiable()) {
            throw new \Exception("Class {$class} is not instantiable");
        }
        
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            return $reflection->newInstance();
        }
        
        $constructorParams = $constructor->getParameters();
        $dependencies = [];
        
        foreach ($constructorParams as $param) {
            $paramType = $param->getType();
            
            if ($paramType && !$paramType->isBuiltin()) {
                // Resolve dependency from container
                $dependencies[] = $this->make($paramType->getName());
            } elseif (isset($parameters[$param->getName()])) {
                // Use passed parameter
                $dependencies[] = $parameters[$param->getName()];
            } else {
                // Use default value if available
                if ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } else {
                    throw new \Exception("Cannot resolve dependency {$param->getName()}");
                }
            }
        }
        
        return $reflection->newInstanceArgs($dependencies);
    }
}