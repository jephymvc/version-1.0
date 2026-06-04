<?php
namespace App\Middleware;

use App\Core\Middleware;

class CsrfMiddleware extends Middleware
{
    /**
     * CSRF Protection Middleware
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Skip CSRF check for these methods
        $skipMethods = ['GET', 'HEAD', 'OPTIONS'];
        if (in_array($request->method, $skipMethods)) {
            return $next($request);
        }
        
        // Skip CSRF for API routes
        if (strpos($request->path, '/api/') === 0) {
            return $next($request);
        }
        
        // Get token from request
        $token = $this->getTokenFromRequest($request);
        
        // Validate token
        if (!$this->validateToken($token)) {
            if ($this->expectsJson($request)) {
                $this->jsonResponse(['error' => 'CSRF token validation failed', 'code' => 419], 419);
            }
            
            // Flash error message
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Security token validation failed. Please try again.'
            ];
            
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
        
        // Regenerate token for next request
        $this->regenerateToken();
        
        return $next($request);
    }
    
    /**
     * Get CSRF token from request
     * 
     * @param mixed $request
     * @return string|null
     */
    private function getTokenFromRequest($request)
    {
        // Check POST data
        if (isset($request->post['csrf_token'])) {
            return $request->post['csrf_token'];
        }
        
        // Check JSON input
        if (isset($request->json['csrf_token'])) {
            return $request->json['csrf_token'];
        }
        
        // Check headers
        if (isset($request->headers['X-CSRF-TOKEN'])) {
            return $request->headers['X-CSRF-TOKEN'];
        }
        
        // Check header variation
        if (isset($request->headers['X-Csrf-Token'])) {
            return $request->headers['X-Csrf-Token'];
        }
        
        return null;
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string|null $token
     * @return bool
     */
    private function validateToken($token)
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenerate CSRF token
     * 
     * @return void
     */
    private function regenerateToken()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}