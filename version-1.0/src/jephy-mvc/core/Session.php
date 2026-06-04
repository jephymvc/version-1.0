<?php
namespace App\Core;

class Session
{
    private static $started = false;
    private static $config;
    
    public static function init()
    {
        if (self::$started) {
            return;
        }
        
        self::$config = Config::getInstance();
        
        // Set session name
        session_name(self::$config->get('session.name', 'app_session'));
        
        // Set session cookie parameters
        $cookieParams = [
            'lifetime' => self::$config->get('session.lifetime', 0),
            'path' => self::$config->get('session.cookie_path', '/'),
            'domain' => self::$config->get('session.cookie_domain', ''),
            'secure' => self::$config->get('session.cookie_secure', false),
            'httponly' => self::$config->get('session.cookie_httponly', true),
            'samesite' => self::$config->get('session.cookie_samesite', 'Lax')
        ];
        
        session_set_cookie_params($cookieParams);
        
        // Set session save path if using file driver
        if (self::$config->get('session.driver') === 'file') {
            $savePath = self::$config->getStoragePath('sessions');
            if (!is_dir($savePath)) {
                mkdir($savePath, 0755, true);
            }
            session_save_path($savePath);
        }
        
        // Start session
        session_start();
        self::$started = true;
        
        // Regenerate session ID periodically for security
        self::regenerate();
        
        // Initialize CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Initialize flash data
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
    }
    
    public static function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }
    
    public static function has($key)
    {
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key)
    {
        unset($_SESSION[$key]);
    }
    
    public static function destroy()
    {
        session_destroy();
        $_SESSION = [];
        self::$started = false;
    }
    
    public static function regenerate($deleteOld = false)
    {
        session_regenerate_id($deleteOld);
    }
    
    public static function flash($key, $value = null)
    {
        if ($value === null) {
            // Get flash data
            $value = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $value;
        }
        
        // Set flash data
        $_SESSION['flash'][$key] = $value;
    }
    
    public static function csrfToken()
    {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    public static function csrfField()
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    public static function validateCsrf($token)
    {
        $sessionToken = self::csrfToken();
        
        if (!$sessionToken || !$token) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    public static function all()
    {
        return $_SESSION;
    }
    
    public static function id()
    {
        return session_id();
    }
    
    public static function save()
    {
        session_write_close();
        self::$started = false;
    }
}