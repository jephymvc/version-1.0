<?php
namespace App\Core\GraphQL\Shield\Middleware;
use App\Core\GraphQL\Shield\ShieldError;
class AuthMiddleware
{
    public function handle($context)
    {
        if (!isset($context['user']) || $context['user'] === null) {
            return new ShieldError('Authentication required', ['code' => 'UNAUTHENTICATED']);
        }
        
        return $context;
    }
}