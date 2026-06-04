<?php
namespace App\Hooks;
use App\Core\Framework;
use App\Core\HookManager;
class ValidationHook
{
    public function registerHooks(HookManager $hooks)
    {
        $hooks->registerHook('beforeController', [$this, 'validateCsrf'], 2);
        $hooks->registerHook('beforeRender', [$this, 'addValidationHelpers'], 5);
    }
    
    public function validateCsrf($params)
    {
       		
	   // Only validate POST, PUT, PATCH, DELETE requests
        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        
        if (in_array($_SERVER['REQUEST_METHOD'], $methods)) {
            $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';			     
            if ( empty( $token ) || $token !== ( $_SESSION['csrf_token'] ?? '' ) ) 
			{
                http_response_code(419);
                echo "CSRF token mismatch. Please refresh the page and try again.";
                exit;
            }
        }
        
        return $params;
    }
    
    public function addValidationHelpers($params)
    {
        $smarty = Framework::getSmarty();
        
        // Register Form helper in Smarty
        $smarty->registerPlugin('function', 'form_open', [$this, 'smartyFormOpen']);
        $smarty->registerPlugin('function', 'form_close', [$this, 'smartyFormClose']);
        $smarty->registerPlugin('function', 'form_input', [$this, 'smartyFormInput']);
        $smarty->registerPlugin('function', 'form_error', [$this, 'smartyFormError']);
        $smarty->registerPlugin('function', 'old', [$this, 'smartyOld']);
        
        return $params;
    }
    
    public function smartyFormOpen($params, $smarty)
    {
        $action = $params['action'] ?? '';
        $method = $params['method'] ?? 'POST';
        unset($params['action'], $params['method']);
        
        return Form::open($action, $method, $params);
    }
    
    public function smartyFormClose($params, $smarty)
    {
        return Form::close();
    }
    
    public function smartyFormInput($params, $smarty)
    {
        $type = $params['type'] ?? 'text';
        $name = $params['name'] ?? '';
        $value = $params['value'] ?? Form::old($name);
        unset($params['type'], $params['name'], $params['value']);
        
        return Form::input($type, $name, $value, $params);
    }
    
    public function smartyFormError($params, $smarty)
    {
        $field = $params['field'] ?? '';
        $errors = $smarty->getTemplateVars('errors') ?? [];
        
        return Form::error($field, $errors);
    }
    
    public function smartyOld($params, $smarty)
    {
        $field = $params['field'] ?? '';
        $default = $params['default'] ?? '';
        
        return Form::old($field, $default);
    }
}