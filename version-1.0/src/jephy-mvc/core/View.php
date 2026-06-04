<?php
namespace App\Core;
// app/core/View.php (extended version)

use App\Core\Framework;

class View
{
    private $smarty;
    private $data = [];
    private $composers = [];
    private static $globalData = [];
    private static $registeredComposers = [];
    
    public function __construct()
    {
        $this->smarty = Framework::getSmarty();
    }
    
    /**
     * Register a view composer
     * 
     * @param string|array $views View pattern(s) (e.g., 'users.*', 'profile.show', '*')
     * @param callable|string $callback Callback function or class@method
     */
    public static function composer($views, $callback)
    {
        $patterns = is_array($views) ? $views : [$views];
        
        foreach ($patterns as $pattern) {
            if (!isset(self::$registeredComposers[$pattern])) {
                self::$registeredComposers[$pattern] = [];
            }
            self::$registeredComposers[$pattern][] = $callback;
        }
    }
    
    /**
     * Set a view variable
     */
    public function set($key, $value)
    {
        // Handle dot notation for nested arrays
        if (strpos($key, '.') !== false) {
            $this->setNested($key, $value);
        } else {
            $this->data[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Set nested value using dot notation
     */
    private function setNested($key, $value)
    {
        $parts = explode('.', $key);
        $current = &$this->data;
        
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }
    
    /**
     * Set multiple view variables
     */
    public function setMultiple(array $data)
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }
    
    /**
     * Set a global variable (available in all views)
     */
    public static function setGlobal($key, $value)
    {
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $current = &self::$globalData;
            
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current[$part] = $value;
                } else {
                    if (!isset($current[$part]) || !is_array($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        } else {
            self::$globalData[$key] = $value;
        }
    }
    
    /**
     * Get all data for this view
     */
    public function getData()
    {
        return array_merge(self::$globalData, $this->data);
    }
    
    /**
     * Run composers for a template
     * 
     * @param string $template Template name
     */
    private function runComposers($template)
    {
        foreach (self::$registeredComposers as $pattern => $composers) {
            if ($this->matchesPattern($template, $pattern)) {
                foreach ($composers as $composer) {
                    $this->executeComposer($composer);
                }
            }
        }
    }
    
    /**
     * Check if template matches pattern
     * 
     * @param string $template Template name
     * @param string $pattern Pattern with wildcards
     * @return bool
     */
    private function matchesPattern($template, $pattern)
    {
        // Match all views
        if ($pattern === '*') {
            return true;
        }
        
        // Convert pattern to regex
        // 'users.*' becomes '/^users\..*/'
        $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
        
        return preg_match($regex, $template) === 1;
    }
    
    /**
     * Execute a composer
     * 
     * @param callable|string $composer
     */
    private function executeComposer($composer)
    {
        if (is_callable($composer)) {
            // Callable composer (closure)
            call_user_func($composer, $this);
            
        } elseif (is_string($composer) && strpos($composer, '@') !== false) {
            // Class@method format
            list($class, $method) = explode('@', $composer);
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    call_user_func([$instance, $method], $this);
                }
            }
            
        } elseif (is_string($composer) && class_exists($composer)) {
            // Class with compose method
            $instance = new $composer();
            if (method_exists($instance, 'compose')) {
                $instance->compose($this);
            }
        }
    }
    
    /**
     * Render a template
     */
    public function render($template)
    {
        // Run composers BEFORE rendering
        $this->runComposers($template);
        
        // Merge global and local data
        $allData = $this->getData();
        
        // Assign all data to Smarty
        foreach ($allData as $key => $value) {
            $this->smarty->assign($key, $value);
        }
        
        // Normalize template path
        $templatePath = $this->normalizePath($template);
        
        // Execute beforeRender hook
        $hooks = Framework::getHooks();
        $hooks->exec('beforeRender', [
            'template' => $templatePath,
            'data' => $allData,
            'smarty' => $this->smarty
        ]);
        
        // Return rendered template
        return $this->smarty->fetch($templatePath);
    }
    
    /**
     * Normalize template path
     */
    private function normalizePath($template)
    {
        // If already has .tpl, return as is
        if (substr($template, -4) === '.tpl') {
            return $template;
        }
        
        // Replace dots with directory separators
        $template = str_replace('.', '/', $template);
        
        // Add .tpl extension
        return $template . '.tpl';
    }
    
    /**
     * Display a template directly
     */
    public function display($template)
    {
        echo $this->render($template);
    }
    
    /**
     * Check if a view exists
     */
    public function exists($template)
    {
        $templatePath = $this->normalizePath($template);
        $templateDirs = $this->smarty->getTemplateDir();
        
        foreach ($templateDirs as $dir) {
            if (file_exists($dir . $templatePath)) {
                return true;
            }
        }
        
        return false;
    }
}
