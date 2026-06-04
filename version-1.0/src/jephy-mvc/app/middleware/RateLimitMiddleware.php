<?php

// jephy-mvc/app/middleware/RateLimitMiddleware.php
class RateLimitMiddleware extends Middleware
{
    public function handle($request, $next)
    {
        $ip 		= $_SERVER['REMOTE_ADDR'];
        $key 		= "rate_limit:{$ip}";
        $maxRequests 	= 60; // 60 requests
        $timeWindow 	= 60; // per minute
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'first_request' => time()];
        } else {
            $data = $_SESSION[$key];
            
            if (time() - $data['first_request'] > $timeWindow) {
                // Reset window
                $_SESSION[$key] = ['count' => 1, 'first_request' => time()];
            } elseif ($data['count'] >= $maxRequests) {
                return $this->json([
                    'message' => 'Too many requests. Please try again later.'
                ], 429);
            } else {
                $_SESSION[$key]['count']++;
            }
        }
        
        return $next($request);
    }
}

