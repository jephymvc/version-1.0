<?php
namespace App\Core;

class HookManager
{
    
	private $hooks 			= [];
    private $disabledHooks 	= [];
    
    /**
     * Register a hook
     */
    public function registerHook( $hookName, $callback, $priority = 10 )
    {
        
		if ( !is_callable( $callback ) ) {
            throw new \InvalidArgumentException( "Hook callback must be callable for hook: {$hookName}" );
        }
        
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        
        $this->hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => (int)$priority
        ];
        
        // Sort by priority (lower number = higher priority)
        usort($this->hooks[$hookName], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
		
    }
    
    /**
     * Execute hooks - pass arguments as array
     */
    public function exec( $hookName, $params = [] )
    {
        
		if ( in_array( $hookName, $this->disabledHooks ) || !isset( $this->hooks[$hookName] ) ) {
            return $params;
        }
        
        $modifiedParams = $params;
        
        foreach ( $this->hooks[$hookName] as $hook ) {
            // Always pass $params as a single array argument
            $result = call_user_func($hook['callback'], $modifiedParams);
            
            // If hook returns an array, use it as new parameters
            if (is_array($result)) {
                $modifiedParams = $result;
            }
            // If hook returns a non-null scalar, replace params
            elseif ($result !== null) {
                $modifiedParams = $result;
            }
        }
        
        return $modifiedParams;
		
    }
    
    /**
     * Execute hooks with multiple arguments
     * Use this when you need to pass multiple separate arguments
     */
    public function fire($hookName, ...$args)
    {
        if (in_array($hookName, $this->disabledHooks) || !isset($this->hooks[$hookName])) {
            return;
        }
        
        foreach ($this->hooks[$hookName] as $hook) {
            call_user_func_array($hook['callback'], $args);
        }
    }
    
    /**
     * Execute hooks and collect their return values
     */
    public function execWithReturnAlt($hookName, $params = [])
    {
        $results = [];
        
        if (in_array($hookName, $this->disabledHooks) || !isset($this->hooks[$hookName])) {
            return $results;
        }
        
        foreach ($this->hooks[$hookName] as $hook) {
            // Pass parameters as single array argument
            $result = call_user_func($hook['callback'], $params);
            
            if ($result !== null) {
                $results[] = $result;
            }
        }
        
        return $results;
    }
	
	public function execWithReturn($hook, $params = [])
	{
		if (!isset($this->hooks[$hook])) {
			return [];
		}
		
		$results = [];
		
		foreach ($this->hooks[$hook] as $hookData) {
			$callback = $hookData['callback'];
			
			if (is_callable($callback)) {
				try {
					// For methods expecting single params array
					$result = call_user_func_array($callback, [$params]);
					
					// For backward compatibility, also try with multiple params
					if ($result === null && !is_array($params)) {
						$result = call_user_func_array($callback, $params);
					}
					
					if ($result !== null) {
						$results[] = $result;
					}
					
				} catch (\ArgumentCountError $e) {
					// Try without parameters
					try {
						$result = call_user_func($callback);
						if ($result !== null) {
							$results[] = $result;
						}
					} catch (\Exception $e2) {
						error_log("Hook execution error: " . $e2->getMessage());
					}
				} catch (\Exception $e) {
					error_log("Hook execution error: " . $e->getMessage());
				}
			}
		}
		
		return $results;
	}
		
    public function hasHooks($hookName)
    {
        return isset($this->hooks[$hookName]) && !empty($this->hooks[$hookName]);
    }
	
	
}
