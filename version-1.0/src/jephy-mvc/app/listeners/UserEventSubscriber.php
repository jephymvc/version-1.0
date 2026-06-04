<?php
namespace App\Listeners;

use App\Core\EventSubscriber;
use App\Core\Logger;

class UserEventSubscriber
{
    use EventSubscriber;
    
    protected $subscribedEvents = [
        'user.created' => 'onUserCreated',
        'user.updated' => 'onUserUpdated',
        'user.deleted' => 'onUserDeleted',
    ];
    
    public function onUserCreated($event, $payload)
    {
        Logger::info('User creation event triggered', $payload);
    }
    
    public function onUserUpdated($event, $payload)
    {
        Logger::info('User update event triggered', $payload);
    }
    
    public function onUserDeleted($event, $payload)
    {
        Logger::warning('User deletion event triggered', $payload);
    }
}