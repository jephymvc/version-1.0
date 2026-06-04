<?php
namespace App\Core;

use DateTime;
use DateTimeZone;

class Logger
{
    // Log levels
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    
    // Log level priorities (higher number = higher priority)
    private static $levelPriorities = [
        self::DEBUG     => 100,
        self::INFO      => 200,
        self::NOTICE    => 250,
        self::WARNING   => 300,
        self::ERROR     => 400,
        self::CRITICAL  => 500,
        self::ALERT     => 550,
        self::EMERGENCY => 600,
    ];
    
    private static $instance = null;
    private static $config = [];
    private static $logFile = null;
    private static $minLevel = null;
    private static $buffer = [];
    private static $bufferEnabled = false;
    
    /**
     * Initialize logger with configuration
     */
    public static function init(array $config = [])
    {
        $defaultConfig = [
            'driver' => 'file', // file, database, syslog
            'log_dir' => __DIR__ . '/../../logs/',
            'log_file' => 'app.log',
            'date_format' => 'Y-m-d H:i:s',
            'timezone' => 'UTC',
            'min_level' => self::DEBUG,
            'buffer_logs' => false,
            'buffer_size' => 50,
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'max_files' => 7, // Keep 7 days of logs
        ];
        
        self::$config = array_merge($defaultConfig, $config);
        
        // Set timezone
        date_default_timezone_set(self::$config['timezone']);
        
        // Set minimum log level
        self::$minLevel = self::getLevelPriority(self::$config['min_level']);
        
        // Create log directory if it doesn't exist
        if (self::$config['driver'] === 'file' && !is_dir(self::$config['log_dir'])) {
            mkdir(self::$config['log_dir'], 0755, true);
        }
        
        // Set log file path
        self::$logFile = self::$config['log_dir'] . '/' . self::$config['log_file'];
        
        // Enable buffer if configured
        self::$bufferEnabled = self::$config['buffer_logs'];
        
        // Register shutdown function to flush buffer
        register_shutdown_function(function() {
            self::flushBuffer();
        });
    }
    
