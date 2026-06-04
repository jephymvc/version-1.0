<?php
namespace App\Core\GraphQL;

abstract class BaseMutation
{
    protected $resolver;

    public function __construct($resolver = null)
    {
        $this->resolver = $resolver;
    }

    abstract public function mutations(): array;

    protected function field(string $type, array $config = []): array
    {
        return array_merge(['type' => $type], $config);
    }

    protected function arg(string $name, string $type, array $config = []): array
    {
        return [$name => array_merge(['type' => $type], $config)];
    }
}