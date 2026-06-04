<?php
namespace App\Core\GraphQL;

use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\FormattedError;
use GraphQL\Error\Error;
use App\Core\GraphQL\Config\SmartyConfigLoader;
use App\Core\GraphQL\TypeRegistry;
use App\Core\GraphQL\Type;
use App\Core\Config;

class GraphQL
{
    private static ?self $instance = null;
    private array $queries = [];
    private array $mutations = [];
    private array $types = [];
    private bool $debug = false;
    private $config;
    private string $projectRoot;
    private ?Shield $shield = null;
    private ?Schema $cachedSchema = null;
    private array $context = [];
    private array $fieldResolvers = [];
    private TypeRegistry $typeRegistry;
    
    private function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        #	$configPath = $this->projectRoot . '/config.conf';
        #	
        #	if (!file_exists($configPath)) {
        #	    throw new \RuntimeException("Config file not found: {$configPath}");
        #	}
        
        #	$this->config = SmartyConfigLoader::getInstance($configPath);
		
        $this->config = Config::getInstance();
        $this->debug = filter_var(
            $this->config->get('graphql.debug', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        
        $this->typeRegistry = TypeRegistry::getInstance();
        
        // Register built-in types with Type class
        $this->registerBuiltInTypes();
    }
    
    private function registerBuiltInTypes(): void
    {
        // Register common scalar types
        $this->typeRegistry->set('String', Type::string());
        $this->typeRegistry->set('Int', Type::int());
        $this->typeRegistry->set('Float', Type::float());
        $this->typeRegistry->set('Boolean', Type::boolean());
        $this->typeRegistry->set('ID', Type::id());
    }
    
    public static function getInstance(?string $projectRoot = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($projectRoot);
        }
        return self::$instance;
    }
    
    /**
     * Register a type definition
     */
    public function registerType(string $name, callable $definition): self
    {
        $this->typeRegistry->register($name, $definition);
        return $this;
    }
    
    /**
     * Register multiple types
     */
    public function registerTypes(array $types): self
    {
        foreach ($types as $name => $definition) {
            $this->registerType($name, $definition);
        }
        return $this;
    }
    
    /**
     * Register queries with string-based type references
     */
    public function registerQueries(array $queries): self
    {
        $this->queries = array_merge($this->queries, $queries);
        $this->invalidateSchema();
        return $this;
    }
    
    /**
     * Register mutations with string-based type references
     */
    public function registerMutations(array $mutations): self
    {
        $this->mutations = array_merge($this->mutations, $mutations);
        $this->invalidateSchema();
        return $this;
    }
    
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
    
    public function setShield(?Shield $shield): self
    {
        $this->shield = $shield;
        $this->fieldResolvers = [];
        $this->invalidateSchema();
        return $this;
    }
    
