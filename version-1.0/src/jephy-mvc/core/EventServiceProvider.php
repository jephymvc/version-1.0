<?php
namespace App\Core;

class EventServiceProvider
{
    /**
     * @var array Event to listener mappings
     */
    protected $listen = [];
    
    /**
     * @var array Subscriber classes
     */
    protected $subscribers = [];
    
    public function __construct()
    {
        $this->listen = $this->listen();
        $this->subscribers = $this->subscribers();
    }
    
    /**
     * Define event to listener mappings
     */
    protected function listen()
    {
        return [
            // Authentication events
            'auth.login' => [
                'App\Listeners\LogUserLogin',
                'App\Listeners\UpdateLastLogin',
            ],
            'auth.logout' => [
                'App\Listeners\LogUserLogout',
            ],
            'auth.failed' => [
                'App\Listeners\LogFailedLogin',
            ],
            
            // User events
            'user.created' => [
                'App\Listeners\SendWelcomeEmail',
                'App\Listeners\CreateUserProfile',
            ],
            'user.updated' => [
                'App\Listeners\LogUserUpdate',
            ],
            'user.deleted' => [
                'App\Listeners\CleanupUserData',
            ],
            
            // Blog events
            'blog.created' => [
                'App\Listeners\SendNewPostNotification',
                'App\Listeners\UpdateUserPostCount',
            ],
            'blog.updated' => [
                'App\Listeners\ClearPostCache',
            ],
            'blog.deleted' => [
                'App\Listeners\CleanupPostComments',
            ],
            
            // System events
            'system.error' => [
                'App\Listeners\LogSystemError',
                'App\Listeners\SendErrorAlert',
            ],
            'system.maintenance' => [
                'App\Listeners\NotifyMaintenanceMode',
            ],
        ];
    }
    
    /**
     * Define event subscribers
     */
    protected function subscribers()
    {
        return [
            'App\Listeners\UserEventSubscriber',
            'App\Listeners\SystemEventSubscriber',
        ];
    }
    
    /**
     * Register all events
     */
    public function register()
    {
        // Register event listeners
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::on($event, $listener);
            }
        }
        
        // Register subscribers
        foreach ($this->subscribers as $subscriber) {
            $subscriberInstance = new $subscriber();
            if (method_exists($subscriberInstance, 'subscribe')) {
                $subscriberInstance->subscribe();
            }
        }
    }
    
    /**
     * Boot the service provider
     */
    public function boot()
    {
        // Additional boot logic if needed
    }
}
