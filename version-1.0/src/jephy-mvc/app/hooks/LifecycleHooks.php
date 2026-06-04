<?php

// jephy-mvc/app/hooks/LifecycleHooks.php
namespace App\Hooks;

class LifecycleHooks {
    
    // Before any request is processed
    public function beforeRequest($request) {
        // Log request start time
        $request->startTime = microtime(true);
    }
    
    // After route is matched but before middleware
    public function onRouteMatched($route, $params) {
        error_log("Route matched: {$route} with params " . json_encode($params));
    }
    
    // Before controller execution
    public function beforeController($controller, $method) {
        error_log("Executing {$controller}@{$method}");
    }
    
    // After controller execution, before response sent
    public function afterController($response) {
        // Add custom headers or modify response
        header('X-Powered-By: Jephy-MVC');
        return $response;
    }
    
    // After response sent
    public function onResponseSent() {
        // Log request completion
        $executionTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log("Request completed in {$executionTime} seconds");
    }
	
}