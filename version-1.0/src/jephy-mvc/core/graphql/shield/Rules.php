<?php
namespace App\Core\GraphQL\Shield;

class Rules
{
    public static function isAuthenticated(): Rule
    {
        return new CallbackRule(function($root, $args, $context) {
            return isset($context['user']) || isset($context['current_user']);
        });
    }
    
    public static function hasRole(string $role): Rule
    {
        return new CallbackRule(function($root, $args, $context) use ($role) {
            $user = $context['user'] ?? $context['current_user'] ?? null;
            return $user && isset($user['role']) && $user['role'] === $role;
        });
    }
    
    public static function hasPermission(string $permission): Rule
    {
        return new CallbackRule(function($root, $args, $context) use ($permission) {
            $user = $context['user'] ?? $context['current_user'] ?? null;
            return $user && isset($user['permissions']) && 
                   in_array($permission, $user['permissions']);
        });
    }
    
    public static function isOwner(string $ownerField = 'user_id'): Rule
    {
        return new CallbackRule(function($root, $args, $context) use ($ownerField) {
            $user = $context['user'] ?? $context['current_user'] ?? null;
            if (!$user) return false;
            
            $userId = $user['id'] ?? $user['user_id'] ?? null;
            if (!$userId) return false;
            
            if ($root && isset($root[$ownerField])) {
                return $root[$ownerField] == $userId;
            }
            
            if (isset($args[$ownerField])) {
                return $args[$ownerField] == $userId;
            }
            
            return false;
        });
    }
}