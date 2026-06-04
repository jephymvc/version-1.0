<?php
namespace App\Core\GraphQL\Shield;

use GraphQL\Type\Definition\ResolveInfo;

class Shield
{
    private $rules = [];
    private $fallbackRule;
    private $fallbackError = 'Not authorized';
    
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    public static function rule(callable $callback, array $options = []): Rule
    {
        return new Rule($callback, $options);
    }

    public static function and(...$rules): RuleChain
    {
        return new RuleChain('and', $rules);
    }

    public static function or(...$rules): RuleChain
    {
        return new RuleChain('or', $rules);
    }

    public static function not($rule): RuleChain
    {
        return new RuleChain('not', [$rule]);
    }

    public function fallback($rule, string $error = null): self
    {
        $this->fallbackRule = $rule;
        if ($error) {
            $this->fallbackError = $error;
        }
        return $this;
    }

    public function protect($fieldName, $root, $args, $context, ResolveInfo $info)
    {
        $rule = $this->findRule($fieldName, $info);
        
        if (!$rule && !$this->fallbackRule) {
            return true;
        }

        if ($rule) {
            $result = $this->evaluateRule($rule, $root, $args, $context, $info);
            if ($result instanceof ShieldError) {
                return $result;
            }
            if ($result === false) {
                return new ShieldError($rule->getErrorMessage() ?: 'Not authorized');
            }
        } elseif ($this->fallbackRule) {
            $result = $this->evaluateRule($this->fallbackRule, $root, $args, $context, $info);
            if ($result instanceof ShieldError || $result === false) {
                return new ShieldError($this->fallbackError);
            }
        }

        return true;
    }

    private function findRule($fieldName, ResolveInfo $info)
    {
        $parentType = $info->parentType->name;
        
        // Check for specific field rule
        $key = "{$parentType}.{$fieldName}";
        if (isset($this->rules[$key])) {
            return $this->rules[$key];
        }

        // Check for type-wide rule
        if (isset($this->rules[$parentType])) {
            return $this->rules[$parentType];
        }

        // Check for wildcard rule
        if (isset($this->rules['*'])) {
            return $this->rules['*'];
        }

        return null;
    }

    private function evaluateRule($rule, $root, $args, $context, $info)
    {
        if ($rule instanceof RuleChain) {
            return $rule->evaluate($root, $args, $context, $info);
        }

        if ($rule instanceof Rule) {
            return $rule->evaluate($root, $args, $context, $info);
        }

        if (is_callable($rule)) {
            return $rule($root, $args, $context, $info);
        }

        return $rule;
    }

    public static function error(string $message, array $extensions = []): ShieldError
    {
        return new ShieldError($message, $extensions);
    }
}