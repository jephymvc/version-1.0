<?php
namespace App\Middleware;

use App\Core\Middleware;

class LogMiddleware extends Middleware
{
    /**
     * Request/Response logging middleware
     * 
     * @param mixed $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // Log request start
        $startTime = microtime(true);
        $this->logRequest($request);
        
        $response = $next($request);
        
        // Log response
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->logResponse($request, $response, $duration);
        
        return $response;
    }
    
    /**
     * Log request details
     * 
     * @param mixed $request
     * @return void
     */
    private function logRequest($request)
    {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $request->method,
            'path' => $request->path,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logFile = dirname(__DIR__, 2) . '/storage/logs/requests.log';
        file_put_contents($logFile, json_encode($log) . "\n", FILE_APPEND);
    }
    
    /**
     * Log response details
     * 
     * @param mixed $request
     * @param mixed $response
     * @param float $duration
     * @return void
     */
    private function logResponse($request, $response, $duration)
    {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $request->method,
            'path' => $request->path,
            'duration_ms' => $duration,
            'status' => http_response_code()
        ];
        
        $logFile = dirname(__DIR__, 2) . '/storage/logs/responses.log';
        file_put_contents($logFile, json_encode($log) . "\n", FILE_APPEND);
    }
    
    /**
     * Terminate method - called after response is sent
     * 
     * @param mixed $request
     * @param mixed $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $log = [
            'memory_usage' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $logFile = dirname(__DIR__, 2) . '/storage/logs/performance.log';
        file_put_contents($logFile, json_encode($log) . "\n", FILE_APPEND);
    }
}