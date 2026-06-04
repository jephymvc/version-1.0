<?php
namespace App\Core\GraphQL\Shield;

class ShieldResult
{
    private $allowed;
    private $message;
    private $details;

    public function __construct($allowed, $message = '', $details = [])
    {
        $this->allowed = $allowed;
        $this->message = $message;
        $this->details = $details;
    }

    public function isAllowed(): bool
    {
        return $this->allowed === true;
    }

    public function isDenied(): bool
    {
        return $this->allowed === false;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}