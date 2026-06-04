<?php
namespace App\Listeners;

use App\Core\EventSubscriber;
use App\Core\Logger;
use App\Core\Event;

class SystemEventSubscriber
{
    use EventSubscriber;
    
    /**
     * Define the events this subscriber listens to
     */
    protected $subscribedEvents = [
        'system.error' => 'onSystemError',
        'system.warning' => 'onSystemWarning',
        'system.info' => 'onSystemInfo',
        'system.debug' => 'onSystemDebug',
        'system.maintenance' => 'onMaintenanceMode',
        'system.maintenance.end' => 'onMaintenanceEnd',
        'system.cache.clear' => 'onCacheClear',
        'system.session.cleanup' => 'onSessionCleanup',
        'system.database.query.slow' => 'onSlowQuery',
        'system.memory.high' => 'onHighMemoryUsage',
        'system.cpu.high' => 'onHighCpuUsage',
        'system.startup' => 'onSystemStartup',
        'system.shutdown' => 'onSystemShutdown',
    ];
    
    /**
     * Handle system error events
     */
    public function onSystemError($payload, $eventName = null)
    {
        Logger::error('System Error', [
            'message' => $payload['message'] ?? 'Unknown error',
            'file' => $payload['file'] ?? null,
            'line' => $payload['line'] ?? null,
            'trace' => $payload['trace'] ?? null,
            'code' => $payload['code'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        // Send critical errors to monitoring service
        if ($this->isCriticalError($payload)) {
            $this->sendToMonitoringService($payload);
        }
        
        // Send email alert for critical errors
        if ($this->shouldSendAlert($payload)) {
            $this->sendErrorAlert($payload);
        }
    }
    
    /**
     * Handle system warning events
     */
    public function onSystemWarning($payload, $eventName = null)
    {
        Logger::warning('System Warning', [
            'message' => $payload['message'] ?? 'Unknown warning',
            'file' => $payload['file'] ?? null,
            'line' => $payload['line'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null
        ]);
        
        // Track warning count in session
        $this->trackWarning($payload);
    }
    
    /**
     * Handle system info events
     */
    public function onSystemInfo($payload, $eventName = null)
    {
        Logger::info('System Info', $payload);
        
        // Log to separate info log if configured
        if (defined('SYSTEM_INFO_LOG') && SYSTEM_INFO_LOG) {
            $this->writeInfoLog($payload);
        }
    }
    
    /**
     * Handle system debug events
     */
    public function onSystemDebug($payload, $eventName = null)
    {
        if ($_ENV['APP_ENV'] === 'development' || $_ENV['APP_DEBUG'] === 'true') {
            Logger::debug('System Debug', $payload);
        }
    }
    
    /**
     * Handle maintenance mode start
     */
    public function onMaintenanceMode($payload, $eventName = null)
    {
        Logger::warning('Maintenance mode activated', [
            'reason' => $payload['reason'] ?? 'Scheduled maintenance',
            'duration' => $payload['duration'] ?? 'Unknown',
            'started_by' => $payload['user_id'] ?? 'system',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        // Notify admins
        $this->notifyAdmins('Maintenance Mode Started', $payload);
        
        // Create maintenance file
        $this->createMaintenanceFile($payload);
    }
    
    /**
     * Handle maintenance mode end
     */
    public function onMaintenanceEnd($payload, $eventName = null)
    {
        Logger::info('Maintenance mode deactivated', [
            'ended_by' => $payload['user_id'] ?? 'system',
            'duration' => $payload['duration'] ?? 'Unknown'
        ]);
        
        // Notify admins
        $this->notifyAdmins('Maintenance Mode Ended', $payload);
        
        // Remove maintenance file
        $this->removeMaintenanceFile();
    }
    
    /**
     * Handle cache clear events
     */
    public function onCacheClear($payload, $eventName = null)
    {
        Logger::info('Cache cleared', [
            'cache_type' => $payload['type'] ?? 'all',
            'cleared_by' => $payload['user_id'] ?? 'system',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Clear opcache if enabled
        if (function_exists('opcache_reset')) {
            opcache_reset();
            Logger::debug('OPCache reset after cache clear');
        }
    }
    
    /**
     * Handle session cleanup events
     */
    public function onSessionCleanup($payload, $eventName = null)
    {
        $expiredCount = $payload['expired_count'] ?? 0;
        $totalCount = $payload['total_count'] ?? 0;
        
        Logger::info('Session cleanup completed', [
            'expired_sessions' => $expiredCount,
            'total_sessions' => $totalCount,
            'cleaned_by' => $payload['trigger'] ?? 'cron'
        ]);
    }
    
    /**
     * Handle slow database queries
     */
    public function onSlowQuery($payload, $eventName = null)
    {
        $queryTime = $payload['time'] ?? 0;
        $threshold = $payload['threshold'] ?? 1.0;
        
        Logger::warning('Slow database query detected', [
            'query' => $payload['query'] ?? 'Unknown query',
            'time' => $queryTime . 's',
            'threshold' => $threshold . 's',
            'file' => $payload['file'] ?? null,
            'line' => $payload['line'] ?? null,
            'trace' => $payload['trace'] ?? null
        ]);
        
        // Log to slow query log file
        $this->logSlowQuery($payload);
    }
    
    /**
     * Handle high memory usage
     */
    public function onHighMemoryUsage($payload, $eventName = null)
    {
        $memoryUsage = $payload['memory_usage'] ?? 0;
        $memoryLimit = $payload['memory_limit'] ?? ini_get('memory_limit');
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        
        Logger::warning('High memory usage detected', [
            'current_usage' => round($memoryUsage, 2) . ' MB',
            'peak_usage' => round($peakMemory, 2) . ' MB',
            'memory_limit' => $memoryLimit,
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'file' => $payload['file'] ?? null,
            'line' => $payload['line'] ?? null
        ]);
        
        // Trigger garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Handle high CPU usage
     */
    public function onHighCpuUsage($payload, $eventName = null)
    {
        Logger::warning('High CPU usage detected', [
            'cpu_usage' => $payload['cpu_usage'] ?? 'Unknown',
            'process' => $payload['process'] ?? null,
            'url' => $_SERVER['REQUEST_URI'] ?? null
        ]);
    }
    
    /**
     * Handle system startup
     */
    public function onSystemStartup($payload, $eventName = null)
    {
        Logger::info('System started', [
            'php_version' => PHP_VERSION,
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'start_time' => date('Y-m-d H:i:s'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
        
        // Check system requirements
        $this->checkSystemRequirements();
    }
    
    /**
     * Handle system shutdown
     */
    public function onSystemShutdown($payload, $eventName = null)
    {
        $executionTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;
        
        Logger::info('System shutdown', [
            'execution_time' => round($executionTime, 3) . 's',
            'peak_memory' => round($memoryUsage, 2) . ' MB',
            'url' => $_SERVER['REQUEST_URI'] ?? null,
            'status_code' => http_response_code()
        ]);
    }
    
    /**
     * Check if error is critical
     */
    private function isCriticalError($payload)
    {
        $criticalCodes = [500, 503, 504, 0];
        $code = $payload['code'] ?? 0;
        
        return in_array($code, $criticalCodes) || 
               strpos($payload['message'] ?? '', 'Fatal') !== false ||
               strpos($payload['message'] ?? '', 'Uncaught') !== false;
    }
    
    /**
     * Check if alert should be sent
     */
    private function shouldSendAlert($payload)
    {
        // Don't send alerts in development
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            return false;
        }
        
        // Check if this error type should trigger an alert
        $alertTriggers = ['database', 'connection', 'fatal', 'critical', 'emergency'];
        $message = strtolower($payload['message'] ?? '');
        
        foreach ($alertTriggers as $trigger) {
            if (strpos($message, $trigger) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send error to monitoring service
     */
    private function sendToMonitoringService($payload)
    {
        // Example: Send to Sentry, Bugsnag, or custom monitoring API
        if (defined('MONITORING_API_URL') && MONITORING_API_URL) {
            $data = [
                'type' => 'error',
                'message' => $payload['message'] ?? 'Unknown error',
                'file' => $payload['file'] ?? null,
                'line' => $payload['line'] ?? null,
                'trace' => $payload['trace'] ?? null,
                'environment' => $_ENV['APP_ENV'] ?? 'production',
                'timestamp' => time()
            ];
            
            // Async HTTP request to monitoring service
            $this->asyncHttpRequest(MONITORING_API_URL, $data);
        }
    }
    
    /**
     * Send error alert email
     */
    private function sendErrorAlert($payload)
    {
        $adminEmails = defined('ADMIN_EMAILS') ? explode(',', ADMIN_EMAILS) : [];
        
        if (empty($adminEmails)) {
            return;
        }
        
        $subject = '[ALERT] System Error on ' . ($_SERVER['HTTP_HOST'] ?? 'Unknown Host');
        $message = "Error: " . ($payload['message'] ?? 'Unknown error') . "\n";
        $message .= "File: " . ($payload['file'] ?? 'Unknown') . "\n";
        $message .= "Line: " . ($payload['line'] ?? 'Unknown') . "\n";
        $message .= "URL: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        foreach ($adminEmails as $email) {
            mail(trim($email), $subject, $message, "From: system@" . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
    }
    
    /**
     * Track warning count
     */
    private function trackWarning($payload)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['system_warnings'])) {
                $_SESSION['system_warnings'] = [];
            }
            
            $_SESSION['system_warnings'][] = [
                'message' => $payload['message'] ?? 'Unknown warning',
                'time' => time(),
                'url' => $_SERVER['REQUEST_URI'] ?? null
            ];
            
            // Keep only last 100 warnings
            if (count($_SESSION['system_warnings']) > 100) {
                $_SESSION['system_warnings'] = array_slice($_SESSION['system_warnings'], -100);
            }
        }
    }
    
    /**
     * Write to info log file
     */
    private function writeInfoLog($payload)
    {
        $logFile = __DIR__ . '/../../logs/info.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = sprintf(
            "[%s] %s - %s%s",
            date('Y-m-d H:i:s'),
            $payload['type'] ?? 'info',
            $payload['message'] ?? '',
            PHP_EOL
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Notify administrators
     */
    private function notifyAdmins($subject, $payload)
    {
        Logger::info("Admin notification: {$subject}", $payload);
        
        // Could also send email, Slack message, etc.
        if (defined('SLACK_WEBHOOK_URL') && SLACK_WEBHOOK_URL) {
            $this->sendSlackNotification($subject, $payload);
        }
    }
    
    /**
     * Send Slack notification
     */
    private function sendSlackNotification($subject, $payload)
    {
        $data = [
            'text' => "*{$subject}*\n" . json_encode($payload, JSON_PRETTY_PRINT),
            'username' => 'System Monitor',
            'icon_emoji' => ':warning:'
        ];
        
        $this->asyncHttpRequest(SLACK_WEBHOOK_URL, $data);
    }
    
    /**
     * Create maintenance mode file
     */
    private function createMaintenanceFile($payload)
    {
        $maintenanceFile = __DIR__ . '/../../storage/framework/maintenance.php';
        $maintenanceDir = dirname($maintenanceFile);
        
        if (!is_dir($maintenanceDir)) {
            mkdir($maintenanceDir, 0755, true);
        }
        
        $content = '<?php' . PHP_EOL;
        $content .= '// Maintenance mode activated at ' . date('Y-m-d H:i:s') . PHP_EOL;
        $content .= 'return [' . PHP_EOL;
        $content .= '    "time" => ' . time() . ',' . PHP_EOL;
        $content .= '    "message" => "' . addslashes($payload['message'] ?? 'Down for maintenance') . '",' . PHP_EOL;
        $content .= '    "retry" => ' . ($payload['retry_after'] ?? 60) . PHP_EOL;
        $content .= '];' . PHP_EOL;
        
        file_put_contents($maintenanceFile, $content);
    }
    
    /**
     * Remove maintenance mode file
     */
    private function removeMaintenanceFile()
    {
        $maintenanceFile = __DIR__ . '/../../storage/framework/maintenance.php';
        
        if (file_exists($maintenanceFile)) {
            unlink($maintenanceFile);
        }
    }
    
    /**
     * Log slow query to separate file
     */
    private function logSlowQuery($payload)
    {
        $logFile = __DIR__ . '/../../logs/slow-queries.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = sprintf(
            "[%s] %.3fs - %s - %s%s",
            date('Y-m-d H:i:s'),
            $payload['time'] ?? 0,
            $_SERVER['REQUEST_URI'] ?? '/',
            $payload['query'] ?? '',
            PHP_EOL
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check system requirements
     */
    private function checkSystemRequirements()
    {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'pdo' => extension_loaded('pdo'),
            'json' => extension_loaded('json'),
            'session' => extension_loaded('session'),
            'openssl' => extension_loaded('openssl'),
        ];
        
        $failed = array_filter($requirements, function($pass) {
            return !$pass;
        });
        
        if (!empty($failed)) {
            Logger::critical('System requirements check failed', [
                'failed_requirements' => array_keys($failed)
            ]);
        }
    }
    
    /**
     * Make async HTTP request
     */
    private function asyncHttpRequest($url, $data)
    {
        $postData = http_build_query($data);
        
        $parts = parse_url($url);
        $host = $parts['host'];
        $port = $parts['port'] ?? 80;
        $path = $parts['path'] ?? '/';
        
        $fp = fsockopen($host, $port, $errno, $errstr, 1);
        
        if ($fp) {
            $out = "POST {$path} HTTP/1.1\r\n";
            $out .= "Host: {$host}\r\n";
            $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $out .= "Content-Length: " . strlen($postData) . "\r\n";
            $out .= "Connection: Close\r\n\r\n";
            $out .= $postData;
            
            fwrite($fp, $out);
            fclose($fp);
        }
    }
}

