<?php
namespace App\Core\GraphQL\Shield;

class Rules
{
    public static function isAuthenticated(): Rule
    {
        return new Rule(function ($root, $args, $context, $info) {
            return isset($context['user']) && $context['user'] !== null;
        }, ['error' => 'Not authenticated']);
    }

    public static function hasRole(string $role): Rule
    {
        return new Rule(function ($root, $args, $context, $info) use ($role) {
            $user = $context['user'] ?? null;
            if (!$user) {
                return false;
            }
            
            $roles = $context['userRoles'] ?? [];
            return in_array($role, $roles);
        }, ['error' => "Role '{$role}' required"]);
    }

    public static function hasPermission(string $permission): Rule
    {
        return new Rule(function ($root, $args, $context, $info) use ($permission) {
            $user = $context['user'] ?? null;
            if (!$user) {
                return false;
            }
            
            $permissions = $context['userPermissions'] ?? [];
            return in_array($permission, $permissions);
        }, ['error' => "Permission '{$permission}' required"]);
    }

    public static function isOwner(string $resourceField = 'user_id'): Rule
    {
        return new Rule(function ($root, $args, $context, $info) use ($resourceField) {
            $user = $context['user'] ?? null;
            if (!$user) {
                return false;
            }

            // Check if the resource exists in root (for field-level)
            if ($root && is_array($root) && isset($root[$resourceField])) {
                return $root[$resourceField] == ($context['userId'] ?? 0);
            }

            // Check if ID is in args (for mutations)
            if (isset($args['id'])) {
                // You'll need to implement fetching the resource
                return false;
            }

            return false;
        }, ['error' => 'You do not own this resource']);
    }

    public static function isGuest(): Rule
    {
        return new Rule(function ($root, $args, $context, $info) {
            return !isset($context['user']) || $context['user'] === null;
        }, ['error' => 'Must be guest']);
    }
}