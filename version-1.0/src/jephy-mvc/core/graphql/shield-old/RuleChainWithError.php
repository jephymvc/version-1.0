<?php
namespace App\Core\GraphQL\Shield;

class RuleChainWithError
{
    private $chain;
    private $errorMessage;

    public function __construct($chain, string $errorMessage)
    {
        $this->chain = $chain;
        $this->errorMessage = $errorMessage;
    }

    public function evaluate($root, $args, $context, $info)
    {
        $result = $this->chain->evaluate($root, $args, $context, $info);
        
        if ($result === false) {
            return new ShieldError($this->errorMessage);
        }
        
        return $result;
    }
}