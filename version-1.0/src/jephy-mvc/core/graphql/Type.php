<?php
namespace App\Core\GraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use App\Core\GraphQL\TypeRegistry;

class Type
{
    /**
     * Create a non-null type from a string reference
     */
    public static function nonNull($type): GraphQLType
    {
        $resolvedType = self::resolveType($type);
        return GraphQLType::nonNull($resolvedType);
    }
    
    /**
     * Create a list type from a string reference
     */
    public static function listOf($type): GraphQLType
    {
        $resolvedType = self::resolveType($type);
        return GraphQLType::listOf($resolvedType);
    }
    
    /**
     * Get a type by string name
     */
    public static function get(string $name)
    {
        return TypeRegistry::getInstance()->get($name);
    }
    
    /**
     * Register a type
     */
    public static function register(string $name, callable $factory): void
    {
        TypeRegistry::getInstance()->register($name, $factory);
    }
    
    /**
     * Resolve type (string or Type instance)
     */
    private static function resolveType($type)
    {
        if (is_string($type)) {
            return TypeRegistry::getInstance()->get($type);
        }
        return $type;
    }
    
    // Built-in types as static methods for convenience
    public static function string(): GraphQLType
    {
        return GraphQLType::string();
    }
    
    public static function int(): GraphQLType
    {
        return GraphQLType::int();
    }
    
    public static function float(): GraphQLType
    {
        return GraphQLType::float();
    }
    
    public static function boolean(): GraphQLType
    {
        return GraphQLType::boolean();
    }
    
    public static function id(): GraphQLType
    {
        return GraphQLType::id();
    }
}