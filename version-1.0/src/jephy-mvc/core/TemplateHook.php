<?php
namespace App\Core;

class TemplateHook
{
    private $hooks;
    
    public function __construct(HookManager $hooks)
    {
        $this->hooks = $hooks;
    }
    
    public function display($hookName, $params = [])
    {
        if (empty($hookName)) {
            return '';
        }
        
        try {
            $results = $this->hooks->execWithReturn($hookName, $params);
            
            if (empty($results)) {
                return '';
            }
            
            // If results is an array, implode it
            if (is_array($results)) {
                return implode('', $results);
            }
            
            return (string)$results;
            
        } catch (\Exception $e) {
            error_log("TemplateHook error for '{$hookName}': " . $e->getMessage());
            return '';
        }
    }
}