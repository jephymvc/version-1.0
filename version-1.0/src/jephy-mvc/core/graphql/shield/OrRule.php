<?php
namespace App\Core\GraphQL\Shield;

class OrRule extends Rule
{
    private array $rules;
    
    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }
    
    public function execute($root, $args, $context, $info)
    {
        foreach ($this->rules as $rule) {
            $result = $rule->execute($root, $args, $context, $info);
            if ($result === true) {
                return true;
            }
        }
        return false;
    }
}
