<?php
namespace App\Middleware;

use App\Core\Middleware;

class GuestMiddleware extends Middleware
{
    /**
     * Guest only middleware (redirects authenticated users)
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // If user is already logged in, redirect away from guest pages
        if (isset($_SESSION['user_id'])) {
            $intended = $_SESSION['intended_url'] ?? '/dashboard';
            unset($_SESSION['intended_url']);
            
            if ($this->expectsJson($request)) {
                $this->jsonResponse(['error' => 'Already authenticated'], 400);
            }
            
            $this->redirect($intended);
        }
        
        return $next($request);
    }
}