<?php
namespace App\Hooks;
use App\Core\Framework;
use App\Core\HookManager;
class SecurityHooks
{
    public function registerHooks(HookManager $hooks)
    {
        $hooks->registerHook('beforeAddComment', [$this, 'validateComment'], 5);
        $hooks->registerHook('beforeAddComment', [$this, 'checkSpam'], 10);
    }
    
    public function validateComment($params)
    {
        if (empty($params['comment']) || strlen($params['comment']) < 5) {
            return false; // Prevent the action
        }
        
        // Check for banned words
        $bannedWords = ['spam', 'http://', 'https://'];
        foreach ($bannedWords as $word) {
            if (stripos($params['comment'], $word) !== false) {
                return false;
            }
        }
        
        return $params;
    }
    
    public function checkSpam($params)
    {
        // Simulate spam check
        if (isset($_POST['website']) && !empty($_POST['website'])) {
            return false; // Likely spam
        }
        
        return $params;
    }
}