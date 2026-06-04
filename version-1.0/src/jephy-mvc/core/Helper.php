<?php
// app/core/Helper.php
namespace App\Core;

use App\Core\View;
use App\Core\Framework;
use App\Hooks\GlobalDataHook;

class Helper
{
    /**
     * Initialize all helpers
     */
    public static function init()
    {
        // This class exists just to autoload the helper functions
        // The actual functions are in the global namespace below
    }
}

// Global helper functions
#	if (!function_exists('view')) {
#	    /**
#	     * Create a new view instance
#	     */
#	    function view($template = null, $data = [])
#	    {
#	        $view = new View();
#	        
#	        if ($template !== null) {
#	            $view->setMultiple($data);
#	            return $view->render($template);
#	        }
#	        
#	        return $view;
#	    }
#	}


if (!function_exists('view')) {
    /**
     * Render a Smarty view and return the content
     * Automatically injects global data from GlobalDataHook
     * 
     * @param string $template Template path (e.g., 'home/index.tpl')
     * @param array $data Additional data to assign to template
     * @return string Rendered content
     */
    function view($template, $data = [])
    {
        $smarty = Framework::getSmarty();
        
        if ($smarty === null) {
            error_log("Smarty not available in view() function");
            return "Error: Template engine not available";
        }
        
        // === AUTOMATICALLY INJECT GLOBAL DATA ===
        try {
            $globalDataHook = GlobalDataHook::getInstance();
            $globalDataHook->initialize();
            $globalDataHook->forceInjectToSmarty();
        } catch (\Exception $e) {
            error_log("Failed to inject global data in view(): " . $e->getMessage());
        }
        
        // Assign additional data from view() call (overrides global data if keys conflict)
        foreach ($data as $key => $value) {
            $smarty->assign($key, $value);
        }
        
        // Get the rendered content
        try {
            $content = $smarty->fetch($template);
            return $content;
        } catch (Exception $e) {
            error_log("Template error: " . $e->getMessage());
            return "Error rendering template: " . $e->getMessage();
        }
    }
}

if (!function_exists('render')) {
    /**
     * Render and display a Smarty view
     * 
     * @param string $template Template path
     * @param array $data Additional data to assign
     */
    function render($template, $data = [])
    {
        echo view($template, $data);
    }
}


if (!function_exists('dd')) {
    function dd($var)
    {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        die();
    }
}

if (!function_exists('view_global')) {
    /**
     * Set a global view variable
     */
    function view_global($key, $value)
    {
        View::setGlobal($key, $value);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset($path)
    {
        $config = Framework::getInstance()->getConfig();
        $baseUrl = rtrim($config->get('site.url', ''), '/');
        return $baseUrl . '/public/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url($path = '')
    {
        $config = Framework::getInstance()->getConfig();
        $baseUrl = rtrim($config->get('site.url', ''), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('old')) {
    /**
     * Get old form input value
     */
    function old($key, $default = '')
    {
        return $_SESSION['old'][$key] ?? $default;
    }
}

if (!function_exists('session')) {
    /**
     * Get session value
     */
    function session($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    /**
     * Get config value
     */
    function config($key, $default = null)
    {
        $config = Framework::getInstance()->getConfig();
        return $config->get($key, $default);
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variable for debugging
     */
    function dump($var)
    {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
}



if (!function_exists('redirect')) {
    /**
     * Redirect to a URL
     */
    function redirect($path, $statusCode = 302)
    {
        header('Location: ' . url($path), true, $statusCode);
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * Redirect back to previous page
     */
    function back()
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? url('/');
        header('Location: ' . $referer);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate or get CSRF token
     */
    function csrf_token()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF field HTML
     */
    function csrf_field()
    {
        return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate method field HTML for PUT, PATCH, DELETE
     */
    function method_field($method)
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('flash')) {
    /**
     * Set or get flash message
     */
    function flash($key = null, $message = null)
    {
        if ($key === null) {
            return $_SESSION['flash'] ?? [];
        }
        
        if ($message === null) {
            $value = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $value;
        }
        
        $_SESSION['flash'][$key] = $message;
        return true;
    }
}

if (!function_exists('auth_check')) {
    /**
     * Check if user is authenticated
     */
    function auth_check()
    {
        $config = Framework::getInstance()->getConfig();
        $guestKey = $config->get('session.guest', '');
        $adminKey = $config->get('session.admin', '');
        
        return (isset($_SESSION["user_session"][$guestKey]) && !empty($_SESSION["user_session"][$guestKey])) ||
               (isset($_SESSION["user_session"][$adminKey]) && !empty($_SESSION["user_session"][$adminKey]));
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if user is admin
     */
    function is_admin()
    {
        $config = Framework::getInstance()->getConfig();
        $adminKey = $config->get('session.admin', '');
        
        return isset($_SESSION["user_session"][$adminKey]) && !empty($_SESSION["user_session"][$adminKey]);
    }
}

if (!function_exists('current_url')) {
    /**
     * Get current URL
     */
    function current_url()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('str_slug')) {
    /**
     * Generate URL friendly slug from string
     */
    function str_slug($string, $separator = '-')
    {
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
        $string = preg_replace('/\s+/', $separator, trim($string));
        return strtolower($string);
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date
     */
    function format_date($date, $format = 'Y-m-d')
    {
        if ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        if (is_string($date)) {
            return date($format, strtotime($date));
        }
        
        return '';
    }
}