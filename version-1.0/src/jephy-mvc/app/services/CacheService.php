<?php
// jephy-mvc/app/services/CacheService.php
namespace App\Services;

use Core\BaseService;

class CacheService extends BaseService
{
    /**
     * @var string Cache driver (file, redis, memcached)
     */
    private $driver;
    
    /**
     * @var string Cache directory for file driver
     */
    private $cacheDir;
    
    /**
     * @var \Redis Redis instance
     */
    private $redis;
    
    /**
     * @var \Memcached Memcached instance
     */
    private $memcached;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->initializeCache();
    }
    
    /**
     * Initialize cache driver
     */
    private function initializeCache()
    {
        $this->driver = $this->config('cache.driver', 'file');
        $this->cacheDir = $this->config('cache.path', '../jephy-mvc/app/cache/');
        
        // Ensure cache directory exists
        if ($this->driver === 'file') {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0755, true);
            }
        }
        
        // Initialize Redis if configured
        if ($this->driver === 'redis') {
            $this->initializeRedis();
        }
        
        // Initialize Memcached if configured
        if ($this->driver === 'memcached') {
            $this->initializeMemcached();
        }
        
        $this->log('Cache service initialized', ['driver' => $this->driver]);
    }
    
    /**
     * Initialize Redis connection
     */
    private function initializeRedis()
    {
        try {
            $this->redis = new \Redis();
            $host = $this->config('redis.host', '127.0.0.1');
            $port = $this->config('redis.port', 6379);
            $password = $this->config('redis.password', null);
            $database = $this->config('redis.database', 0);
            
            $this->redis->connect($host, $port);
            
            if ($password) {
                $this->redis->auth($password);
            }
            
            $this->redis->select($database);
            
            $this->log('Redis connected', ['host' => $host, 'port' => $port]);
        } catch (\Exception $e) {
            $this->log('Redis connection failed, falling back to file cache', ['error' => $e->getMessage()]);
            $this->driver = 'file';
        }
    }
    
    /**
     * Initialize Memcached connection
     */
    private function initializeMemcached()
    {
        try {
            $this->memcached = new \Memcached();
            $servers = $this->config('memcached.servers', [['host' => '127.0.0.1', 'port' => 11211]]);
            
            foreach ($servers as $server) {
                $this->memcached->addServer($server['host'], $server['port']);
            }
            
            $this->log('Memcached connected');
        } catch (\Exception $e) {
            $this->log('Memcached connection failed, falling back to file cache', ['error' => $e->getMessage()]);
            $this->driver = 'file';
        }
    }
    
    /**
     * Get a cached value
     */
    public function get($key, $default = null)
    {
        $this->log('Getting cache', ['key' => $key]);
        
        switch ($this->driver) {
            case 'redis':
                $value = $this->redis->get($key);
                return $value !== false ? unserialize($value) : $default;
                
            case 'memcached':
                $value = $this->memcached->get($key);
                return $value !== false ? $value : $default;
                
            case 'file':
            default:
                return $this->getFromFile($key, $default);
        }
    }
    
    /**
     * Get multiple cached values
     */
    public function getMultiple($keys)
    {
        $this->log('Getting multiple cache keys', ['keys' => $keys]);
        
        $results = [];
        
        switch ($this->driver) {
            case 'redis':
                $values = $this->redis->mget($keys);
                foreach ($keys as $index => $key) {
                    $results[$key] = $values[$index] !== false ? unserialize($values[$index]) : null;
                }
                break;
                
            case 'memcached':
                $values = $this->memcached->getMulti($keys);
                foreach ($keys as $key) {
                    $results[$key] = $values[$key] ?? null;
                }
                break;
                
            case 'file':
            default:
                foreach ($keys as $key) {
                    $results[$key] = $this->getFromFile($key);
                }
                break;
        }
        
        return $results;
    }
    
    /**
     * Set a cached value
     */
    public function set($key, $value, $ttl = 3600)
    {
        $this->log('Setting cache', ['key' => $key, 'ttl' => $ttl]);
        
        $serialized = serialize($value);
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->setex($key, $ttl, $serialized);
                
            case 'memcached':
                return $this->memcached->set($key, $value, $ttl);
                
            case 'file':
            default:
                return $this->setToFile($key, $serialized, $ttl);
        }
    }
    
    /**
     * Set multiple cached values
     */
    public function setMultiple($items, $ttl = 3600)
    {
        $this->log('Setting multiple cache items', ['count' => count($items), 'ttl' => $ttl]);
        
        $success = true;
        
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete a cached value
     */
    public function delete($key)
    {
        $this->log('Deleting cache', ['key' => $key]);
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->del($key) > 0;
                
            case 'memcached':
                return $this->memcached->delete($key);
                
            case 'file':
            default:
                return $this->deleteFromFile($key);
        }
    }
    
    /**
     * Delete multiple cached values
     */
    public function deleteMultiple($keys)
    {
        $this->log('Deleting multiple cache keys', ['keys' => $keys]);
        
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Clear all cache
     */
    public function clear()
    {
        $this->log('Clearing all cache');
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->flushDB();
                
            case 'memcached':
                return $this->memcached->flush();
                
            case 'file':
            default:
                return $this->clearFileCache();
        }
    }
    
    /**
     * Check if cache key exists
     */
    public function has($key)
    {
        switch ($this->driver) {
            case 'redis':
                return $this->redis->exists($key) > 0;
                
            case 'memcached':
                $this->memcached->get($key);
                return $this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
                
            case 'file':
            default:
                return file_exists($this->getCacheFilePath($key));
        }
    }
    
    /**
     * Get cache from file
     */
    private function getFromFile($key, $default = null)
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (!file_exists($filePath)) {
            return $default;
        }
        
        $content = file_get_contents($filePath);
        $data = unserialize($content);
        
        // Check if expired
        if ($data['expires'] < time()) {
            $this->deleteFromFile($key);
            return $default;
        }
        
        return unserialize($data['value']);
    }
    
    /**
     * Set cache to file
     */
    private function setToFile($key, $value, $ttl)
    {
        $filePath = $this->getCacheFilePath($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filePath, serialize($data)) !== false;
    }
    
    /**
     * Delete cache from file
     */
    private function deleteFromFile($key)
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Clear all file cache
     */
    private function clearFileCache()
    {
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFilePath($key)
    {
        $safeKey = md5($key);
        return $this->cacheDir . '/' . $safeKey . '.cache';
    }
    
    /**
     * Clear cache by pattern (wildcard matching)
     */
    public function clearByPattern($pattern)
    {
        $this->log('Clearing cache by pattern', ['pattern' => $pattern]);
        
        switch ($this->driver) {
            case 'redis':
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
                break;
                
            case 'file':
            default:
                $pattern = str_replace('*', '.*', $pattern);
                $pattern = str_replace('?', '.', $pattern);
                $files = glob($this->cacheDir . '*.cache');
                
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    $data = unserialize($content);
                    
                    if ($data && isset($data['key']) && preg_match("/{$pattern}/", $data['key'])) {
                        unlink($file);
                    }
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $stats = [
            'driver' => $this->driver,
            'prefix' => $this->config('cache.prefix', 'jephy_')
        ];
        
        switch ($this->driver) {
            case 'redis':
                $info = $this->redis->info();
                $stats['memory_used'] = $info['used_memory_human'] ?? 'unknown';
                $stats['keys'] = $this->redis->dbSize();
                break;
                
            case 'memcached':
                $stats['version'] = $this->memcached->getVersion();
                break;
                
            case 'file':
                $files = glob($this->cacheDir . '*.cache');
                $stats['file_count'] = count($files);
                $stats['cache_dir'] = $this->cacheDir;
                break;
        }
        
        return $stats;
    }
    
    /**
     * Remember a value (get or set)
     */
    public function remember($key, $ttl, callable $callback)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Increment a counter
     */
    public function increment($key, $amount = 1)
    {
        $this->log('Incrementing cache counter', ['key' => $key, 'amount' => $amount]);
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->incrBy($key, $amount);
                
            case 'memcached':
                return $this->memcached->increment($key, $amount);
                
            case 'file':
            default:
                $value = $this->get($key, 0);
                $value += $amount;
                $this->set($key, $value, 3600);
                return $value;
        }
    }
    
    /**
     * Decrement a counter
     */
    public function decrement($key, $amount = 1)
    {
        $this->log('Decrementing cache counter', ['key' => $key, 'amount' => $amount]);
        
        switch ($this->driver) {
            case 'redis':
                return $this->redis->decrBy($key, $amount);
                
            case 'memcached':
                return $this->memcached->decrement($key, $amount);
                
            case 'file':
            default:
                $value = $this->get($key, 0);
                $value -= $amount;
                $this->set($key, $value, 3600);
                return $value;
        }
    }
}