<?php
namespace App\Core\GraphQL\Shield\Middleware;

use App\Core\GraphQL\Shield\ShieldError;

class RoleMiddleware
{
    private $requiredRoles;

    public function __construct(array $requiredRoles = [])
    {
        $this->requiredRoles = $requiredRoles;
    }

    public function handle($context)
    {
        if (!isset($context['user'])) {
            return new ShieldError('User not authenticated', ['code' => 'UNAUTHENTICATED']);
        }

        if (empty($this->requiredRoles)) {
            return $context;
        }

        $userRoles = $context['userRoles'] ?? [];
        
        foreach ($this->requiredRoles as $role) {
            if (in_array($role, $userRoles)) {
                return $context;
            }
        }

        return new ShieldError(
            'Insufficient role permissions',
            ['code' => 'FORBIDDEN', 'required' => $this->requiredRoles]
        );
    }
}