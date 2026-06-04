<?php
namespace App\Controllers;
// jephy-mvc/app/controllers/AuthController.php (Traditional)


use Core\Controller;
use App\Models\User;

class AuthController extends Controller
{
    // Show login form
    public function showLoginForm()
    {
        return $this->view->display('auth/login.tpl');
    }
    
    // Process login
    public function login()
    {
        $email 		= $this->request->post( 'email' );
        $password 	= $this->request->post( 'password' );
        $remember 	= $this->request->post( 'remember' ) === 'on';
        
        // Find user by email
        $user = User::where('email', $email)->first();
        
        if ($user && password_verify($password, $user->password)) {
            // Set session variables
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_name'] = $user->name;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['logged_in'] = true;
            
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            // Set remember me cookie
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $user->remember_token = $token;
                $user->save();
                
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', true, true);
            }
            
            // Update last login
            $user->last_login_at = date('Y-m-d H:i:s');
            $user->last_login_ip = $_SERVER['REMOTE_ADDR'];
            $user->save();
            
            // Redirect to intended page or dashboard
            $redirectTo = $_SESSION['intended_url'] ?? '/dashboard';
            unset($_SESSION['intended_url']);
            
            $this->redirect($redirectTo);
        }
        
        // Authentication failed
        $_SESSION['error'] = 'Invalid email or password';
        $this->redirect('/login');
    }
    
    // Logout
    public function logout()
    {
        // Clear session
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Delete remember me cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        
        $this->redirect('/login');
		
    }
    
    // Middleware protected dashboard
    public function dashboard()
    {
        // This will only be accessible if logged in
        $user = User::find($_SESSION['user_id']);        
        $this->view->assign('user', $user);
        return $this->view->display('dashboard.tpl');
    }
	
	// Registration with validation
    public function register()
    {
        
		$validator  = new Validation();        
        $rules 		= [
            'name' 		=> 'required|min:2|max:100',
            'email' 	=> 'required|email',
            'password' 	=> 'required|min:6|confirmed',
            'terms' 	=> 'required'
        ];
        
        $data = $this->request->post();
        
        if (!$validator->validate($data, $rules)) {
            // Return validation errors
            return $this->json([
                'success' => false,
                'errors' => $validator->getErrors()
            ], 422);
        }
        
        // Create user if validation passes
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT)
        ]);
        
        return $this->json([
            'success' => true,
            'message' => 'Registration successful'
        ]);
    }
    
}

// Routes
//	$router->get('/login', 'AuthController@showLoginForm');
//	$router->post('/login', 'AuthController@login');
//	$router->get('/logout', 'AuthController@logout');
//	$router->get('/dashboard', 'DashboardController@index')->middleware('AuthMiddleware');