<?php
namespace App\Core\GraphQL\Shield;

class RuleChain
{
    private $operator;
    private $rules;

    public function __construct(string $operator, array $rules)
    {
        $this->operator = $operator;
        $this->rules = $rules;
    }

    public function evaluate($root, $args, $context, $info)
    {
        $results = [];
        
        foreach ($this->rules as $rule) {
            if ($rule instanceof Rule) {
                $results[] = $rule->evaluate($root, $args, $context, $info);
            } elseif ($rule instanceof RuleChain) {
                $results[] = $rule->evaluate($root, $args, $context, $info);
            } elseif (is_callable($rule)) {
                $results[] = $rule($root, $args, $context, $info);
            } else {
                $results[] = $rule;
            }
        }

        return $this->applyOperator($results);
    }

    private function applyOperator(array $results)
    {
        switch ($this->operator) {
            case 'and':
                return $this->applyAnd($results);
            case 'or':
                return $this->applyOr($results);
            case 'not':
                return $this->applyNot($results);
            default:
                return false;
        }
    }

    private function applyAnd(array $results)
    {
        foreach ($results as $result) {
            if ($result instanceof ShieldError) {
                return $result;
            }
            if (!$result) {
                return false;
            }
        }
        return true;
    }

    private function applyOr(array $results)
    {
        foreach ($results as $result) {
            if ($result === true) {
                return true;
            }
        }
        
        foreach ($results as $result) {
            if ($result instanceof ShieldError) {
                return $result;
            }
        }
        
        return false;
    }

    private function applyNot(array $results)
    {
        $result = $results[0] ?? false;
        
        if ($result instanceof ShieldError) {
            return $result;
        }
        
        return !$result;
    }

    public function error(string $message): RuleChainWithError
    {
        return new RuleChainWithError($this, $message);
    }
}