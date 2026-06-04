<?php
// jephy-mvc/app/middleware/JwtAuthMiddleware.php
namespace App\Middleware;

use Core\Middleware;
use Core\JWT;

class JwtAuthMiddleware extends Middleware
{
    
	private $jwt;
    
    public function __construct()
    {
        $this->jwt = new JWT();
    }
    
    public function handle($request, $next)
    {
        // Get Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $this->json([
                'success' => false,
                'message' => 'No token provided'
            ], 401);
        }
        
        $token = $matches[1];
        
        try {
            // Decode and validate token
            $payload = $this->jwt->decode($token);
            
            // Attach user info to request
            $request->user = $payload;
            $request->userId = $payload['user_id'];
            
            return $next($request);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or expired token: ' . $e->getMessage()
            ], 401);
        }
    }
    
    private function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

