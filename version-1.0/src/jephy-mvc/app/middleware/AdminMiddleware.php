<?php
namespace App\Middleware;

use App\Core\Middleware;

class AdminMiddleware extends Middleware
{
    /**
     * Admin access middleware
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            if ($this->expectsJson($request)) {
                $this->jsonResponse(['error' => 'Unauthorized'], 401);
            }
            
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            $this->redirect('/login');
        }
        
        // Check if user has admin role
        $userRole = $_SESSION['user_role'] ?? 'user';
        $allowedRoles = ['admin', 'super_admin'];
        
        if (!in_array($userRole, $allowedRoles)) {
            if ($this->expectsJson($request)) {
                $this->jsonResponse(['error' => 'Forbidden - Admin access required'], 403);
            }
            
            $this->abort(403, 'Access denied. Admin privileges required.');
        }
        
        return $next($request);
    }
}