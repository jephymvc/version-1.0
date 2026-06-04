<?php
namespace App\Core;

/**
 * Base Middleware Class
 * 
 * All middleware classes should extend this class and implement the handle method.
 * 
 * @package App\Core
 */
abstract class Middleware
{
    /**
     * Handle the incoming request
     * 
     * @param mixed $request The request object
     * @param \Closure $next The next middleware or controller
     * @return mixed
     */
    abstract public function handle($request, \Closure $next);
    
    /**
     * Terminate the middleware (called after response is sent)
     * Override this method if you need to perform cleanup after the response is sent
     * 
     * @param mixed $request The request object
     * @param mixed $response The response object
     * @return void
     */
    public function terminate($request, $response)
    {
        // Optional: Override in child classes
    }
    
    /**
     * Get middleware parameters from string (e.g., "auth:admin,editor")
     * 
     * @param string $params The parameter string
     * @return array Parsed parameters
     */
    protected function parseParameters($params)
    {
        if (empty($params)) {
            return [];
        }
        
        return explode(',', $params);
    }
    
    /**
     * Check if request expects JSON response
     * 
     * @param mixed $request The request object
     * @return bool
     */
    protected function expectsJson($request)
    {
        if (method_exists($request, 'expectsJson')) {
            return $request->expectsJson();
        }
        
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }
        
        // Check if it's an AJAX request
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        return $isAjax;
    }
    
    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Redirect to URL
     * 
     * @param string $url Redirect URL
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function redirect($url, $statusCode = 302)
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }
    
    /**
     * Abort with error
     * 
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     * @return void
     */
    protected function abort($statusCode, $message = null)
    {
        http_response_code($statusCode);
        
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            419 => 'CSRF Token Mismatch',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error'
        ];
        
        if ($message === null) {
            $message = $messages[$statusCode] ?? 'Error';
        }
        
        if ($this->expectsJson(null)) {
            $this->jsonResponse(['error' => $message], $statusCode);
        }
        
        echo "<h1>{$statusCode}</h1>";
        echo "<p>{$message}</p>";
        exit;
    }
}