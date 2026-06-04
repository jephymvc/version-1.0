<?php
namespace App\Core;

class Event
{
    /**
     * @var array Registered event listeners
     */
    protected static $listeners = [];
    
    /**
     * @var array Wildcard event listeners
     */
    protected static $wildcardListeners = [];
    
    /**
     * @var array Queue of events to be processed
     */
    protected static $queue = [];
    
    /**
     * @var bool Whether events are queued
     */
    protected static $queueEnabled = false;
    
    /**
     * @var bool Whether event propagation is stopped
     */
    protected static $propagationStopped = false;
    
    /**
     * Register an event listener
     * 
     * @param string $eventName Event name
     * @param callable|string $listener Listener callback or class@method
     * @param int $priority Priority (higher = executed first)
     */
    public static function on($eventName, $listener, $priority = 0)
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }
        
        if (!isset(self::$listeners[$eventName][$priority])) {
            self::$listeners[$eventName][$priority] = [];
        }
        
        self::$listeners[$eventName][$priority][] = $listener;
        
        // Sort by priority (higher first)
        krsort(self::$listeners[$eventName]);
    }
    
    /**
     * Register a one-time event listener
     * 
     * @param string $eventName Event name
     * @param callable|string $listener Listener callback or class@method
     * @param int $priority Priority
     */
    public static function once($eventName, $listener, $priority = 0)
    {
        $onceListener = function($eventNameParam, $payloadParam = null) use ($listener, $eventName) {
            $result = self::dispatchListener($listener, $eventNameParam, $payloadParam);
            self::off($eventName, $onceListener);
            return $result;
        };
        
        self::on($eventName, $onceListener, $priority);
    }
    
    /**
     * Register a wildcard event listener (matches events with pattern)
     * 
     * @param string $pattern Event pattern (supports * wildcard)
     * @param callable|string $listener
     * @param int $priority
     */
    public static function onWildcard($pattern, $listener, $priority = 0)
    {
        if (!isset(self::$wildcardListeners[$pattern])) {
            self::$wildcardListeners[$pattern] = [];
        }
        
        if (!isset(self::$wildcardListeners[$pattern][$priority])) {
            self::$wildcardListeners[$pattern][$priority] = [];
        }
        
        self::$wildcardListeners[$pattern][$priority][] = $listener;
        krsort(self::$wildcardListeners[$pattern]);
    }
    
    /**
     * Remove an event listener
     * 
     * @param string $eventName Event name
     * @param callable|null $listener Specific listener to remove (remove all if null)
     */
    public static function off($eventName, $listener = null)
    {
        if ($listener === null) {
            unset(self::$listeners[$eventName]);
            return;
        }
        
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $priority => $listeners) {
                foreach ($listeners as $key => $registeredListener) {
                    if ($registeredListener === $listener) {
                        unset(self::$listeners[$eventName][$priority][$key]);
                    }
                }
                
                if (empty(self::$listeners[$eventName][$priority])) {
                    unset(self::$listeners[$eventName][$priority]);
                }
            }
            
            if (empty(self::$listeners[$eventName])) {
                unset(self::$listeners[$eventName]);
            }
        }
    }
    
    /**
     * Dispatch an event
     * 
     * @param string $eventName Event name
     * @param mixed $payload Event data
     * @return mixed Event response
     */
    public static function dispatch($eventName, $payload = null)
    {
        self::$propagationStopped = false;
        
        if (self::$queueEnabled) {
            self::$queue[] = ['event' => $eventName, 'payload' => $payload];
            return null;
        }
        
        $responses = [];
        
        // Dispatch exact event listeners
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    if (self::$propagationStopped) {
                        break 2;
                    }
                    
                    $response = self::dispatchListener($listener, $eventName, $payload);
                    if ($response !== null) {
                        $responses[] = $response;
                    }
                }
            }
        }
        
        // Dispatch wildcard listeners
        foreach (self::$wildcardListeners as $pattern => $priorities) {
            if (self::matchesWildcard($pattern, $eventName)) {
                foreach ($priorities as $priority => $listeners) {
                    foreach ($listeners as $listener) {
                        if (self::$propagationStopped) {
                            break 3;
                        }
                        
                        $response = self::dispatchListener($listener, $eventName, $payload);
                        if ($response !== null) {
                            $responses[] = $response;
                        }
                    }
                }
            }
        }
        
        return $responses;
    }
    
    /**
     * Dispatch a listener
     * 
     * @param callable|string $listener
     * @param string $eventName
     * @param mixed $payload
     * @return mixed
     */
    protected static function dispatchListener($listener, $eventName, $payload)
    {
        // Handle string listener (Class@method or Class::method)
        if (is_string($listener)) {
            if (strpos($listener, '@') !== false) {
                list($class, $method) = explode('@', $listener);
                if (class_exists($class)) {
                    $listener = [new $class(), $method];
                }
            } elseif (strpos($listener, '::') !== false) {
                $listener = explode('::', $listener);
            }
        }
        
        // Call the listener
        if (is_callable($listener)) {
            return call_user_func($listener, $eventName, $payload);
        }
        
        return null;
    }
    
    /**
     * Check if event name matches wildcard pattern
     * 
     * @param string $pattern
     * @param string $eventName
     * @return bool
     */
    protected static function matchesWildcard($pattern, $eventName)
    {
        $pattern = str_replace('\\*', '.*', preg_quote($pattern, '/'));
        return preg_match('/^' . $pattern . '$/i', $eventName);
    }
    
    /**
     * Stop event propagation
     */
    public static function stopPropagation()
    {
        self::$propagationStopped = true;
    }
    
    /**
     * Enable event queuing
     */
    public static function enableQueue()
    {
        self::$queueEnabled = true;
    }
    
    /**
     * Disable event queuing
     */
    public static function disableQueue()
    {
        self::$queueEnabled = false;
    }
    
    /**
     * Flush the event queue
     */
    public static function flushQueue()
    {
        $responses = [];
        
        foreach (self::$queue as $queuedEvent) {
            $response = self::dispatch($queuedEvent['event'], $queuedEvent['payload']);
            if ($response !== null) {
                $responses[] = $response;
            }
        }
        
        self::$queue = [];
        return $responses;
    }
    
    /**
     * Get all registered listeners
     * 
     * @return array
     */
    public static function getListeners()
    {
        return self::$listeners;
    }
    
    /**
     * Check if event has listeners
     * 
     * @param string $eventName
     * @return bool
     */
    public static function hasListeners($eventName)
    {
        return isset(self::$listeners[$eventName]) && !empty(self::$listeners[$eventName]);
    }
    
    /**
     * Clear all listeners
     */
    public static function clearListeners()
    {
        self::$listeners = [];
        self::$wildcardListeners = [];
        self::$queue = [];
        self::$queueEnabled = false;
        self::$propagationStopped = false;
    }
}