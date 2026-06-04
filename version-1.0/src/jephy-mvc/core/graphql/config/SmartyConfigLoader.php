<?php
namespace App\Core\GraphQL\Config;

class SmartyConfigLoader
{
    private static ?self $instance = null;
    private array $config = [];
    
    private function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Config file not found: {$filePath}");
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^\s*([a-zA-Z0-9_\.]+)\s*=\s*(.+)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2]);
                // Remove quotes if present
                if (preg_match('/^["\'].*["\']$/', $value)) {
                    $value = substr($value, 1, -1);
                }
                $this->config[$key] = $value;
            }
        }
    }
    
    public static function getInstance(string $filePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($filePath);
        }
        return self::$instance;
    }
    
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    public function all(): array
    {
        return $this->config;
    }
}