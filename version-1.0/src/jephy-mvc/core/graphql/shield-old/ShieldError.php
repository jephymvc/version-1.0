<?php
namespace App\Core\GraphQL\Shield;

class ShieldError
{
    private $message;
    private $extensions;

    public function __construct(string $message, array $extensions = [])
    {
        $this->message = $message;
        $this->extensions = $extensions;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'extensions' => array_merge($this->extensions, [
                'code' => 'FORBIDDEN'
            ])
        ];
    }
}