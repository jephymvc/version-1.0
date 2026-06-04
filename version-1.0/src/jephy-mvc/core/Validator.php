<?php
namespace App\Core;
class Validator
{
    
	private $errors 	= [];
    private $data 		= [];
    private $rules 		= [];
	private $allData 	= []; // Store ALL original data
    
    public function __construct( $data = [] )
    {
        $this->data 	= $data;
		$this->allData 	= $data; // Store all data
    }
    
    public function make($data, $rules)
    {
        $this->data 	= $data;
        $this->allData 	= $data; // Store all original data
        $this->rules 	= $rules;
        $this->errors 	= [];        
        return $this;
    }
	
	/**
     * Get all data merged (validated + unvalidated, with sanitization where applicable)
     */
    public function all()
    {
        $all = [];        
        // Add validated fields (sanitized)
        foreach ($this->rules as $field => $rules) {
            if (isset($this->allData[$field])) {
                $all[$field] = $this->sanitizeField($field, $this->allData[$field], $rules);
            }
        }
        
        // Add unvalidated fields (raw or basic sanitization)
        foreach ($this->allData as $field => $value) {
            if (!isset($this->rules[$field])) {
                // Apply basic sanitization to unvalidated fields for safety
                $all[$field] = $this->basicSanitize($value);
            }
        }
        
        return $all;
		
    }  

