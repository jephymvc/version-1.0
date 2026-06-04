<?php
namespace App\Core\GraphQL;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

abstract class BaseType
{
    protected $type;
    protected $name;

    abstract protected function fields(): array;

    public function __construct()
    {
        $this->type = new ObjectType([
            'name' => $this->name(),
            'description' => $this->description(),
            'fields' => function() {
                return $this->resolveFields();
            }
        ]);
    }

    protected function name(): string
    {
        if (!$this->name) {
            $className = (new \ReflectionClass($this))->getShortName();
            $this->name = str_replace('Type', '', $className);
        }
        return $this->name;
    }

    protected function description(): ?string
    {
        return null;
    }

    protected function resolveFields(): array
    {
        $fields = $this->fields();
        $resolved = [];

        foreach ($fields as $name => $config) {
            if (is_string($config)) {
                $resolved[$name] = [
                    'type' => $this->parseTypeString($config)
                ];
            } else {
                if (isset($config['type']) && is_string($config['type'])) {
                    $config['type'] = $this->parseTypeString($config['type']);
                }
                $resolved[$name] = $config;
            }
        }

        return $resolved;
    }

    private function parseTypeString(string $typeString)
    {
        $isNonNull = strpos($typeString, '!') !== false;
        $isList = strpos($typeString, '[') !== false;
        
        $cleanType = str_replace(['[', ']', '!'], '', $typeString);
        
        $type = $this->getType($cleanType);
        
        if ($isList) {
            $type = Type::listOf($type);
        }
        
        if ($isNonNull) {
            $type = Type::nonNull($type);
        }
        
        return $type;
    }

    protected function getType($name)
    {
        return TypeRegistry::get($name);
    }

    protected function id() { return Type::id(); }
    protected function string() { return Type::string(); }
    protected function int() { return Type::int(); }
    protected function float() { return Type::float(); }
    protected function boolean() { return Type::boolean(); }

    public function getType()
    {
        return $this->type;
    }
	
}