<?php
namespace App\Hooks;
use App\Core\Framework;
use App\Core\HookManager;
class OutputHooks
{
    public function registerHooks(HookManager $hooks)
    {
        $hooks->registerHook('afterRender', [$this, 'minifyHtml'], 100);
        #	$hooks->registerHook('afterRender', [$this, 'injectAnalytics'], 90);
        #	$hooks->registerHook('afterRender', [$this, 'addDebugBar'], 999);
    }
    
    public function minifyHtml($params)
    {
        if (isset($params['output'])) {
            $output = $params['output'];
            
            // Remove extra whitespace
            $output = preg_replace('/\s+/', ' ', $output);
            
            // Remove whitespace between tags
            $output = preg_replace('/>\s+</', '><', $output);
            
            $params['output'] = $output;
        }
        
        if (isset($params['content'])) {
            $content = $params['content'];
            $content = preg_replace('/\s+/', ' ', $content);
            $content = preg_replace('/>\s+</', '><', $content);
            $params['content'] = $content;
        }
        
        return $params;
    }
    
    public function injectAnalytics($params)
    {
        $analyticsCode = "
        <!-- Google Analytics -->
        <script async src='https://www.googletagmanager.com/gtag/js?id=UA-XXXXX-Y'></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'UA-XXXXX-Y');
        </script>";
        
        if (isset($params['output'])) {
            // Inject before closing </head> tag
            $params['output'] = str_replace('</head>', $analyticsCode . '</head>', $params['output']);
        }
        
        if (isset($params['content'])) {
            $params['content'] = str_replace('</head>', $analyticsCode . '</head>', $params['content']);
        }
        
        return $params;
    }
    
    public function addDebugBar($params)
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $debugBar = "
            <div style='position:fixed;bottom:0;left:0;right:0;background:#333;color:white;padding:10px;font-family:monospace;'>
                Memory: " . round(memory_get_usage()/1024/1024, 2) . "MB | 
                Time: " . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'])*1000, 2) . "ms
            </div>";
            
            if (isset($params['output'])) {
                $params['output'] = str_replace('</body>', $debugBar . '</body>', $params['output']);
            }
            
            if (isset($params['content'])) {
                $params['content'] = str_replace('</body>', $debugBar . '</body>', $params['content']);
            }
        }
        
        return $params;
    }
}