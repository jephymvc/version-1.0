<?php
namespace App\Services;
// jephy-mvc/app/services/UserService.php


use App\Core\BaseService;
use App\Repositories\UserRepository;
use App\Services\MailService;
use App\Services\CacheService;

class UserService extends BaseService
{
    /**
     * @var UserRepository User repository instance
     */
    private $userRepository;
    
    /**
     * @var MailService Mail service instance
     */
    private $mailService;
    
    /**
     * @var CacheService Cache service instance
     */
    private $cacheService;
    
    /**
     * Constructor
     */
    public function __construct(
        UserRepository $userRepository = null,
        MailService $mailService = null,
        CacheService $cacheService = null
    ) {
        parent::__construct();
        
        $this->userRepository = $userRepository ?? new UserRepository();
        $this->mailService = $mailService ?? new MailService();
        $this->cacheService = $cacheService ?? new CacheService();
    }
    
    /**
     * Initialize service
     */
    protected function initialize()
    {
        $this->validateConfig([
            'app.name'
        ]);
    }
    
    /**
     * Get all users
     */
    public function getAllUsers($page = 1, $limit = 20)
    {
        $this->log('Fetching all users', ['page' => $page, 'limit' => $limit]);
        
        $cacheKey = "users.all.page_{$page}.limit_{$limit}";
        
        // Try to get from cache
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== null) {
            $this->log('Users retrieved from cache', ['key' => $cacheKey]);
            return $cached;
        }
        
        // Get from repository
        $users = $this->userRepository->all($page, $limit);
        
        // Store in cache for 5 minutes
        $this->cacheService->set($cacheKey, $users, 300);
        
        return $users;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id)
    {
        $this->log('Fetching user by ID', ['id' => $id]);
        
        $cacheKey = "user.id.{$id}";
        
        // Try to get from cache
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Get from repository
        $user = $this->userRepository->find($id);
        
        if ($user) {
            // Store in cache for 1 hour
            $this->cacheService->set($cacheKey, $user, 3600);
        }
        
        return $user;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail($email)
    {
        $this->log('Fetching user by email', ['email' => $email]);
        
        $cacheKey = "user.email." . md5($email);
        
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $user = $this->userRepository->findByEmail($email);
        
        if ($user) {
            $this->cacheService->set($cacheKey, $user, 3600);
        }
        
        return $user;
    }
    
    /**
     * Create a new user
     */
    public function createUser($data)
    {
        $this->log('Creating new user', ['email' => $data['email'] ?? 'unknown']);
        
        // Validate data
        $this->validateUserData($data);
        
        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            throw new \RuntimeException("User with email {$data['email']} already exists");
        }
        
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Create user
        $user = $this->userRepository->create($data);
        
        // Clear relevant caches
        $this->cacheService->clearByPattern('users.all.*');
        
        // Send welcome email
        $this->mailService->sendWelcomeEmail($user);
        
        // Fire hook after user creation
        $this->hookManager->fire('user_created', $user);
        
        return $user;
    }
    
    /**
     * Update user
     */
    public function updateUser($id, $data)
    {
        $this->log('Updating user', ['id' => $id]);
        
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new \RuntimeException("User with ID {$id} not found");
        }
        
        // Update password if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        $updated = $this->userRepository->update($id, $data);
        
        // Clear user from cache
        $this->cacheService->delete("user.id.{$id}");
        $this->cacheService->delete("user.email." . md5($user->email));
        $this->cacheService->clearByPattern('users.all.*');
        
        // Fire hook
        $this->hookManager->fire('user_updated', ['id' => $id, 'data' => $data]);
        
        return $updated;
    }
    
    /**
     * Delete user
     */
    public function deleteUser($id, $hardDelete = false)
    {
        $this->log('Deleting user', ['id' => $id, 'hard_delete' => $hardDelete]);
        
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new \RuntimeException("User with ID {$id} not found");
        }
        
        $result = $this->userRepository->delete($id, $hardDelete);
        
        // Clear caches
        $this->cacheService->delete("user.id.{$id}");
        $this->cacheService->delete("user.email." . md5($user->email));
        $this->cacheService->clearByPattern('users.all.*');
        
        // Fire hook
        $this->hookManager->fire('user_deleted', ['id' => $id, 'hard' => $hardDelete]);
        
        return $result;
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($email, $password)
    {
        $this->log('Authenticating user', ['email' => $email]);
        
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            $this->log('Authentication failed: User not found', ['email' => $email]);
            return false;
        }
        
        if (!password_verify($password, $user->password)) {
            $this->log('Authentication failed: Invalid password', ['email' => $email]);
            return false;
        }
        
        // Update last login
        $this->userRepository->update($user->id, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        $this->log('Authentication successful', ['email' => $email, 'user_id' => $user->id]);
        
        // Fire hook
        $this->hookManager->fire('user_authenticated', $user);
        
        return $user;
    }
    
    /**
     * Validate user data
     */
    private function validateUserData($data)
    {
        $errors = [];
        
        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        if (isset($data['password']) && strlen($data['password']) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(', ', $errors));
        }
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword)
    {
        $this->log('Changing password', ['user_id' => $userId]);
        
        $user = $this->getUserById($userId);
        
        if (!$user) {
            throw new \RuntimeException("User not found");
        }
        
        if (!password_verify($oldPassword, $user->password)) {
            throw new \RuntimeException("Invalid current password");
        }
        
        if (strlen($newPassword) < 6) {
            throw new \RuntimeException("New password must be at least 6 characters");
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return $this->userRepository->update($userId, ['password' => $hashedPassword]);
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($userId = null)
    {
        $this->log('Fetching user statistics', ['user_id' => $userId]);
        
        $cacheKey = $userId ? "user.stats.{$userId}" : "user.stats.all";
        
        $cached = $this->cacheService->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $stats = $this->userRepository->getStats($userId);
        $this->cacheService->set($cacheKey, $stats, 1800); // 30 minutes
        
        return $stats;
    }
}