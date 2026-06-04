<?php
namespace App\Controllers\Api;
// jephy-mvc/app/controllers/Api/JwtAuthController.php


use Core\Controller;
use Core\JWT;
use Core\Validation;
use App\Models\User;

class JwtAuthController extends Controller
{
    
	private $jwt;    
    public function __construct()
    {
        parent::__construct();
        $this->jwt = new JWT();
    }
    
    // Login and get JWT token
    public function login()
    {
        
		$input 		= json_decode( file_get_contents( 'php://input' ), true );        
        $validator 	= new Validation();
        $rules 		= [
            'email' 	=> 'required|email',
            'password' 	=> 'required'
        ];
        
        if (!$validator->validate($input, $rules)) {
            return $this->json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }
        
        // Find user
        $user = User::where('email', $input['email'])->first();
        
        if (!$user || !password_verify($input['password'], $user->password)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }
        
        // Create JWT payload
        $payload = [
            'user_id' 	=> $user->id,
            'email' 	=> $user->email,
            'role' 		=> $user->role
        ];
        
        // Generate token
        $token 			= $this->jwt->encode($payload);
        $refreshToken 	= $this->generateRefreshToken($user->id);
        
        return $this->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwt->ttl,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]
        ]);
    }
    
    // Refresh token
    public function refresh()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? '';
        
        // Validate refresh token from database
        $tokenRecord = RefreshToken::where('token', $refreshToken)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
        
        if (!$tokenRecord) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }
        
        // Get user
        $user = User::find($tokenRecord->user_id);
        
        // Generate new tokens
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' 	=> $user->role
        ];
        
        $newToken 		= $this->jwt->encode($payload);
        $newRefreshToken = $this->generateRefreshToken($user->id);
        
        // Invalidate old refresh token
        $tokenRecord->delete();
        
        return $this->json([
            'success' => true,
            'data' => [
                'access_token' => $newToken,
                'refresh_token' => $newRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwt->ttl
            ]
        ]);
    }
    
    // Get authenticated user info
    public function me()
    {
        $auth = $this->getAuthenticatedUser();
        
        if (!$auth) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $auth->id,
                'name' => $auth->name,
                'email' => $auth->email,
                'role' => $auth->role,
                'created_at' => $auth->created_at
            ]
        ]);
    }
    
    // Logout (revoke token)
    public function logout()
    {
        $auth = $this->getAuthenticatedUser();
        
        if ($auth) {
            // Blacklist token or delete refresh token
            $token = $this->getBearerToken();
            TokenBlacklist::create([
                'token' => $token,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->jwt->ttl)
            ]);
        }
        
        return $this->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
    
    // Generate refresh token
    private function generateRefreshToken($userId)
    {
        $token = bin2hex(random_bytes(64));
        
        RefreshToken::create([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', time() + 604800) // 7 days
        ]);
        
        return $token;
    }
    
    // Extract bearer token from Authorization header
    private function getBearerToken()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    // Get authenticated user from JWT
    private function getAuthenticatedUser()
    {
        $token = $this->getBearerToken();
        
        if (!$token) {
            return null;
        }
        
        try {
            $payload = $this->jwt->decode($token);
            
            // Check if token is blacklisted
            if (TokenBlacklist::where('token', $token)->exists()) {
                return null;
            }
            
            return User::find($payload['user_id']);
        } catch (\Exception $e) {
            return null;
        }
    }
}