    public function buildSchema(): Schema
    {
        if ($this->cachedSchema !== null) {
            return $this->cachedSchema;
        }
        
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => function() {
                return $this->buildFields($this->queries, 'Query');
            }
        ]);
        
        $mutationType = !empty($this->mutations) 
            ? new ObjectType([
                'name' => 'Mutation',
                'fields' => function() {
                    return $this->buildFields($this->mutations, 'Mutation');
                }
            ]) 
            : null;
        
        $schemaConfig = [
            'query' => $queryType,
            'typeLoader' => function(string $name) {
                return $this->typeRegistry->get($name);
            }
        ];
        
        if ($mutationType) {
            $schemaConfig['mutation'] = $mutationType;
        }
        
        $this->cachedSchema = new Schema($schemaConfig);
        return $this->cachedSchema;
    }
    
    private function buildFields(array $fieldConfigs, string $parentType): array
    {
        if (empty($fieldConfigs)) {
            return [];
        }
        
        $fields = [];
        
        foreach ($fieldConfigs as $fieldName => $config) {
            // Resolve type from string if needed
            $resolvedConfig = $config;
            if (isset($config['type']) && is_string($config['type'])) {
                $resolvedConfig['type'] = $this->resolveTypeReference($config['type']);
            }
            
            // Resolve args types
            if (isset($config['args']) && is_array($config['args'])) {
                foreach ($config['args'] as $argName => $argConfig) {
                    if (isset($argConfig['type']) && is_string($argConfig['type'])) {
                        $resolvedConfig['args'][$argName]['type'] = $this->resolveTypeReference($argConfig['type']);
                    }
                }
            }
            
            $fields[$fieldName] = $resolvedConfig;
            $fieldPath = "{$parentType}.{$fieldName}";
            
            if ($this->shield !== null && isset($config['resolve'])) {
                $fields[$fieldName]['resolve'] = $this->getProtectedResolver(
                    $fieldPath,
                    $config['resolve']
                );
            }
        }
        
        return $fields;
    }
    
    /**
     * Resolve a type reference (supports nullable, non-null, and list types)
     */
    private function resolveTypeReference(string $typeReference)
    {
        // Handle non-null types: Type!
        if (str_ends_with($typeReference, '!')) {
            $innerType = substr($typeReference, 0, -1);
            return GraphQLType::nonNull($this->resolveTypeReference($innerType));
        }
        
        // Handle list types: [Type]
        if (preg_match('/^\[(.*)\]$/', $typeReference, $matches)) {
            $innerType = $matches[1];
            return GraphQLType::listOf($this->resolveTypeReference($innerType));
        }
        
        // Handle non-null list: [Type]!
        if (preg_match('/^\[(.*)\]!$/', $typeReference, $matches)) {
            $innerType = $matches[1];
            return GraphQLType::nonNull(
                GraphQLType::listOf($this->resolveTypeReference($innerType))
            );
        }
        
        // Regular type name
        return $this->typeRegistry->get($typeReference);
    }
    
    private function getProtectedResolver(string $fieldPath, callable $originalResolver): callable
    {
        $cacheKey = $fieldPath;
        
        if (!isset($this->fieldResolvers[$cacheKey])) {
            $shield = $this->shield;
            
            $this->fieldResolvers[$cacheKey] = function($root, $args, $context, $info) use ($fieldPath, $originalResolver, $shield) {
                $result = $shield->protect($fieldPath, $root, $args, $context, $info);
                
                if ($result instanceof ShieldError) {
                    throw new Error($result->getMessage());
                }
                
                if ($result === false) {
                    $errorMessage = $shield->getErrorMessage($fieldPath) ?? "Access denied";
                    throw new Error($errorMessage);
                }
                
                $enhancedContext = $context ?? [];
                if (is_array($result) && $result !== true) {
                    $enhancedContext = array_merge($enhancedContext, $result);
                }
                
                return $originalResolver($root, $args, $enhancedContext, $info);
            };
        }
        
        return $this->fieldResolvers[$cacheKey];
    }
    
    public function execute(string $query, array $variables = [], ?array $context = null): array
    {
        $startTime = microtime(true);
        
        try {
            $schema = $this->buildSchema();
            $executionContext = array_merge($this->context, $context ?? []);
            
            $result = GraphQLBase::executeQuery(
                $schema,
                $query,
                null,
                $executionContext,
                $variables
            );
            
            $output = $this->debug 
                ? $result->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE)
                : $result->toArray();
            
            return $output;
            
        } catch (\Throwable $e) {
            error_log(sprintf('[GraphQL] Error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
            
            if (!$this->debug) {
                return ['errors' => [['message' => 'Internal server error']]];
            }
            
            return ['errors' => [FormattedError::createFromException($e)]];
        }
    }
    
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }
    
    private function invalidateSchema(): void
    {
        $this->cachedSchema = null;
        $this->fieldResolvers = [];
    }
    
    public static function resetInstance(): void
    {
        self::$instance = null;
        TypeRegistry::getInstance()->clear();
    }
}