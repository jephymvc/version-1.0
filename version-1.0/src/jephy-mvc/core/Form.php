<?php
namespace App\Core;
class Form
{
    private static $csrfToken;
    
    public static function open($action = '', $method = 'POST', $attributes = [])
    {
        $defaults = [
            'method' => strtoupper($method),
            'action' => $action,
        ];
        
        $attributes = array_merge($defaults, $attributes);
        
        $html = '<form';
			foreach ($attributes as $key => $value) {
				$html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
			}
        $html .= '>';
        
        // Add CSRF token
        $html .= self::csrf();
        
        return $html;
    }
    
    public static function close()
    {
        return '</form>';
    }
    
    public static function csrf()
    {
        if (!self::$csrfToken) {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            self::$csrfToken = $_SESSION['csrf_token'];
        }
        
        return '<input type="hidden" name="_token" value="' . self::$csrfToken . '">';
    }
    
    public static function input($type, $name, $value = '', $attributes = [])
    {
        $defaults = [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'id' => $name,
        ];
        
        $attributes = array_merge($defaults, $attributes);
        
        $html = '<input';
        foreach ($attributes as $key => $val) {
            $html .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
        }
        $html .= '>';
        
        return $html;
    }
    
    public static function text($name, $value = '', $attributes = [])
    {
        return self::input('text', $name, $value, $attributes);
    }
    
    public static function email($name, $value = '', $attributes = [])
    {
        return self::input('email', $name, $value, $attributes);
    }
    
    public static function password($name, $value = '', $attributes = [])
    {
        return self::input('password', $name, $value, $attributes);
    }
    
    public static function textarea($name, $value = '', $attributes = [])
    {
        $defaults = [
            'name' => $name,
            'id' => $name,
        ];
        
        $attributes = array_merge($defaults, $attributes);
        
        $html = '<textarea';
        foreach ($attributes as $key => $val) {
            if ($key !== 'value') {
                $html .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
            }
        }
        $html .= '>' . htmlspecialchars($value) . '</textarea>';
        
        return $html;
    }
    
    public static function select($name, $options = [], $selected = '', $attributes = [])
    {
        $defaults = [
            'name' => $name,
            'id' => $name,
        ];
        
        $attributes = array_merge($defaults, $attributes);
        
        $html = '<select';
        foreach ($attributes as $key => $val) {
            $html .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
        }
        $html .= '>';
        
        foreach ($options as $value => $label) {
            $isSelected = ($value == $selected) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value) . '"' . $isSelected . '>';
            $html .= htmlspecialchars($label);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }
    
    public static function checkbox($name, $value = '1', $checked = false, $attributes = [])
    {
        if ($checked) {
            $attributes['checked'] = 'checked';
        }
        
        return self::input('checkbox', $name, $value, $attributes);
    }
    
    public static function radio($name, $value, $checked = false, $attributes = [])
    {
        if ($checked) {
            $attributes['checked'] = 'checked';
        }
        
        return self::input('radio', $name, $value, $attributes);
    }
    
    public static function submit($value = 'Submit', $attributes = [])
    {
        $attributes['value'] = $value;
        return self::input('submit', '', '', $attributes);
    }
    
    public static function button($value = 'Button', $attributes = [])
    {
        $attributes['value'] = $value;
        return self::input('button', '', '', $attributes);
    }
    
    public static function label($for, $text, $attributes = [])
    {
        $defaults = [
            'for' => $for,
        ];
        
        $attributes = array_merge($defaults, $attributes);
        
        $html = '<label';
        foreach ($attributes as $key => $val) {
            $html .= ' ' . $key . '="' . htmlspecialchars($val) . '"';
        }
        $html .= '>' . htmlspecialchars($text) . '</label>';
        
        return $html;
    }
    
    public static function error($field, $errors = [])
    {
        if (isset($errors[$field])) {
            $html = '<div class="error-messages">';
            foreach ($errors[$field] as $error) {
                $html .= '<span class="error">' . htmlspecialchars($error) . '</span>';
            }
            $html .= '</div>';
            return $html;
        }
        
        return '';
    }
    
    public static function old($field, $default = '')
    {
        return $_POST[$field] ?? $_GET[$field] ?? $default;
    }
}