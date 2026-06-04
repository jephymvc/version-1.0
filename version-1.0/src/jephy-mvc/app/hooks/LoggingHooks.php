<?php
namespace App\Hooks;
use App\Core\{ Framework, Config, HookManager };
class LoggingHooks
{
    public function registerHooks($hooks)
    {
        $hooks->registerHook('afterAddComment', [$this, 'logComment']);
        $hooks->registerHook('onError', [$this, 'logError']);
    }
    
    public function logComment($params)
    {
       	if( Config::getInstance()->get( 'site.app_debug' ) ){
			error_log("User {$params['user_id']} commented on post {$params['post_id']}");       
		}	  
        return $params; // Don't modify, just log
    }
    
    public function logError($params)
    {
        error_log("Error: {$params['exception']->getMessage()}");
        return $params;
    }
}