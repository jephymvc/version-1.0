<?php
namespace App\Middleware;

use App\Core\Middleware;

class ThrottleMiddleware extends Middleware
{
    private $maxAttempts = 60;
    private $decayMinutes = 1;
    
    /**
     * Rate limiting middleware
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Parse parameters from middleware (e.g., throttle:100,5)
        if (method_exists($this, 'getParameters')) {
            $params = $this->getParameters();
            if (isset($params[0])) $this->maxAttempts = (int)$params[0];
            if (isset($params[1])) $this->decayMinutes = (int)$params[1];
        }
        
        $key = $this->resolveKey($request);
        
        if ($this->hasTooManyAttempts($key)) {
            $retryAfter = $this->getRetryAfter($key);
            
            header("Retry-After: {$retryAfter}");
            
            if ($this->expectsJson($request)) {
                $this->jsonResponse([
                    'error' => 'Too Many Requests',
                    'message' => "Please try again in {$retryAfter} seconds.",
                    'retry_after' => $retryAfter
                ], 429);
            }
            
            $this->abort(429, "Too many requests. Please try again in {$retryAfter} seconds.");
        }
        
        $this->incrementAttempts($key);
        
        $response = $next($request);
        
        // Add rate limit headers
        $remaining = $this->maxAttempts - $this->getAttemptCount($key);
        header("X-RateLimit-Limit: {$this->maxAttempts}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: " . $this->getResetTime($key));
        
        return $response;
    }
    
    /**
     * Resolve unique key for rate limiting
     * 
     * @param mixed $request
     * @return string
     */
    private function resolveKey($request)
    {
        $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'] ?? 'anonymous';
        $path = $request->path ?? '/';
        
        return "throttle:{$identifier}:{$path}";
    }
    
    /**
     * Check if too many attempts
     * 
     * @param string $key
     * @return bool
     */
    private function hasTooManyAttempts($key)
    {
        $attempts = $this->getAttempts($key);
        return count($attempts) >= $this->maxAttempts;
    }
    
    /**
     * Get attempts for key
     * 
     * @param string $key
     * @return array
     */
    private function getAttempts($key)
    {
        $attempts = $_SESSION[$key] ?? [];
        $now = time();
        
        // Filter out expired attempts
        $attempts = array_filter($attempts, function($timestamp) use ($now) {
            return ($now - $timestamp) < ($this->decayMinutes * 60);
        });
        
        return array_values($attempts);
    }
    
    /**
     * Get attempt count
     * 
     * @param string $key
     * @return int
     */
    private function getAttemptCount($key)
    {
        return count($this->getAttempts($key));
    }
    
    /**
     * Increment attempts
     * 
     * @param string $key
     * @return void
     */
    private function incrementAttempts($key)
    {
        $attempts = $this->getAttempts($key);
        $attempts[] = time();
        $_SESSION[$key] = $attempts;
    }
    
    /**
     * Get retry after time
     * 
     * @param string $key
     * @return int
     */
    private function getRetryAfter($key)
    {
        $attempts = $this->getAttempts($key);
        
        if (empty($attempts)) {
            return 0;
        }
        
        $oldest = min($attempts);
        return ($oldest + ($this->decayMinutes * 60)) - time();
    }
    
    /**
     * Get reset time timestamp
     * 
     * @param string $key
     * @return int
     */
    private function getResetTime($key)
    {
        $attempts = $this->getAttempts($key);
        if (empty($attempts)) {
            return time() + ($this->decayMinutes * 60);
        }
        
        $oldest = min($attempts);
        return $oldest + ($this->decayMinutes * 60);
    }
}