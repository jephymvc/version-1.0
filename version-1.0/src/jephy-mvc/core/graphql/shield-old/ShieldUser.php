<?php
namespace App\Core\GraphQL\Shield;

class ShieldUser
{
    private $id;
    private $username;
    private $roles = [];
    private $permissions = [];

    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? '';
        $this->roles = $data['roles'] ?? [];
        $this->permissions = $data['permissions'] ?? [];
    }

    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getRoles() { return $this->roles; }
    public function getPermissions() { return $this->permissions; }
    
    public function hasRole($role): bool 
    { 
        return in_array($role, $this->roles); 
    }
    
    public function hasPermission($permission): bool 
    { 
        return in_array($permission, $this->permissions); 
    }
}