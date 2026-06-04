<?php
namespace App\Core;
class Csrf
{
    private static $token;
    
    /**
     * Generate or retrieve CSRF token
     */
    public static function generate()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        self::$token = $_SESSION['csrf_token'];
        return self::$token;
    }
    
    /**
     * Get CSRF token
     */
    public static function token()
    {
        return self::generate();
    }
    
    /**
     * Get CSRF token as hidden input field
     */
    public static function field()
    {
        $token = self::token();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get CSRF token as meta tag
     */
    public static function meta()
    {
        $token = self::token();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate($token = null)
    {
		if ( defined( 'APP_DEBUG') && APP_DEBUG ) {
			error_log( 'CSRF validation skipped in debug mode' );
			return true;
		}
		
        // Skip validation for safe methods
        $safeMethods = [ 'GET', 'HEAD', 'OPTIONS' ];
        if ( in_array( $_SERVER[ 'REQUEST_METHOD' ], $safeMethods ) ) {
            return true;
        }
        
        $token = $token ?? self::getTokenFromRequest();
        
        if ( empty( $token ) || !self::isValid( $token ) ) {
            throw new Exception( 'CSRF token validation failed' );
        }
        
        return true;
		
    }
    
    /**
     * Extract token from request
     */
    private static function getTokenFromRequest()
    {
		
		#	echo '<pre>';
		#	print_r( $_POST );
		#	echo '</pre>';
		
        // Check POST first
		if ( !empty( $_POST['_token'] ) || isset( $_POST['_token'] ) ) {
            return $_POST['_token'];
        }
        
        // Check for JSON payload
        $input = @file_get_contents('php://input');
        if (!empty($input)) {
            $data = json_decode($input, true);
            if (is_array($data) && isset($data['_token'])) {
                return $data['_token'];
            }
        }
        
        // Check headers (for AJAX)
        $headers = getallheaders();
        if ($headers) {
            foreach (['X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'X-CSRF-Token'] as $header) {
                if ( !empty( $headers[$header] ) || isset( $headers[$header] ) ) {
                    return $headers[$header];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if token is valid
     */
    private static function isValid($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenerate CSRF token
     */
    public static function regenerate()
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        self::$token = $_SESSION['csrf_token'];
        return self::$token;
    }
	
}