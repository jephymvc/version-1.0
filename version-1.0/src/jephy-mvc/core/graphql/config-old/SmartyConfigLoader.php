<?php
namespace App\Core\GraphQL\Config;

class SmartyConfigLoader
{
    private $configFile;
    private $config = [];
    private static $instance = null;

    private function __construct($configFile = null)
    {
        $this->configFile = $configFile;
        $this->load();
    }

    public static function getInstance($configFile = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configFile);
        }
        return self::$instance;
    }

    private function load()
    {
        if (!file_exists($this->configFile)) {
            return;
        }

        $lines = file($this->configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentSection = 'global';

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            if (strpos($line, '[') === 0 && strrpos($line, ']') === strlen($line) - 1) {
                $currentSection = trim($line, '[]');
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $this->config[$currentSection][$key] = $value;
            }
        }
    }

    public function get($key, $default = null, $section = 'graphql')
    {
        if (strpos($key, '.') !== false) {
            list($section, $key) = explode('.', $key, 2);
        }

        return $this->config[$section][$key] ?? $default;
    }
}