<?php
// jephy-mvc/app/hooks/DebugHooks.php
namespace App\Hooks;

use App\Core\HookManager;
use App\Core\Config;

class DebugHooks
{
    private $timeline = [];
    private $hookManager;
    private $memoryUsage = [];
    private $config;
    private $isDebugMode = false;
    
    public function __construct(HookManager $hookManager = null) 
    {
        // Get HookManager instance
        global $hookManager;
        $this->hookManager = $hookManager ?: $hookManager;
        
        // Get Config instance
        $this->config = Config::getInstance();
        
        // Check if debug mode is enabled
        $this->isDebugMode = $this->config->get('app.debug', false);
    }
    
    /**
     * Enable full request profiling
     */
    public function enableProfiling()
    {
        if (!$this->isDebugMode) {
            return;
        }
        
        if (!$this->hookManager) {
            error_log("DebugHooks: HookManager not available");
            return;
        }
        
        $this->timeline['start'] = microtime(true);
        $this->memoryUsage['start'] = memory_get_usage();
        
        // Register hooks for different lifecycle events
        $this->hookManager->registerHook('before_request', [$this, 'logBeforeRequest'], 1);
        $this->hookManager->registerHook('after_route_match', [$this, 'logRouteMatched'], 1);
        $this->hookManager->registerHook('before_controller', [$this, 'logBeforeController'], 1);
        $this->hookManager->registerHook('after_controller', [$this, 'logAfterController'], 1);
        $this->hookManager->registerHook('before_response', [$this, 'logBeforeResponse'], 1);
        $this->hookManager->registerHook('after_response', [$this, 'logAfterResponse'], 1);
        
        // Register shutdown function to output summary
        register_shutdown_function([$this, 'logSummary']);
    }
    
    /**
     * Log before request is processed
     */
    public function logBeforeRequest($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $this->timeline['before_request'] = microtime(true);
        $this->memoryUsage['before_request'] = memory_get_usage();
        
        // Log request details
        error_log(sprintf(
            "[DEBUG] Request Started: %s %s",
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI']
        ));
        
        return $params;
    }
    
    /**
     * Log after route is matched
     */
    public function logRouteMatched($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $this->timeline['route_matched'] = microtime(true);
        $this->memoryUsage['route_matched'] = memory_get_usage();
        
        if (isset($params['route'])) {
            error_log("[DEBUG] Route Matched: " . $params['route']);
        }
        
        if (isset($params['params'])) {
            error_log("[DEBUG] Route Parameters: " . json_encode($params['params']));
        }
        
        return $params;
    }
    
    /**
     * Log before controller executes
     */
    public function logBeforeController($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $this->timeline['before_controller'] = microtime(true);
        $this->memoryUsage['before_controller'] = memory_get_usage();
        
        $controller = $params['controller'] ?? 'Unknown';
        $method = $params['method'] ?? 'Unknown';
        
        error_log("[DEBUG] Executing Controller: {$controller}@{$method}");
        
        return $params;
    }
    
    /**
     * Log after controller executes
     */
    public function logAfterController($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $this->timeline['after_controller'] = microtime(true);
        $this->memoryUsage['after_controller'] = memory_get_usage();
        
        error_log("[DEBUG] Controller Execution Completed");
        
        return $params;
    }
    
    /**
     * Log before response is sent
     */
    public function logBeforeResponse($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $this->timeline['before_response'] = microtime(true);
        $this->memoryUsage['before_response'] = memory_get_usage();
        
        error_log("[DEBUG] Preparing Response");
        
        return $params;
    }
    
    /**
     * Log after response is sent
     */
    public function logAfterResponse($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $this->timeline['after_response'] = microtime(true);
        $this->memoryUsage['after_response'] = memory_get_usage();
        
        error_log("[DEBUG] Response Sent");
        
        return $params;
    }
    