    /**
     * Get logger instance (Singleton pattern)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::init();
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Log an emergency message
     */
    public static function emergency($message, array $context = [])
    {
        self::log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log an alert message
     */
    public static function alert($message, array $context = [])
    {
        self::log(self::ALERT, $message, $context);
    }
    
    /**
     * Log a critical message
     */
    public static function critical($message, array $context = [])
    {
        self::log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log an error message
     */
    public static function error($message, array $context = [])
    {
        self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning($message, array $context = [])
    {
        self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Log a notice message
     */
    public static function notice($message, array $context = [])
    {
        self::log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, array $context = [])
    {
        self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, array $context = [])
    {
        self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Main logging method
     */
    public static function log($level, $message, array $context = [])
    {
        // Check if level meets minimum requirement
        $levelPriority = self::getLevelPriority($level);
        
        if ($levelPriority > self::$minLevel) {
            return false;
        }
        
        // Format message with context
        $formattedMessage = self::interpolate($message, $context);
        
        // Prepare log entry
        $logEntry = [
            'timestamp' => date(self::$config['date_format']),
            'level' => strtoupper($level),
            'message' => $formattedMessage,
            'context' => $context,
            'ip' => self::getClientIp(),
            'user_id' => self::getCurrentUserId(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ];
        
        // Buffer or write directly
        if (self::$bufferEnabled) {
            self::$buffer[] = $logEntry;
            
            if (count(self::$buffer) >= self::$config['buffer_size']) {
                self::flushBuffer();
            }
        } else {
            self::writeLog($logEntry);
        }
        
        return true;
    }
    
    /**
     * Write log entry to appropriate driver
     */
    private static function writeLog(array $logEntry)
    {
        switch (self::$config['driver']) {
            case 'database':
                self::writeToDatabase($logEntry);
                break;
            case 'syslog':
                self::writeToSyslog($logEntry);
                break;
            case 'file':
            default:
                self::writeToFile($logEntry);
                break;
        }
    }
    
    /**
     * Write log to file
     */
    private static function writeToFile(array $logEntry)
    {
        // Rotate log file if it's too large
        if (file_exists(self::$logFile) && filesize(self::$logFile) > self::$config['max_file_size']) {
            self::rotateLogFile();
        }
        
        $logLine = self::formatLogLine($logEntry);
        
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Write log to database
     */
    private static function writeToDatabase(array $logEntry)
    {
        try {
            $db = Database::getInstance();
            
            $data = [
                'level' => $logEntry['level'],
                'message' => $logEntry['message'],
                'context' => json_encode($logEntry['context']),
                'ip' => $logEntry['ip'],
                'user_id' => $logEntry['user_id'],
                'url' => $logEntry['url'],
                'method' => $logEntry['method'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('logs', $data);
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            self::writeToFile($logEntry);
        }
    }
    
    /**
     * Write log to syslog
     */
    private static function writeToSyslog(array $logEntry)
    {
        $priority = self::getSyslogPriority($logEntry['level']);
        $message = sprintf(
            "[%s] %s - %s (User: %s, IP: %s, URI: %s)",
            $logEntry['level'],
            $logEntry['message'],
            json_encode($logEntry['context']),
            $logEntry['user_id'] ?? 'guest',
            $logEntry['ip'],
            $logEntry['url']
        );
        
        syslog($priority, $message);
    }
    
    /**
     * Format log line for file output
     */
    private static function formatLogLine(array $logEntry)
    {
        $format = self::$config['log_format'] ?? '[%s] %s: %s [User: %s] [IP: %s] [URI: %s]%s';
        
        $context = !empty($logEntry['context']) ? ' ' . json_encode($logEntry['context']) : '';
        
        return sprintf(
            $format,
            $logEntry['timestamp'],
            $logEntry['level'],
            $logEntry['message'],
            $logEntry['user_id'] ?? 'guest',
            $logEntry['ip'],
            $logEntry['url'],
            $context
        ) . PHP_EOL;
    }
    
    /**
     * Interpolate context variables into message
     */
    private static function interpolate($message, array $context = [])
    {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            } elseif (is_array($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            }
        }
        
        return strtr($message, $replace);
    }
    
    /**
     * Rotate log file
     */
    private static function rotateLogFile()
    {
        $fileInfo = pathinfo(self::$logFile);
        $filename = $fileInfo['filename'];
        $extension = $fileInfo['extension'] ?? 'log';
        
        for ($i = self::$config['max_files']; $i > 0; $i--) {
            $oldFile = self::$config['log_dir'] . '/' . $filename . '.' . $i . '.' . $extension;
            $newFile = self::$config['log_dir'] . '/' . $filename . '.' . ($i + 1) . '.' . $extension;
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Rename current log file
        $rotatedFile = self::$config['log_dir'] . '/' . $filename . '.1.' . $extension;
        rename(self::$logFile, $rotatedFile);
    }
    
    /**
     * Flush log buffer
     */
    private static function flushBuffer()
    {
        if (empty(self::$buffer)) {
            return;
        }
        
        foreach (self::$buffer as $logEntry) {
            self::writeLog($logEntry);
        }
        
        self::$buffer = [];
    }
    
    /**
     * Get log level priority
     */
    private static function getLevelPriority($level)
    {
        return self::$levelPriorities[$level] ?? 200;
    }
    
    /**
     * Convert log level to syslog priority
     */
    private static function getSyslogPriority($level)
    {
        $map = [
            self::EMERGENCY => LOG_EMERG,
            self::ALERT => LOG_ALERT,
            self::CRITICAL => LOG_CRIT,
            self::ERROR => LOG_ERR,
            self::WARNING => LOG_WARNING,
            self::NOTICE => LOG_NOTICE,
            self::INFO => LOG_INFO,
            self::DEBUG => LOG_DEBUG,
        ];
        
        return $map[$level] ?? LOG_INFO;
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIp()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get current authenticated user ID
     */
    private static function getCurrentUserId()
    {
        if (isset($_SESSION['user_session']['user']['id'])) {
            return $_SESSION['user_session']['user']['id'];
        }
        
        if (isset($_SESSION['user_session']['user']['user_id'])) {
            return $_SESSION['user_session']['user']['user_id'];
        }
        
        return null;
    }
    
    /**
     * Clear old logs
     */
    public static function clearOldLogs($days = 30)
    {
        if (self::$config['driver'] !== 'file') {
            return false;
        }
        
        $files = glob(self::$config['log_dir'] . '/*.log');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > ($days * 86400)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Get log file content
     */
    public static function getLogs($lines = 100)
    {
        if (self::$config['driver'] !== 'file' || !file_exists(self::$logFile)) {
            return [];
        }
        
        $logs = [];
        $file = new \SplFileObject(self::$logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key() + 1;
        
        $startLine = max(0, $totalLines - $lines);
        
        for ($i = $startLine; $i < $totalLines; $i++) {
            $file->seek($i);
            $line = $file->current();
            if (!empty(trim($line))) {
                $logs[] = trim($line);
            }
        }
        
        return $logs;
    }
    
    /**
     * Search logs for specific pattern
     */
    public static function searchLogs($pattern, $limit = 100)
    {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $results = [];
        $file = new \SplFileObject(self::$logFile, 'r');
        
        while (!$file->eof() && count($results) < $limit) {
            $line = $file->fgets();
            if (strpos($line, $pattern) !== false) {
                $results[] = trim($line);
            }
        }
        
        return $results;
    }
}