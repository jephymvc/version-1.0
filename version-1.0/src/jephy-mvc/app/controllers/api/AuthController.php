<?php
namespace App\Controllers\Api;
// jephy-mvc/app/controllers/Api/AuthController.php


use Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
    // AJAX Login
    public function login()
    {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        // Validate input
        if (empty($email) || empty($password)) {
            return $this->json([
                'success' => false,
                'message' => 'Email and password are required'
            ], 400);
        }
        
        // Find user
        $user = User::where('email', $email)->first();
        
        if (!$user || !password_verify($password, $user->password)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }
        
        // Set session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->name;
        session_regenerate_id(true);
        
        // Return user data (excluding sensitive info)
        return $this->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'redirect' => '/dashboard'
        ]);
    }
    
    // AJAX Registration
    public function register()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validation();
        $rules = [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'password' => 'required|min:6'
        ];
        
        if (!$validator->validate($input, $rules)) {
            return $this->json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }
        
        // Check if email exists
        if (User::where('email', $input['email'])->exists()) {
            return $this->json([
                'success' => false,
                'message' => 'Email already registered'
            ], 409);
        }
        
        // Create user
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => password_hash($input['password'], PASSWORD_DEFAULT)
        ]);
        
        // Auto-login after registration
        $_SESSION['user_id'] = $user->id;
        
        return $this->json([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ], 201);
    }
    
    // Check authentication status
    public function check()
    {
        if (isset($_SESSION['user_id'])) {
            $user = User::find($_SESSION['user_id']);
            
            return $this->json([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ]);
        }
        
        return $this->json([
            'authenticated' => false
        ]);
    }
    
    // AJAX Logout
    public function logout()
    {
        $_SESSION = [];
        session_destroy();
        
        return $this->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}