<?php
namespace App\Providers;
// jephy-mvc/app/providers/AppServiceProvider.php


use App\Core\ServiceProvider;
use App\Services\UserService;
use App\Services\MailService;
use App\Services\CacheService;
use App\Repositories\UserRepository;
use App\Repositories\ProductRepository;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // Register singleton services (one instance for entire app)
        $this->registerSingletonServices();
        
        // Register repository bindings
        $this->registerRepositories();
        
        // Register interface bindings
        $this->registerInterfaceBindings();
        
        // Register middleware
        $this->registerMiddleware();
        
        // Register custom services
        $this->registerCustomServices();
    }
    
    /**
     * Bootstrap any application services.
     * This runs after all providers are registered.
     */
    public function boot()
    {
        // Register global hooks
        $this->registerGlobalHooks();
        
        // Initialize third-party services
        $this->initializeThirdParty();
        
        // Set application timezone
        $this->setTimezone();
        
        // Register custom validation rules
        $this->registerValidationRules();
    }
    
    /**
     * Register singleton services
     */
    protected function registerSingletonServices()
    {
        // Cache service
        $this->singleton('cache', function() {
            $driver = $this->config('cache.driver', 'file');
            return new CacheService($driver);
        });
        
        // Mail service
        $this->singleton('mailer', function() {
            $config = [
                'host' => $this->config('mail.host'),
                'port' => $this->config('mail.port'),
                'username' => $this->config('mail.username'),
                'password' => $this->config('mail.password'),
                'encryption' => $this->config('mail.encryption', 'tls')
            ];
            return new MailService($config);
        });
        
        // User service
        $this->singleton('user.service', function() {
            return new UserService(
                $this->container->make(UserRepository::class),
                $this->container->make('mailer')
            );
        });
    }
    
    /**
     * Register repositories
     */
    protected function registerRepositories()
    {
        $this->bind(UserRepository::class, function() {
            return new UserRepository();
        });
        
        $this->bind(ProductRepository::class, function() {
            return new ProductRepository();
        });
        
        // Bind with dependencies
        $this->bind('App\Interfaces\ProductRepositoryInterface', ProductRepository::class);
        $this->bind('App\Interfaces\UserRepositoryInterface', UserRepository::class);
    }
    
    /**
     * Register interface bindings
     */
    protected function registerInterfaceBindings()
    {
        // Bind interfaces to concrete implementations
        $this->bind('App\Interfaces\PaymentGatewayInterface', 'App\Services\StripeGateway');
        $this->bind('App\Interfaces\FileStorageInterface', 'App\Services\LocalFileStorage');
        $this->bind('App\Interfaces\LoggerInterface', 'App\Services\FileLogger');
        
        // Bind with configuration
        $this->bind('App\Interfaces\QueueInterface', function() {
            $driver = $this->config('queue.driver', 'database');
            
            switch ($driver) {
                case 'redis':
                    return new \App\Services\RedisQueue();
                case 'sqs':
                    return new \App\Services\SqsQueue();
                default:
                    return new \App\Services\DatabaseQueue();
            }
        });
    }
    
    /**
     * Register middleware
     */
    protected function registerMiddleware()
    {
        // Bind middleware aliases for use in routes
        $this->bind('auth', AuthMiddleware::class);
        $this->bind('rate.limit', RateLimitMiddleware::class);
        $this->bind('admin', 'App\Middleware\AdminMiddleware');
        $this->bind('cors', 'App\Middleware\CorsMiddleware');
        $this->bind('csrf', 'App\Middleware\CsrfMiddleware');
        
        // Register global middleware in config
        $globalMiddleware = $this->config('middleware.global', []);
        
        if (!empty($globalMiddleware)) {
            foreach ($globalMiddleware as $middleware) {
                // Add to global middleware stack
                $this->container->make('middleware.pipeline')->add($middleware);
            }
        }
    }
    
    /**
     * Register custom services
     */
    protected function registerCustomServices()
    {
        // Register custom helper service
        $this->bind('helpers', function() {
            return new \App\Services\Helpers();
        });
        
        // Register based on environment
        if ($this->config('app.debug', false)) {
            $this->bind('debugger', function() {
                return new \App\Services\DebugService();
            });
        }
        
        // Register payment gateway based on configuration
        $this->bind('payment', function() {
            $gateway = $this->config('payment.gateway', 'stripe');
            
            switch ($gateway) {
                case 'paypal':
                    return new \App\Services\PayPalGateway(
                        $this->config('paypal.client_id'),
                        $this->config('paypal.secret')
                    );
                case 'stripe':
                default:
                    return new \App\Services\StripeGateway(
                        $this->config('stripe.api_key')
                    );
            }
        });
    }
    
    /**
     * Register global hooks
     */
    protected function registerGlobalHooks()
    {
        // Hook for logging all requests
        $this->registerHook('before_request', function($params) {
            error_log(sprintf(
                "[%s] %s %s",
                date('Y-m-d H:i:s'),
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI']
            ));
            return $params;
        }, 999); // Low priority (runs last)
        
        // Hook for measuring execution time
        $this->registerHook('before_request', function($params) {
            $params['start_time'] = microtime(true);
            return $params;
        }, 1); // High priority (runs first)
        
        // Hook for logging response time
        $this->registerHook('after_response', function($params) {
            if (isset($params['start_time'])) {
                $executionTime = (microtime(true) - $params['start_time']) * 1000;
                error_log(sprintf(
                    "Request completed in %.2f ms",
                    $executionTime
                ));
            }
            return $params;
        });
        
        // Hook for authentication check on protected routes
        $this->registerHook('before_controller', function($params) {
            $protectedRoutes = ['/admin', '/dashboard', '/profile'];
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
            
            foreach ($protectedRoutes as $route) {
                if (strpos($currentPath, $route) === 0) {
                    if (!isset($_SESSION['user_id'])) {
                        header('Location: /login');
                        exit;
                    }
                    break;
                }
            }
            
            return $params;
        });
    }
    
    /**
     * Initialize third-party services
     */
    protected function initializeThirdParty()
    {
        // Initialize Sentry for error tracking (if configured)
        if ($this->config('sentry.enabled', false)) {
            \Sentry\init([
                'dsn' => $this->config('sentry.dsn'),
                'environment' => $this->config('app.env', 'production'),
            ]);
        }
        
        // Initialize New Relic (if configured)
        if (extension_loaded('newrelic')) {
            newrelic_set_appname($this->config('app.name', 'Jephy-MVC'));
        }
    }
    
    /**
     * Set application timezone
     */
    protected function setTimezone()
    {
        $timezone = $this->config('app.timezone', 'UTC');
        date_default_timezone_set($timezone);
    }
    
    /**
     * Register custom validation rules
     */
    protected function registerValidationRules()
    {
        // Example: Register a custom validator
        // Validator::extend('custom_rule', function($field, $value, $params) {
        //     return $value === 'something';
        // });
    }
    
    /**
     * Define which services are provided (for deferred loading)
     */
    protected $provides = [
        'cache',
        'mailer',
        'user.service',
        'payment',
        'helpers',
        UserRepository::class,
        ProductRepository::class,
    ];
}