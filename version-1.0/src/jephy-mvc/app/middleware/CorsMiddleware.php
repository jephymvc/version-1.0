<?php
namespace App\Middleware;

use App\Core\Middleware;

class CorsMiddleware extends Middleware
{
    /**
     * CORS middleware
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Allowed origins
        $allowedOrigins = [
            'http://localhost:3000',
            'https://yourdomain.com'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        }
        
        // Handle preflight OPTIONS request
        if ($request->method === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
            header('Access-Control-Max-Age: 86400');
            exit;
        }
        
        return $next($request);
    }
}