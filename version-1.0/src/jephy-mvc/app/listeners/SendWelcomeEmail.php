<?php
namespace App\Listeners;

use App\Core\Logger;
use App\Core\Mailer;

class SendWelcomeEmail
{
    protected $mailer;
    
    public function __construct()
    {
        $this->mailer = new Mailer();
    }
    
    public function handle($event, $payload)
    {
        $user = $payload['user'] ?? null;
        
        if (!$user) {
            return;
        }
        
        try {
            $this->mailer->send([
                'to' => $user['email'],
                'subject' => 'Welcome to our platform!',
                'template' => 'emails/welcome.tpl',
                'data' => ['user' => $user]
            ]);
            
            Logger::info('Welcome email sent', ['user_id' => $user['id']]);
        } catch (\Exception $e) {
            Logger::error('Failed to send welcome email', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
}
