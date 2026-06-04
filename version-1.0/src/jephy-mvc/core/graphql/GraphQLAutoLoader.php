<?php
namespace App\Core\GraphQL;

use App\Core\Framework;

trait GraphQLAutoLoader
{
    protected $graphQLTypes = [];
    protected $graphQLQueries = [];
    protected $graphQLMutations = [];
    
    protected function autoDiscoverGraphQLTypes()
    {
        $appPath = Framework::getAppPathStatic();
        $graphQLPath = $appPath . '/app/GraphQL/';
        
        // Objects
        $objectsPath = $graphQLPath . 'Objects/';
        if (is_dir($objectsPath)) {
            foreach (glob($objectsPath . '*.php') as $file) {
                $className = 'App\\GraphQL\\Objects\\' . basename($file, '.php');
                if (class_exists($className)) {
                    $typeName = str_replace('Type', '', basename($file, '.php'));
                    TypeRegistry::register($typeName, $className);
                    $this->graphQLTypes[] = $typeName;
                }
            }
        }
        
        // Inputs
        $inputsPath = $graphQLPath . 'Inputs/';
        if (is_dir($inputsPath)) {
            foreach (glob($inputsPath . '*.php') as $file) {
                $className = 'App\\GraphQL\\Inputs\\' . basename($file, '.php');
                if (class_exists($className)) {
                    TypeRegistry::register(basename($file, '.php'), $className);
                    $this->graphQLTypes[] = basename($file, '.php');
                }
            }
        }
        
        // Enums
        $enumsPath = $graphQLPath . 'Enums/';
        if (is_dir($enumsPath)) {
            foreach (glob($enumsPath . '*.php') as $file) {
                $className = 'App\\GraphQL\\Enums\\' . basename($file, '.php');
                if (class_exists($className)) {
                    TypeRegistry::register(basename($file, '.php'), $className);
                    $this->graphQLTypes[] = basename($file, '.php');
                }
            }
        }
    }
    
    protected function autoDiscoverGraphQLQueries(GraphQL $graphQL)
    {
        $appPath = Framework::getAppPathStatic();
        $queriesPath = $appPath . '/app/GraphQL/Queries/';
        
        if (!is_dir($queriesPath)) {
            return;
        }
        
        foreach (glob($queriesPath . '*.php') as $file) {
            $className = 'App\\GraphQL\\Queries\\' . basename($file, '.php');
            if (class_exists($className)) {
                $queryInstance = new $className();
                if (method_exists($queryInstance, 'queries')) {
                    $graphQL->registerQueries($queryInstance->queries());
                    $this->graphQLQueries[] = basename($file, '.php');
                }
            }
        }
    }
    
    protected function autoDiscoverGraphQLMutations(GraphQL $graphQL)
    {
        $appPath = Framework::getAppPathStatic();
        $mutationsPath = $appPath . '/app/GraphQL/Mutations/';
        
        if (!is_dir($mutationsPath)) {
            return;
        }
        
        foreach (glob($mutationsPath . '*.php') as $file) {
            $className = 'App\\GraphQL\\Mutations\\' . basename($file, '.php');
            if (class_exists($className)) {
                $mutationInstance = new $className();
                if (method_exists($mutationInstance, 'mutations')) {
                    $graphQL->registerMutations($mutationInstance->mutations());
                    $this->graphQLMutations[] = basename($file, '.php');
                }
            }
        }
    }
    
    public function getDiscoveredGraphQLComponents()
    {
        return [
            'types' => $this->graphQLTypes,
            'queries' => $this->graphQLQueries,
            'mutations' => $this->graphQLMutations
        ];
    }
}