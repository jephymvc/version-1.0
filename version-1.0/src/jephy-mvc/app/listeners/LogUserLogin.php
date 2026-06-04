<?php
namespace App\Listeners;

use App\Core\Logger;

class LogUserLogin
{
    public function handle($event, $payload)
    {
        Logger::info('User logged in via event', [
            'user_id' => $payload['user_id'] ?? null,
            'email' => $payload['email'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    public function __invoke($event, $payload)
    {
        return $this->handle($event, $payload);
    }
}
