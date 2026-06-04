<?php
namespace App\Core\GraphQL;

use App\Core\GraphQL\Shield\Rule;
use App\Core\GraphQL\Shield\AndRule;
use App\Core\GraphQL\Shield\OrRule;
use App\Core\GraphQL\Shield\CallbackRule;
use App\Core\GraphQL\Shield\ShieldError;

class Shield
{
    private array $rules = [];
    private array $errorMessages = [];
    private ?callable $fallbackRule = null;
    private ?string $fallbackMessage = null;

    public function __construct(array $rules = [])
    {
        foreach ($rules as $path => $rule) {
            $this->rule($path, $rule);
        }
    }

    public function rule(string $path, $rule): self
    {
        $this->rules[$path] = $rule;
        return $this;
    }

    public function error(string $message): self
    {
        if ($lastPath = array_key_last($this->rules)) {
            $this->errorMessages[$lastPath] = $message;
        }
        return $this;
    }

    public function fallback(callable $rule, string $message = 'Access denied'): self
    {
        $this->fallbackRule = $rule;
        $this->fallbackMessage = $message;
        return $this;
    }

    public function protect(string $fieldPath, $root, $args, $context, $info)
    {
        $rule = $this->getRuleForPath($fieldPath);
        
        if ($rule === null) {
            $rule = $this->getRuleForPath('*');
        }
        
        if ($rule === null && $this->fallbackRule) {
            $rule = $this->fallbackRule;
        }
        
        if ($rule === null) {
            return new ShieldError($this->fallbackMessage ?? 'Access denied');
        }
        
        try {
            $result = $this->executeRule($rule, $root, $args, $context, $info);
            
            if ($result === false) {
                $message = $this->errorMessages[$fieldPath] ?? 
                          $this->errorMessages['*'] ?? 
                          'Access denied';
                return new ShieldError($message);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            return new ShieldError($e->getMessage());
        }
    }

    public function getErrorMessage(string $path): ?string
    {
        return $this->errorMessages[$path] ?? $this->errorMessages['*'] ?? null;
    }

    private function getRuleForPath(string $path): ?callable
    {
        if (isset($this->rules[$path])) {
            return $this->rules[$path];
        }
        
        foreach ($this->rules as $pattern => $rule) {
            if ($this->matchesPattern($path, $pattern)) {
                return $rule;
            }
        }
        
        return null;
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return preg_match($regex, $path) === 1;
    }

    private function executeRule($rule, $root, $args, $context, $info)
    {
        if (is_callable($rule)) {
            return $rule($root, $args, $context, $info);
        }
        
        if ($rule instanceof Rule) {
            return $rule->execute($root, $args, $context, $info);
        }
        
        return $rule;
    }

    public static function and(...$rules): Rule
    {
        return new AndRule($rules);
    }

    public static function or(...$rules): Rule
    {
        return new OrRule($rules);
    }

    public static function rule(callable $rule): Rule
    {
        return new CallbackRule($rule);
    }
}