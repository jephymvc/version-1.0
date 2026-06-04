<?php
if ( session_status() === PHP_SESSION_NONE ) session_start();
define( 'ROOT_PATH', dirname( __DIR__ ) );
$autoloadPath = str_replace( "\\", "/", ROOT_PATH ) . '/vendor/autoload.php';

if ( file_exists( $autoloadPath ) ) {
    require_once $autoloadPath;    
}

use App\Core\{ Framework, Event, EventServiceProvider, Config};


// Register event service provider
$eventProvider = new EventServiceProvider();
$eventProvider->register();

// Enable queue for performance (optional)
if ( Config::getInstance()->get( 'site.APP_ENV' ) === 'production' ) {
    Event::enableQueue();
}

// Register shutdown function to flush event queue
register_shutdown_function(function() {
    Event::flushQueue();
});


/**
 * Helper function to render a view with organized data structure
 */
function view(string $template, array $data = []): string
{
    $smarty = Framework::getSmarty();
    $hooks = Framework::getHooks();
    
    // Execute beforeView hook to allow hooks to modify data
    $hookData = $hooks->exec('beforeView', [
        'template' => $template,
        'data' => $data,
        'smarty' => $smarty
    ]);
    
    // Merge hook data with passed data (hook data takes precedence)
    if (isset($hookData['data']) && is_array($hookData['data'])) {
        $data = array_merge($data, $hookData['data']);
    }
    
    // Check if template was modified by hook
    $template = $hookData['template'] ?? $template;
    
    // Organize data into structured arrays for Smarty dot notation
    $organizedData = organizeDataForSmarty($data);
    
    // Assign organized data to Smarty
    foreach ($organizedData as $key => $value) {
        $smarty->assign($key, $value);
    }
    
    // Get global data and organize it
    $globalDataHook = Framework::getGlobalDataHook();
    $globalData = [];
    
    if (method_exists($globalDataHook, 'getAllGlobalData')) {
        $globalData = $globalDataHook->getAllGlobalData();
    }
    
    // Organize global data
    $organizedGlobalData = organizeDataForSmarty($globalData);
    
    // Assign organized global data
    foreach ($organizedGlobalData as $key => $value) {
        // Only assign if not already set by local data
        if (!$smarty->getTemplateVars($key)) {
            $smarty->assign($key, $value);
        }
    }
    
    // Execute render hook
    $hooks->exec('renderView', [
        'template' => $template,
        'smarty' => $smarty
    ]);
    
    // Normalize template path
    $templatePath = normalizeTemplatePath($template);
    
    // Return rendered template
    return $smarty->fetch($templatePath);
}

/**
 * Normalize template path by adding .tpl extension if needed
 * 
 * @param string $template Template name/path
 * @return string Template path with .tpl extension
 */
function normalizeTemplatePath(string $template): string
{
    // If template already ends with .tpl, return as is
    if (substr($template, -4) === '.tpl') {
        return $template;
    }
    
    // Check if template has dots (like "admin.users.index")
    if (strpos($template, '.') !== false) {
        // Convert dots to directory separators
        $template = str_replace('.', '/', $template);
    }
    
    // Add .tpl extension
    return $template . '.tpl';
}

/**
 * Organize data to work with Smarty's dot notation
 * 
 * Converts flat arrays with dot notation keys into nested arrays
 * Example: ['site.name' => 'My Site'] becomes ['site' => ['name' => 'My Site']]
 */
function organizeDataForSmarty(array $data): array
{
    $result = [];
    
    foreach ($data as $key => $value) {
        // If key contains dots, create nested structure
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $current = &$result;
            
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
            // Regular key, assign directly
            $result[$key] = $value;
        }
    }
    
    return $result;
}

/**
 * Flatten nested array for storage (reverse of organizeDataForSmarty)
 */
function flattenDataForStorage(array $data, string $prefix = ''): array
{
    $result = [];
    
    foreach ($data as $key => $value) {
        $newKey = $prefix ? $prefix . '.' . $key : $key;
        
        if (is_array($value)) {
            $result = array_merge($result, flattenDataForStorage($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    
    return $result;
}

/**
 * Check if a view exists
 */
function view_exists(string $template): bool
{
    $smarty = Framework::getSmarty();
    $templatePath = normalizeTemplatePath($template);
    
    // Get template directories
    $templateDirs = $smarty->getTemplateDir();
    
    foreach ($templateDirs as $dir) {
        if (file_exists($dir . $templatePath)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Render a view if it exists, otherwise return default content
 */
function view_safe(string $template, array $data = [], string $default = ''): string
{
    if (view_exists($template)) {
        return view($template, $data);
    }
    
    // Log missing template in debug mode
    if (Framework::getInstance()->getAppDebugMode()) {
        error_log("Template not found: {$template}");
    }
    
    return $default;
}

/**
 * Get global data value using dot notation
 */
function global_data(string $key, $default = null)
{
    return Framework::getGlobalDataHook()->getGlobalData($key, $default);
}

/**
 * Set global data value using dot notation
 */
function set_global_data(string $key, $value): void
{
    Framework::getGlobalDataHook()->setGlobalData($key, $value);
}

/**
 * Refresh global data
 */
function refresh_global_data(): void
{
    Framework::getGlobalDataHook()->refreshGlobalData();
}


require_once __DIR__ . "/routes.php";

Framework::getInstance()->run();

?>
