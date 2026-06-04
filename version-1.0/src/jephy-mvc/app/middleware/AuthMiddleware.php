<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Models\User;

class AuthMiddleware extends Middleware
{
    /**
     * Handle authentication check
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Check if user is authenticated
        if (!isset($_SESSION['user_id'])) {
			
			// Check remember me cookie
            if (isset($_COOKIE['remember_token'])) {
                $user = User::where('remember_token', $_COOKIE['remember_token'])->first();
                if ($user) {
                    $_SESSION['user_id'] 	= $user->id;
                    $_SESSION['user_name'] 	= $user->name;
                    return $next($request);
                }
            }
			
            // Store intended URL for redirect after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            
            // Check if request expects JSON
            if ($this->expectsJson($request)) {
                $this->jsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            // Redirect to login page
            $this->redirect('/login');
        }
        
        // Add user data to request if available
        if (method_exists($request, 'setAttribute')) {
            $user 	= $this->getUser( $_SESSION['user_id'] );
            $request->setAttribute('user', $user);
        }
        
        return $next($request);
    }
    
    /**
     * Get user data from database
     * 
     * @param int $userId
     * @return array|null
     */
    private function getUser($userId)
    {
        // You can inject Database instance here
        // For now, return session data
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'user'
        ];
    }
    
    /**
     * Terminate method - called after response is sent
     * 
     * @param mixed $request
     * @param mixed $response
     * @return void
     */
    public function terminate($request, $response)
    {
        // Update last activity timestamp
        $_SESSION['last_activity'] = time();
    }
}