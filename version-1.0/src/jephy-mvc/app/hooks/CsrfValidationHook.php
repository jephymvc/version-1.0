<?php
namespace App\Hooks;
use App\Core\Framework;
use App\Core\HookManager;
use App\Core\Csrf;
class CsrfValidationHook
{
    public function registerHooks(HookManager $hooks)
    {
        $hooks->registerHook('beforeController', [$this, 'validateRequest'], 5);
        $hooks->registerHook('beforeRender', [$this, 'addCsrfToTemplates'], 10);
    }
    
    public function validateRequest($params)
    {
        // Skip CSRF for GET, HEAD, OPTIONS
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        
        if (!in_array($_SERVER['REQUEST_METHOD'], $safeMethods)) {
            try {
                Csrf::validate();
            } catch (Exception $e) {
                $this->handleCsrfFailure($e);
            }
        }
        
        return $params;
    }
    
    public function addCsrfToTemplates($params)
    {
        $smarty = Framework::getSmarty();
        
        try {
            // Add CSRF token to all templates
            $smarty->assign('csrf_token', Csrf::token());
            $smarty->assign('csrf_field', Csrf::field());
            $smarty->assign('csrf_meta', Csrf::meta());
        } catch (Exception $e) {
            // If Csrf class doesn't exist, assign empty values
            $smarty->assign('csrf_token', '');
            $smarty->assign('csrf_field', '');
            $smarty->assign('csrf_meta', '');
            
            // Log error in development
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log('CSRF Error: ' . $e->getMessage());
            }
        }
        
        return $params;
    }
    
    private function handleCsrfFailure($exception)
    {
        // ALWAYS return JSON for AJAX-like requests
        if ($this->isAjax() || $this->isJsonRequest()) {
            $this->sendJsonError( 'CSRF token mismatch! Please refresh the page and try again!', 419);
        }
        
        // Regular form submission
        if (class_exists('GlobalDataHook')) {
            GlobalDataHook::addFlash('error', 'Session expired. Please try again.');
        }
        
        // Redirect back or to home
        $redirect = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $redirect);
        exit;
    }
    
    private function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    private function isJsonRequest()
    {
		
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';        
        return strpos($accept, 'application/json') !== false || strpos($contentType, 'application/json') !== false;
		
    }
    
    private function sendJsonError($message, $code = 419)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => 'CSRF Validation Failed',
            'message' => $message,
            'code' => $code,
            'timestamp' => date('c')
        ];
        
        echo json_encode($response);
        exit;
    }
}