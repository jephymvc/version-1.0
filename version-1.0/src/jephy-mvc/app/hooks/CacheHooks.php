<?php
namespace App\Hooks;
use App\Core\Framework;
use App\Core\HookManager;
class CacheHooks
{
    private $cache = [];
    
    public function registerHooks($hooks)
    {
        $hooks->registerHook('beforeProductLoad', [$this, 'checkCache'], 5); // High priority
        $hooks->registerHook('afterProductLoad', [$this, 'storeInCache'], 15); // Low priority
    }
    
    public function checkCache($params)
    {
        $cacheKey = "product_{$params['id']}";
        if (isset($this->cache[$cacheKey])) {
            // Return cached data instead of loading from database
            return $this->cache[$cacheKey];
        }
        return $params; // Continue normal loading
    }
    
    public function storeInCache($product)
    {
        $cacheKey = "product_{$product['id']}";
        $this->cache[$cacheKey] = $product;
        return $product;
    }
}