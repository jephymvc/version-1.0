<?php
namespace App\Core;

class JephyORM
{
    protected $modelClass;
    
    public function __construct($modelClass)
    {
        $this->modelClass = $modelClass;
    }
    
    public function create(array $params)
    {
        $data = $params['data'] ?? $params;
        return $this->modelClass::create($data);
    }
    
    public function findFirst(array $params = [])
    {
        return $this->modelClass::findFirst($params);
    }
    
    public function findMany(array $params = [])
    {
        return $this->modelClass::findMany($params);
    }
    
    public function update(array $params)
    {
        $data = $params['data'] ?? [];
        $where = $params['where'] ?? [];
        
        // First find the entity
        $entity = $this->modelClass::findFirst(['where' => $where]);
        
        if (!$entity) {
            return false;
        }
        
        // Update attributes
        foreach ($data as $key => $value) {
            $entity->$key = $value;
        }
        
        // Save changes
        return $entity->save();
    }
    
    public function delete(array $params)
    {
        $where = $params['where'] ?? [];
        
        // For delete, we can support direct deletion without fetching first
        if (method_exists($this->modelClass, 'deleteWhere')) {
            return $this->modelClass::deleteWhere($where);
        }
        
        // Fallback: find first then delete
        $entity = $this->modelClass::findFirst(['where' => $where]);
        
        if (!$entity) {
            return false;
        }
        
        return $entity->delete();
    }
    
    public function all(array $params = [])
    {
        return $this->findMany($params);
    }
    
    public function count(array $params = [])
    {
        return $this->modelClass::count($params);
    }
    
    public function exists(array $params = [])
    {
        return $this->modelClass::exists($params);
    }
    
    /**
     * Alias for findMany with pagination
     */
    public function paginate(int $page = 1, int $perPage = 10, array $params = [])
    {
        $params['skip'] = ($page - 1) * $perPage;
        $params['take'] = $perPage;
        
        $data = $this->findMany($params);
        
        // Remove pagination params for count
        $countParams = $params;
        unset($countParams['skip'], $countParams['take'], $countParams['orderBy']);
        
        $total = $this->count($countParams);
        $totalPages = ceil($total / $perPage);
        
        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'pages'     => $totalPages,
            'has_next'  => $page < $totalPages,
            'has_prev'  => $page > 1,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null
        ];
    }
}


#	// Example 1: Simple LIKE with wildcard
#	$users = $userORM->findMany([
#	    'where' => [
#	        'email' => '%@gmail.com'  // Automatically converted to LIKE '%@gmail.com'
#	    ],
#	    'take' => 10
#	]);
#	
#	// Example 2: Prisma-style contains
#	$users = $userORM->findMany([
#	    'where' => [
#	        'name' => ['contains' => 'john']  // Converted to LIKE '%john%'
#	    ]
#	]);
#	
#	// Example 3: Prisma-style startsWith
#	$products = $productORM->findMany([
#	    'where' => [
#	        'sku' => ['startsWith' => 'PROD-']  // Converted to LIKE 'PROD-%'
#	    ]
#	]);
#	
#	// Example 4: Prisma-style endsWith
#	$files = $fileORM->findMany([
#	    'where' => [
#	        'filename' => ['endsWith' => '.pdf']  // Converted to LIKE '%.pdf'
#	    ]
#	]);
#	
#	// Example 5: Explicit LIKE operator
#	$posts = $postORM->findMany([
#	    'where' => [
#	        'title' => ['like' => '%php%tutorial%']  // Explicit LIKE with pattern
#	    ]
#	]);
#	
#	// Example 6: NOT LIKE
#	$users = $userORM->findMany([
#	    'where' => [
#	        'email' => ['notLike' => '%@spam.com']  // NOT LIKE '%@spam.com'
#	    ]
#	]);
#	
#	// Example 7: Multiple LIKE conditions
#	$searchResults = $postORM->findMany([
#	    'where' => [
#	        'title' => ['contains' => 'PHP'],
#	        'content' => ['contains' => 'tutorial'],
#	        'status' => 'published'
#	    ],
#	    'orderBy' => ['created_at' => 'DESC'],
#	    'take' => 20
#	]);
#	
#	// Example 8: Complex search with LIKE and other operators
#	$products = $productORM->findMany([
#	    'where' => [
#	        'name' => ['contains' => 'apple'],  // LIKE '%apple%'
#	        'price' => ['operator' => '>', 'value' => 100],  // price > 100
#	        'category' => ['operator' => 'IN', 'value' => ['electronics', 'computers']],  // IN
#	        'stock' => ['operator' => '>', 'value' => 0]  // stock > 0
#	    ],
#	    'whereNot' => [
#	        'status' => 'discontinued'
#	    ],
#	    'orderBy' => [
#	        'price' => 'ASC',
#	        'name' => 'ASC'
#	    ],
#	    'take' => 25,
#	    'skip' => 0
#	]);
#	
#	// Example 9: Count with LIKE
#	$johnCount = $userORM->count([
#	    'where' => [
#	        'name' => ['contains' => 'john']
#	    ]
#	]);
#	
#	// Example 10: Exists with LIKE
#	$hasGmailUsers = $userORM->exists([
#	    'where' => [
#	        'email' => ['endsWith' => '@gmail.com']
#	    ]
#	]);
#	
#	// Example 11: Delete with LIKE (using deleteWhere)
#	$deleted = $userORM->delete([
#	    'where' => [
#	        'email' => ['like' => '%@temp.com']
#	    ]
#	]);