<?php
namespace App\Core\GraphQL\Shield;

class Rule
{
    private $callback;
    private $errorMessage;
    private $cache = false;
    private $fragment = false;

    public function __construct(callable $callback, array $options = [])
    {
        $this->callback = $callback;
        $this->errorMessage = $options['error'] ?? null;
        $this->cache = $options['cache'] ?? false;
        $this->fragment = $options['fragment'] ?? false;
    }

    public function error(string $message): self
    {
        $this->errorMessage = $message;
        return $this;
    }

    public function evaluate($root, $args, $context, $info)
    {
        $result = call_user_func($this->callback, $root, $args, $context, $info);
        
        if ($result instanceof ShieldError) {
            return $result;
        }

        return $result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}