<?php
namespace App\Core\GraphQL\Shield;

class CallbackRule extends Rule
{
    private $callback;
    
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    
    public function execute($root, $args, $context, $info)
    {
        return call_user_func($this->callback, $root, $args, $context, $info);
    }
}