    /**
     * Basic sanitization for unvalidated fields
     */
    private function basicSanitize($value)
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }
        
        if (is_numeric($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = trim($value);
            $value = stripslashes($value);
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        
        return $value;
    }	
	
	/**
     * Get ALL data (validated + unvalidated)
     */
	public function getAllData()
    {
        return $this->allData;
    }
    
    
    public function validate()
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);           
            foreach ($rules as $rule) {
				$this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    public function errors()
    {
        return $this->errors;
    }
    
    public function validated()
    {
        $validated = [];
        
        foreach ($this->rules as $field => $rules) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->sanitizeField($field, $this->data[$field], $rules);
            }
        }
        
        return $validated;
    }
	
	public function unvalidated()
    {
        $unvalidated = [];        
        foreach ($this->allData as $field => $value) {
            if (!isset($this->rules[$field])) {
                $unvalidated[$field] = $value;
            }
        }        
        return $unvalidated;
    }
    
    
    private function applyRule($field, $rule)
    {
        $value = $this->data[$field] ?? null;
        $params = [];
        
        // Check if rule has parameters
        if (strpos($rule, ':') !== false) {
            list($rule, $paramString) = explode(':', $rule, 2);
            $params = explode(',', $paramString);
        }
        
        $method = 'validate' . ucfirst($rule);
        
        if (method_exists($this, $method)) {
            if (!$this->$method($field, $value, $params)) {
                $this->addError($field, $rule, $params);
            }
        }
    }
    
    private function sanitizeField($field, $value, $rules)
    {
        $sanitized = $value;
        
        // Apply sanitization based on rules
        $ruleList = explode('|', $rules);
        
        foreach ($ruleList as $rule) {
            $sanitized = $this->applySanitization($field, $sanitized, $rule);
        }
        
        return $sanitized;
    }
    
    public function applySanitization($field, $value, $rule)
    {
        if (strpos($rule, ':') !== false) {
            list($ruleName) = explode(':', $rule);
        } else {
            $ruleName = $rule;
        }
        
        switch ($ruleName) {
            case 'string':
                return $this->sanitizeString($value);
            case 'email':
                return $this->sanitizeEmail($value);
            case 'url':
                return $this->sanitizeUrl($value);
            case 'int':
                return $this->sanitizeInt($value);
            case 'float':
                return $this->sanitizeFloat($value);
            case 'alphanumeric':
                return $this->sanitizeAlphanumeric($value);
            case 'trim':
                return trim($value);
            case 'strip_tags':
                return strip_tags($value);
            case 'htmlspecialchars':
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            case 'escape':
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            default:
                return $value;
        }
    }
	
	
	/**
     * Check if a specific field exists in data
     */
    public function has($field)
    {
        return isset($this->allData[$field]);
    }
    
    /**
     * Get a specific field value
     */
    public function get($field, $default = null)
    {
        return $this->allData[$field] ?? $default;
    }
    
    /**
     * Get only specific fields
     */
    public function only($fields)
    {
        $fields = is_array($fields) ? $fields : func_get_args();
        $result = [];
        
        foreach ($fields as $field) {
            if (isset($this->allData[$field])) {
                $result[$field] = $this->allData[$field];
            }
        }
        
        return $result;
    }


    /**
     * Get all except specific fields
     */
    public function except($fields)
    {
        $fields = is_array($fields) ? $fields : func_get_args();
        $result = $this->allData;
        
        foreach ($fields as $field) {
            unset($result[$field]);
        }
        
        return $result;
    }

    /**
     * Fill missing fields with default values
     */
    public function fillDefaults($defaults)
    {
        foreach ($defaults as $field => $defaultValue) {
            if (!isset($this->allData[$field])) {
                $this->allData[$field] = $defaultValue;
            }
        }
        
        return $this;
    }
		
    
    // ==================== SANITIZATION METHODS ====================
    
	private function sanitizeString($value)
	{
		$value = trim(strip_tags($value));
		return htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
	}
    
    private function sanitizeEmail($value)
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }
    
    private function sanitizeUrl($value)
    {
        return filter_var($value, FILTER_SANITIZE_URL);
    }
    
	private function sanitizeInt($value)
	{
		$value = trim($value);
		return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
	}

	private function sanitizeFloat($value)
	{
		$value = trim($value);
		return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
	}
		
    private function sanitizeAlphanumeric($value)
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', $value);
    }
    
    // ==================== VALIDATION METHODS ====================
    
    private function validateRequired($field, $value, $params)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif (is_array($value) && count($value) < 1) {
            return false;
        }
        
        return true;
    }
    
    private function validateString($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return is_string($value);
    }
    
    private function validateEmail($field, $value, $params)
    {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validateUrl($field, $value, $params)
    {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
    
    private function validateMin($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        $min = $params[0] ?? 0;
        
        if (is_numeric($value)) {
            return $value >= $min;
        } elseif (is_string($value)) {
            return mb_strlen($value) >= $min;
        } elseif (is_array($value)) {
            return count($value) >= $min;
        }
        
        return false;
    }
    
    private function validateMax($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        $max = $params[0] ?? PHP_INT_MAX;
        
        if (is_numeric($value)) {
            return $value <= $max;
        } elseif (is_string($value)) {
            return mb_strlen($value) <= $max;
        } elseif (is_array($value)) {
            return count($value) <= $max;
        }
        
        return false;
    }
    
    private function validateBetween($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        $min = $params[0] ?? 0;
        $max = $params[1] ?? PHP_INT_MAX;
        
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        } elseif (is_string($value)) {
            $length = mb_strlen($value);
            return $length >= $min && $length <= $max;
        }
        
        return false;
    }
    
    private function validateRegex($field, $value, $params)
    {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        $pattern = $params[0] ?? '';
        return preg_match($pattern, $value) === 1;
    }
    
    private function validateIn($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return in_array($value, $params);
    }
    
    private function validateNotIn($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return !in_array($value, $params);
    }
    
    private function validateSame($field, $value, $params)
    {
        $otherField = $params[0] ?? '';
        $otherValue = $this->data[$otherField] ?? null;
        
        return $value === $otherValue;
    }
    
    private function validateDifferent($field, $value, $params)
    {
        $otherField = $params[0] ?? '';
        $otherValue = $this->data[$otherField] ?? null;
        
        return $value !== $otherValue;
    }
    
    private function validateBoolean($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        $acceptable = [true, false, 0, 1, '0', '1', 'true', 'false'];
        return in_array($value, $acceptable, true);
    }
    
    private function validateNumeric($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return is_numeric($value);
    }
    
    private function validateInteger($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    private function validateFloat($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
    
    private function validateArray($field, $value, $params)
    {
        if (is_null($value)) {
            return true;
        }
        
        return is_array($value);
    }
    
    private function validateDate($field, $value, $params)
    {
        if (is_null($value) || $value === '') {
            return true;
        }
        
        $format = $params[0] ?? 'Y-m-d';
        $date = DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }
    
    private function validateConfirmed($field, $value, $params)
    {
        $confirmationField = $field . '_confirmation';
        return isset($this->data[$confirmationField]) && $value === $this->data[$confirmationField];
    }
    
    // ==================== ERROR HANDLING ====================
    
    private function addError($field, $rule, $params)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $message = $this->getErrorMessage($field, $rule, $params);
        $this->errors[$field][] = $message;
    }
    
    private function getErrorMessage($field, $rule, $params)
    {
		
        $messages = [
            'required' 	=> "The ". $field ." field is required.",
            'email' 	=> "The ". $field ." must be a valid email address.",
            'url' 		=> "The ". $field ." must be a valid URL.",
            'min' 		=> "The ". $field ." must be at least ". $params[0] .".",
            'max' 		=> "The ". $field ." may not be greater than ". $params[0] .".",
            'between' 	=> "The ". $field ." must be between ".$params[0] ." and ". $params[1] .".",
            'regex' 	=> "The ". $field ." format is invalid.",
            'in' 		=> "The selected ". $field ." is invalid.",
            'not_in' 	=> "The selected ". $field ." is invalid.",
            'same' 		=> "The ". $field ."} and ". $params[0] ." must match.",
            'different' => "The ". $field ." and ". $params[0] ." must be different.",
            'boolean' 	=> "The ". $field ." field must be true or false.",
            'numeric' 	=> "The ". $field ." must be a number.",
            'integer' 	=> "The ". $field ." must be an integer.",
            'float' 	=> "The ". $field ." must be a float.",
            'array' 	=> "The ". $field ." must be an array.",
            'date' 		=> "The ". $field ." is not a valid date.",
            'confirmed' => "The ". $field ." confirmation does not match.",
            'string' 	=> "The ". $field ." must be a string.",
        ];
        
        return $messages[$rule] ?? "The ". $field ." field is invalid.";
		
    }
	
}



