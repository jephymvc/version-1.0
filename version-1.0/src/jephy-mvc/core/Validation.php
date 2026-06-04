<?php
namespace Core;
// jephy-mvc/core/Validation.php

class Validation
{
   
	protected $errors 	= [];
    protected $data 	= [];
    
    // Available validation rules
    protected $rules = [
        'required', 'email', 'min', 'max', 'between', 'numeric', 
        'integer', 'string', 'array', 'boolean', 'date', 'url',
        'ip', 'confirmed', 'unique', 'exists', 'regex', 'in'
    ];
    
    public function validate(array $data, array $rules)
    {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $fieldRules = explode('|', $fieldRules);
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    protected function applyRule($field, $value, $rule)
    {
        // Required rule
        if ($rule === 'required' && empty($value) && $value !== '0') {
            $this->addError($field, 'This field is required');
            return;
        }
        
        // Skip other validation if field is empty and not required
        if (empty($value) && $value !== '0') {
            return;
        }
        
        // Email rule
        if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Must be a valid email address');
        }
        
        // Min length
        if (strpos($rule, 'min:') === 0) {
            $min = substr($rule, 4);
            if (strlen($value) < $min) {
                $this->addError($field, "Must be at least {$min} characters");
            }
        }
        
        // Max length
        if (strpos($rule, 'max:') === 0) {
            $max = substr($rule, 4);
            if (strlen($value) > $max) {
                $this->addError($field, "Must not exceed {$max} characters");
            }
        }
        
        // Between
        if (strpos($rule, 'between:') === 0) {
            $range = substr($rule, 8);
            list($min, $max) = explode(',', $range);
            if (strlen($value) < $min || strlen($value) > $max) {
                $this->addError($field, "Must be between {$min} and {$max} characters");
            }
        }
        
        // Numeric
        if ($rule === 'numeric' && !is_numeric($value)) {
            $this->addError($field, 'Must be a number');
        }
        
        // Integer
        if ($rule === 'integer' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'Must be an integer');
        }
        
        // Date
        if ($rule === 'date' && !strtotime($value)) {
            $this->addError($field, 'Must be a valid date');
        }
        
        // URL
        if ($rule === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'Must be a valid URL');
        }
        
        // Confirmed (password confirmation)
        if ($rule === 'confirmed') {
            $confirmationField = $field . '_confirmation';
            if ($value !== ($this->data[$confirmationField] ?? null)) {
                $this->addError($field, 'Confirmation does not match');
            }
        }
    }
    
    protected function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
    
    public function getFirstError($field = null)
    {
        if ($field) {
            return $this->errors[$field][0] ?? null;
        }
        
        foreach ($this->errors as $field => $messages) {
            return $messages[0];
        }
        
        return null;
    }
}