    /**
     * Output summary of the request life cycle
     */
    public function logSummary()
    {
        if (!$this->isDebugMode) return;
        
        if (empty($this->timeline)) {
            return;
        }
        
        $this->timeline['end'] = microtime(true);
        $this->memoryUsage['end'] = memory_get_usage();
        
        $timings = [];
        $memory = [];
        $prev = $this->timeline['start'];
        
        // Calculate timing differences
        foreach ($this->timeline as $stage => $time) {
            if ($stage !== 'start' && $stage !== 'end') {
                $timings[$stage] = round(($time - $prev) * 1000, 2);
                $prev = $time;
            }
        }
        
        // Calculate total time
        $totalTime = round(($this->timeline['end'] - $this->timeline['start']) * 1000, 2);
        
        // Calculate memory usage
        $memoryUsed = round(($this->memoryUsage['end'] - $this->memoryUsage['start']) / 1024, 2);
        $peakMemory = round(memory_get_peak_usage() / 1024, 2);
        
        // Build summary output
        $summary = "\n";
        $summary .= "═══════════════════════════════════════════════════════════\n";
        $summary .= "📊 JEPHY-MVC DEBUG SUMMARY\n";
        $summary .= "═══════════════════════════════════════════════════════════\n";
        $summary .= "🔹 TOTAL TIME: {$totalTime} ms\n";
        $summary .= "🔹 MEMORY USAGE: {$memoryUsed} KB\n";
        $summary .= "🔹 PEAK MEMORY: {$peakMemory} KB\n\n";
        
        $summary .= "📈 BREAKDOWN:\n";
        foreach ($timings as $stage => $ms) {
            $barLength = ($totalTime > 0) ? min(50, floor($ms / ($totalTime / 50))) : 0;
            $bar = str_repeat('█', $barLength);
            $percentage = ($totalTime > 0) ? round(($ms / $totalTime) * 100, 1) : 0;
            $summary .= sprintf("  %-20s %4.1fms %3.1f%% %s\n", 
                str_replace('_', ' ', ucfirst($stage)), 
                $ms, 
                $percentage,
                $bar
            );
        }
        
        $summary .= "═══════════════════════════════════════════════════════════\n";
        
        // Output as HTML comment if not CLI
        if (php_sapi_name() !== 'cli') {
            echo "<!--\n{$summary}\n-->";
        } else {
            echo $summary;
        }
        
        // Log to file as well
        error_log($summary);
    }
    
    /**
     * Log SQL queries (to be called from your Model/Query Builder)
     */
    public function logQuery($query, $bindings = [], $time = 0)
    {
        if (!$this->isDebugMode) return [];
        
        static $queryCount = 0;
        static $slowQueries = [];
        
        $queryCount++;
        
        if ($time > 100) { // Queries slower than 100ms
            $slowQueries[] = [
                'query' => $query,
                'bindings' => $bindings,
                'time' => $time
            ];
        }
        
        error_log(sprintf(
            "[SQL #%d] %s | Time: %.2fms",
            $queryCount,
            $query,
            $time
        ));
        
        return $slowQueries;
    }
    
    /**
     * Output SQL debug info in summary
     */
    public function logQuerySummary()
    {
        if (!$this->isDebugMode) return;
        
        static $queryCount = 0;
        static $slowQueries = [];
        
        if ($queryCount > 0) {
            echo "<!--\n";
            echo "📊 SQL QUERY SUMMARY:\n";
            echo "Total Queries: {$queryCount}\n";
            
            if (!empty($slowQueries)) {
                echo "\n⚠️  SLOW QUERIES (>100ms):\n";
                foreach ($slowQueries as $i => $sq) {
                    echo sprintf("  %d. %s (%.2fms)\n", 
                        $i + 1, 
                        $sq['query'], 
                        $sq['time']
                    );
                }
            }
            echo "-->\n";
        }
    }
    
    /**
     * Log application variables and state
     */
    public function logAppState($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        // Log session data (excluding sensitive info)
        if (isset($_SESSION) && !empty($_SESSION)) {
            $safeSession = array_diff_key($_SESSION, ['password' => '', 'token' => '']);
            error_log("[DEBUG] Session Data: " . json_encode($safeSession));
        }
        
        // Log config values (excluding sensitive data)
        $safeConfig = $this->config->all();
        if (isset($safeConfig['database']['password'])) {
            $safeConfig['database']['password'] = '******';
        }
        
        return $params;
    }
    
    /**
     * Track memory usage with detailed snapshots
     */
    private $memorySnapshots = [];
    
    public function logMemoryUsage($params = [])
    {
        if (!$this->isDebugMode) return $params;
        
        $currentMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();
        
        $this->memorySnapshots[] = [
            'time' => microtime(true),
            'memory' => $currentMemory,
            'peak' => $peakMemory,
            'stage' => $params['stage'] ?? 'unknown'
        ];
        
        if (count($this->memorySnapshots) > 1) {
            $last = $this->memorySnapshots[count($this->memorySnapshots) - 2];
            $increase = $currentMemory - $last['memory'];
            $increaseKB = round($increase / 1024, 2);
            
            error_log(sprintf(
                "[MEMORY] %s: +%s KB (Total: %s KB, Peak: %s KB)",
                $params['stage'] ?? 'unknown',
                $increaseKB,
                round($currentMemory / 1024, 2),
                round($peakMemory / 1024, 2)
            ));
        }
        
        return $params;
    }
    
    /**
     * Dump variable for debugging (similar to var_dump but formatted)
     */
    public function dump($var, $label = null)
    {
        if (!$this->isDebugMode) return;
        
        $output = "\n" . str_repeat('=', 60) . "\n";
        if ($label) {
            $output .= "🔍 DEBUG DUMP: {$label}\n";
            $output .= str_repeat('-', 60) . "\n";
        }
        $output .= print_r($var, true);
        $output .= "\n" . str_repeat('=', 60) . "\n";
        
        if (php_sapi_name() !== 'cli') {
            echo "<!--\n{$output}\n-->";
        } else {
            echo $output;
        }
    }
}
