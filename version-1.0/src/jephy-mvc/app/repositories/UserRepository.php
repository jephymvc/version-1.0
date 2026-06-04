<?php
namespace App\Repositories;
// jephy-mvc/app/repositories/UserRepository.php

use Core\BaseRepository;

class UserRepository extends BaseRepository
{
    /**
     * Table name
     */
    protected $table = 'users';
    
    /**
     * Primary key
     */
    protected $primaryKey = 'id';
    
    /**
     * Fillable fields
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'email_verified_at',
        'last_login_at',
        'last_login_ip'
    ];
    
    /**
     * Hidden fields (not returned in queries)
     */
    protected $hidden = [
        'password'
    ];
    
    /**
     * Guarded fields (cannot be mass assigned)
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at'
    ];
    
    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        return $this->findBy('email', $email, 1);
    }
    
    /**
     * Find users by role
     */
    public function findByRole($role, $limit = null)
    {
        return $this->findBy('role', $role, $limit);
    }
    
    /**
     * Find active users
     */
    public function findActive($limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->execute($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Find users by date range
     */
    public function findByDateRange($startDate, $endDate)
    {
        $sql = "SELECT * FROM {$this->table} WHERE created_at BETWEEN :start AND :end";
        $stmt = $this->execute($sql, [
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin($userId, $ip)
    {
        $sql = "UPDATE {$this->table} SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id";
        return $this->execute($sql, [':id' => $userId, ':ip' => $ip]);
    }
    
    /**
     * Verify user email
     */
    public function verifyEmail($userId)
    {
        $sql = "UPDATE {$this->table} SET email_verified_at = NOW() WHERE id = :id";
        return $this->execute($sql, [':id' => $userId]);
    }
    
    /**
     * Get user statistics
     */
    public function getStats($userId = null)
    {
        if ($userId) {
            $sql = "SELECT COUNT(*) as total_users, 
                           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                           SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                    FROM {$this->table} WHERE id = :id";
            $stmt = $this->execute($sql, [':id' => $userId]);
        } else {
            $sql = "SELECT COUNT(*) as total_users, 
                           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                           SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                           SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified
                    FROM {$this->table}";
            $stmt = $this->execute($sql);
        }
        
        return $stmt->fetch();
    }
    
    /**
     * Search users
     */
    public function search($keyword, $fields = ['name', 'email'])
    {
        $conditions = [];
        $params = [];
        
        foreach ($fields as $field) {
            $conditions[] = "{$field} LIKE :keyword";
        }
        
        $whereClause = implode(' OR ', $conditions);
        $sql = "SELECT * FROM {$this->table} WHERE {$whereClause}";
        
        $stmt = $this->execute($sql, [':keyword' => "%{$keyword}%"]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get users with pagination
     */
    public function paginate($page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset";
        $stmt = $this->execute($sql, [
            ':limit' => $perPage,
            ':offset' => $offset
        ]);
        
        $users = $stmt->fetchAll();
        $total = $this->count();
        
        return [
            'data' => $users,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Batch update users
     */
    public function batchUpdate($ids, $data)
    {
        if (empty($ids)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $setClause = [];
        
        foreach ($data as $key => $value) {
            $setClause[] = "{$key} = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE id IN ({$placeholders})";
        
        $params = array_values($data);
        $params = array_merge($params, $ids);
        
        $stmt = $this->execute($sql, $params);
        
        return $stmt->rowCount();
    }
}