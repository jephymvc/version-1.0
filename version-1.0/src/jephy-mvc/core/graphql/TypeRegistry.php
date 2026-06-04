<?php
namespace App\Core\GraphQL;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\UnionType;

class TypeRegistry
{
    private static ?self $instance = null;
    private array $types = [];
    private array $lazyTypes = [];
    private array $resolvedTypes = [];
    
    private function __construct() {}
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a type with lazy loading
     */
    public function register(string $name, callable $factory): self
    {
        $this->lazyTypes[$name] = $factory;
        return $this;
    }
    
    /**
     * Register a type instance directly
     */
    public function set(string $name, $type): self
    {
        $this->types[$name] = $type;
        return $this;
    }
    
    /**
     * Get a type by name (resolves lazy types)
     */
    public function get(string $name)
    {
        // Already resolved
        if (isset($this->resolvedTypes[$name])) {
            return $this->resolvedTypes[$name];
        }
        
        // Direct type
        if (isset($this->types[$name])) {
            $this->resolvedTypes[$name] = $this->types[$name];
            return $this->resolvedTypes[$name];
        }
        
        // Lazy type factory
        if (isset($this->lazyTypes[$name])) {
            $type = call_user_func($this->lazyTypes[$name]);
            $this->resolvedTypes[$name] = $type;
            return $type;
        }
        
        // Built-in GraphQL types
        $builtIn = $this->getBuiltInType($name);
        if ($builtIn) {
            return $builtIn;
        }
        
        throw new \RuntimeException("Type not registered: {$name}");
    }
    
    /**
     * Get built-in GraphQL types
     */
    private function getBuiltInType(string $name)
    {
        return match($name) {
            'String' => Type::string(),
            'Int' => Type::int(),
            'Float' => Type::float(),
            'Boolean' => Type::boolean(),
            'ID' => Type::id(),
            default => null
        };
    }
    
    /**
     * Check if type exists
     */
    public function has(string $name): bool
    {
        return isset($this->types[$name]) || 
               isset($this->lazyTypes[$name]) || 
               $this->getBuiltInType($name) !== null;
    }
    
    /**
     * Resolve a type reference (string or Type instance)
     */
    public function resolve($type)
    {
        if (is_string($type)) {
            return $this->get($type);
        }
        return $type;
    }
    
    /**
     * Get all registered type names
     */
    public function getTypeNames(): array
    {
        return array_merge(
            array_keys($this->types),
            array_keys($this->lazyTypes)
        );
    }
    
    /**
     * Clear registry (useful for testing)
     */
    public function clear(): void
    {
        $this->types = [];
        $this->lazyTypes = [];
        $this->resolvedTypes = [];
    }
}