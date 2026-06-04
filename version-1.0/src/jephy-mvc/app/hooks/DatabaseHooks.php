<?php
namespace App\Hooks;
use App\Core\{ Framework, Config, HookManager };
class DatabaseHooks
{
    public function registerHooks( HookManager $hooks )
    {
        $hooks->registerHook( 'beforeUserRegister', [$this, 'validateUserRegistration'] );
        $hooks->registerHook( 'afterUserRegister', [$this, 'sendAdminNotification'] );
        $hooks->registerHook( 'beforeSave', [$this, 'sanitizeData'] );
        $hooks->registerHook( 'beforeInsert', [$this, 'logInsert'] );
    }
    
    public function validateUserRegistration($params)
    {
        $user = $params['user'];
        
        // Check if email already exists
        $existingUser = User::where('email', $user->email)->first();
        if ($existingUser) {
            return false; // Block registration
        }
        
        // Validate username
        if (strlen($user->username) < 3) {
            return false;
        }
        
        return $params;
    }
    
    public function sendAdminNotification($params)
    {
        $mailer 	= Framework::getMailer();
        $emailBody 	= "New user registered: {$params['user']->username} ({$params['user']->email})";       
        $mailer->send( Config::getInstance()->get( 'mail.address.from' ), 'New User Registration', $emailBody);
        
        return $params;
    }
    
    public function sanitizeData($params)
    {
        $entity = $params['entity'];
        
        // Sanitize string fields
        foreach ($params['attributes'] as $key => $value) {
            if (is_string($value)) {
                $params['attributes'][$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $params;
    }
    
    public function logInsert($params)
    {
        // Log the insert operation
		if( Config::getInstance()->get( 'site.app_debug' ) ){
			error_log("Inserting into {$params['entity']->table}: " . json_encode($params['attributes']));        
		}
        return $params;
    }
	
}