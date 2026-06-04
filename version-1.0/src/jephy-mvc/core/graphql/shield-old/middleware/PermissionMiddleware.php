<?php
namespace App\Core\GraphQL\Shield\Middleware;

use App\Core\GraphQL\Shield\ShieldError;

class PermissionMiddleware
{
    private $requiredPermissions;

    public function __construct(array $requiredPermissions = [])
    {
        $this->requiredPermissions = $requiredPermissions;
    }

    public function handle($context)
    {
        if (!isset($context['user'])) {
            return new ShieldError('User not authenticated', ['code' => 'UNAUTHENTICATED']);
        }

        if (empty($this->requiredPermissions)) {
            return $context;
        }

        $userPermissions = $context['userPermissions'] ?? [];
        
        foreach ($this->requiredPermissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return $context;
            }
        }

        return new ShieldError(
            'Insufficient permissions',
            ['code' => 'FORBIDDEN', 'required' => $this->requiredPermissions]
        );
    }
}