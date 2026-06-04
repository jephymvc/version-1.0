<?php
namespace App\Core;

class Model
{
    
	protected static $table;
    protected static $db;
    protected $attributes 	= [];
    protected $original 	= [];
	
	// Relationship cache to avoid multiple queries
    protected $relations 			= [];    
    // Define relationships in child classes
    protected $with 				= []; // Eager loading
    protected $relationDefinitions 	= [];
    
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
    }
    
    /**
     * Get database instance
     */
    protected static function db()
    {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }
	
	  
    // ==================== RELATIONSHIP METHODS ====================
    
    /**
     * Define One-to-One relationship
     * 
     * @param string $relatedClass The related entity class
     * @param string $foreignKey Foreign key on related table (default: parent_id)
     * @param string $localKey Local key on this table (default: id)
     * @return mixed Related entity or null
     */
    public function hasOne($relatedClass, $foreignKey = null, $localKey = 'id')
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localValue = $this->attributes[$localKey] ?? null;
        
        if ($localValue === null) {
            return null;
        }
        
        $cacheKey = "hasOne:{$relatedClass}:{$foreignKey}:{$localValue}";
        
        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey];
        }
        
        $result = $relatedClass::findFirst([
            'where' => [$foreignKey => $localValue]
        ]);
        
        $this->relations[$cacheKey] = $result;
        return $result;
    }
    
    /**
     * Define One-to-Many relationship
     * 
     * @param string $relatedClass The related entity class
     * @param string $foreignKey Foreign key on related table (default: parent_id)
     * @param string $localKey Local key on this table (default: id)
     * @return array Collection of related entities
     */
    public function hasMany($relatedClass, $foreignKey = null, $localKey = 'id')
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localValue = $this->attributes[$localKey] ?? null;
        
        if ($localValue === null) {
            return [];
        }
        
        $cacheKey = "hasMany:{$relatedClass}:{$foreignKey}:{$localValue}";
        
        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey];
        }
        
        $results = $relatedClass::findMany([
            'where' => [$foreignKey => $localValue],
            'orderBy' => 'id ASC'
        ]);
        
        $this->relations[$cacheKey] = $results;
        return $results;
    }
    
    /**
     * Define Belongs-To relationship (inverse of hasOne/hasMany)
     * 
     * @param string $relatedClass The parent entity class
     * @param string $foreignKey Foreign key on this table (default: parent_id)
     * @param string $ownerKey Primary key on related table (default: id)
     * @return mixed Related entity or null
     */
    public function belongsTo($relatedClass, $foreignKey = null, $ownerKey = 'id')
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey(true);
        $foreignValue = $this->attributes[$foreignKey] ?? null;
        
        if ($foreignValue === null) {
            return null;
        }
        
        $cacheKey = "belongsTo:{$relatedClass}:{$foreignKey}:{$foreignValue}";
        
        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey];
        }
        
        $result = $relatedClass::findFirst([
            'where' => [$ownerKey => $foreignValue]
        ]);
        
        $this->relations[$cacheKey] = $result;
        return $result;
    }
    
    /**
     * Define Belongs-To-Many relationship
     * 
     * @param string $relatedClass The related entity class
     * @param string $pivotTable Pivot table name
     * @param string $foreignPivotKey Foreign key on pivot table for this model
     * @param string $relatedPivotKey Foreign key on pivot table for related model
     * @param string $localKey Local key on this table (default: id)
     * @return array Collection of related entities
     */
    public function belongsToMany($relatedClass, $pivotTable, $foreignPivotKey = null, $relatedPivotKey = null, $localKey = 'id')
    {
        $foreignPivotKey = $foreignPivotKey ?: $this->getTableName() . '_id';
        $relatedPivotKey = $relatedPivotKey ?: (new $relatedClass())->getTableName() . '_id';
        $localValue = $this->attributes[$localKey] ?? null;
        
        if ($localValue === null) {
            return [];
        }
        
        $cacheKey = "belongsToMany:{$relatedClass}:{$pivotTable}:{$foreignPivotKey}:{$localValue}";
        
        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey];
        }
        
        // Get related IDs from pivot table
        $pivotRecords = self::db()->select($pivotTable, "{$relatedPivotKey}", [
            $foreignPivotKey => $localValue
        ]);
        
        if (empty($pivotRecords)) {
            return [];
        }
        
        $relatedIds = array_column($pivotRecords, $relatedPivotKey);
        
        // Fetch related entities
        $results = $relatedClass::findMany([
            'where' => ['id' => $relatedIds]
        ]);
        
        $this->relations[$cacheKey] = $results;
        return $results;
    }
    
    /**
     * Load relationships eagerly
     * 
     * @param array|string $relations Relations to load
     * @return $this
     */
    public function load($relations)
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $this->$relation();
            }
        }
        
        return $this;
    }
    
    /**
     * Get relationship value (magic method for property-style access)
     */
    public function __get($name)
    {
        // Check if it's a relationship
        if (method_exists($this, $name)) {
            return $this->$name();
        }
        
        // Check if it's a loaded relation
        foreach ($this->relations as $key => $value) {
            if (strpos($key, "{$name}:") === 0) {
                return $value;
            }
        }
        
        // Otherwise return attribute
        return $this->attributes[$name] ?? null;
    }
    
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    
    public function __isset($name)
    {
        return isset($this->attributes[$name]);
    }
    
    public function toArray()
    {
        return $this->attributes;
    }

    
    public function save()
    {
        if (isset($this->attributes['id'])) {
            // Update existing record
            $id = $this->attributes['id'];
            $updateData = $this->attributes;
            unset($updateData['id']);
            
            $result = self::db()->update(
                static::$table, 
                $updateData, 
                ['id' => $id]
            );
            
            if ($result) {
                $this->original = $this->attributes;
            }
            
            return $result > 0;
        } else {
            // Insert new record
            if (!isset($this->attributes['created_at'])) {
                $this->attributes['created_at'] = date('Y-m-d H:i:s');
            }
            
            $id = self::db()->insert(static::$table, $this->attributes);
            
            if ($id) {
                $this->attributes['id'] = $id;
                $this->original = $this->attributes;
                return true;
            }
        }
        
        return false;
    }
    
    public static function create(array $data)
    {
        $entity = new static($data);
        return $entity->save() ? $entity : null;
    }      
    
    /**
     * Process where conditions to handle LIKE operators
     */
    private static function processWhereConditions(array $where): array
    {
        $processed = [];
        
        foreach ($where as $field => $condition) {
            // Check if it's a LIKE condition (string contains %, _, or wildcards)
            if (is_string($condition) && (strpos($condition, '%') !== false || strpos($condition, '_') !== false)) {
                // Check for LIKE patterns
                if (strpos($condition, '%') !== false || strpos($condition, '_') !== false) {
                    $processed[$field] = ['operator' => 'LIKE', 'value' => $condition];
                } else {
                    $processed[$field] = $condition;
                }
            }
            // Check if it's an array with contains/startsWith/endsWith (Prisma-style)
            elseif (is_array($condition)) {
                if (isset($condition['contains'])) {
                    $processed[$field] = ['operator' => 'LIKE', 'value' => '%' . $condition['contains'] . '%'];
                } elseif (isset($condition['startsWith'])) {
                    $processed[$field] = ['operator' => 'LIKE', 'value' => $condition['startsWith'] . '%'];
                } elseif (isset($condition['endsWith'])) {
                    $processed[$field] = ['operator' => 'LIKE', 'value' => '%' . $condition['endsWith']];
                } elseif (isset($condition['like'])) {
                    $processed[$field] = ['operator' => 'LIKE', 'value' => $condition['like']];
                } elseif (isset($condition['notLike'])) {
                    $processed[$field] = ['operator' => 'NOT LIKE', 'value' => $condition['notLike']];
                } elseif (isset($condition['operator'])) {
                    // Already in operator format
                    $processed[$field] = $condition;
                } else {
                    // Simple array value
                    $processed[$field] = $condition;
                }
            } else {
                // Simple value
                $processed[$field] = $condition;
            }
        }
        
        return $processed;
    }
    
    /**
     * Bulk delete using WHERE conditions
     */
    public static function deleteWhere(array $where)
    {
        $where = self::processWhereConditions($where);
        $db = self::db();
        return $db->delete(static::$table, $where) > 0;
    }
	
	
    	

	  
    public static function find($id)
    {
        $db = self::db();
        $results = $db->select(static::$table, '*', ['id' => $id]);
        
        if (!empty($results)) {
            return new static($results[0]);
        }
        
        return null;
    }
	
	    
    public static function findMany(array $params = [])
    {
        // Extract parameters
        $where = $params['where'] ?? [];
        $whereNot = $params['whereNot'] ?? [];
        $orderBy = $params['orderBy'] ?? '';
        $limit = $params['take'] ?? $params['limit'] ?? null;
        $offset = $params['skip'] ?? $params['offset'] ?? null;
        
        // Process where conditions to handle LIKE operators
        $where = self::processWhereConditions($where);
        
        // Convert whereNot to where format with != operator
        if (!empty($whereNot)) {
            foreach ($whereNot as $field => $value) {
                $where[$field] = ['operator' => '!=', 'value' => $value];
            }
        }
        
        $db = self::db();
        $results = $db->select(static::$table, '*', $where, $orderBy, $limit, $offset);
        
        return array_map(function($item) {
            return new static($item);
        }, $results);
    }
    
    
    public static function findFirst(array $params = [])
    {
        $where = $params['where'] ?? [];
        $whereNot = $params['whereNot'] ?? [];
        
        // Process where conditions
        $where = self::processWhereConditions($where);
        
        // Convert whereNot to where format
        if (!empty($whereNot)) {
            foreach ($whereNot as $field => $value) {
                $where[$field] = ['operator' => '!=', 'value' => $value];
            }
        }
        
        $db = self::db();
        $results = $db->select(static::$table, '*', $where, '', 1);
        
        if (!empty($results)) {
            return new static($results[0]);
        }
        
        return null;
    }    
	
    
    public static function all($orderBy = '')
    {
        return self::findMany([], $orderBy);
    }
    	
	
	public static function where($conditions)
	{
		// Create callables that capture the protected members
		$dbAccessor = function() {
			return static::db();
		};
		
		$tableAccessor = function() {
			return static::$table;
		};
		
		return new class($conditions, static::class, $dbAccessor, $tableAccessor) {
			private $conditions;
			private $entityClass;
			private $dbAccessor;
			private $tableAccessor;
			private $orderBy = '';
			private $limit = null;
			private $offset = null;
			
			public function __construct($conditions, $entityClass, callable $dbAccessor, callable $tableAccessor)
			{
				$this->conditions = $conditions;
				$this->entityClass = $entityClass;
				$this->dbAccessor = $dbAccessor;
				$this->tableAccessor = $tableAccessor;
			}
			
			private function getDb()
			{
				return ($this->dbAccessor)();
			}
			
			private function getTable()
			{
				return ($this->tableAccessor)();
			}
			
			public function get($orderBy = '', $limit = null, $offset = null)
			{
				$db = $this->getDb();
				$table = $this->getTable();
				$results = $db->select(
					$table, 
					'*', 
					$this->conditions, 
					$orderBy, 
					$limit, 
					$offset
				);
				
				return array_map(function($item) use ($table) {
					return new $this->entityClass($item);
				}, $results);
			}
			
			public function first()
			{
				$db = $this->getDb();
				$table = $this->getTable();
				$results = $db->select(
					$table, 
					'*', 
					$this->conditions, 
					'', 
					1
				);
				
				if (!empty($results)) {
					return new $this->entityClass($results[0]);
				}
				
				return null;
			}
			
			public function count()
			{
				$db = $this->getDb();
				$table = $this->getTable();
				return $db->count($table, $this->conditions);
			}
			
			public function orderBy($orderBy)
			{
				$this->orderBy = $orderBy;
				return $this;
			}
			
			public function limit($limit)
			{
				$this->limit = $limit;
				return $this;
			}
			
			public function offset($offset)
			{
				$this->offset = $offset;
				return $this;
			}
		};
	}
	
    public static function count(array $params = [])
    {
        $where = $params['where'] ?? [];
        $whereNot = $params['whereNot'] ?? [];
        
        // Process where conditions
        $where = self::processWhereConditions($where);
        
        // Convert whereNot to where format
        if (!empty($whereNot)) {
            foreach ($whereNot as $field => $value) {
                $where[$field] = ['operator' => '!=', 'value' => $value];
            }
        }
        
        $db = self::db();
        return $db->count(static::$table, $where);
    }
	
	
	public static function exists(array $params = [])
    {
        return self::count($params) > 0;
    }
    
    
    public function update(array $data)
    {
        foreach ($data as $key => $value) {
            $this->attributes[$key] = $value;
        }
        $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        return $this->save();
    }

	
    public function delete()
    {
        if (isset($this->attributes['id'])) {
            $db = self::db();
            return $db->delete(static::$table, ['id' => $this->attributes['id']]) > 0;
        }
        
        return false;
    }

    
    public static function query()
    {
        return new class(static::class) {
            private $entityClass;
            private $conditions = [];
            private $orderBy = '';
            private $limit = null;
            private $offset = null;
            
            public function __construct($entityClass)
            {
                $this->entityClass = $entityClass;
            }
            
            public function where($conditions)
            {
                $this->conditions = array_merge($this->conditions, $conditions);
                return $this;
            }
            
            public function orderBy($orderBy)
            {
                $this->orderBy = $orderBy;
                return $this;
            }
            
            public function limit($limit)
            {
                $this->limit = $limit;
                return $this;
            }
            
            public function offset($offset)
            {
                $this->offset = $offset;
                return $this;
            }
            
            public function get()
            {
                return $this->entityClass::findMany($this->conditions, $this->orderBy, $this->limit, $this->offset);
            }
            
            public function first()
            {
                $results = $this->entityClass::findMany($this->conditions, $this->orderBy, 1, $this->offset);
                return !empty($results) ? $results[0] : null;
            }
            
            public function count()
            {
                return $this->entityClass::count($this->conditions);
            }
        };
    }
    
    public function refresh()
    {
        if (isset($this->attributes['id'])) {
            $fresh = static::find($this->attributes['id']);
            if ($fresh) {
                $this->attributes = $fresh->toArray();
                $this->original = $this->attributes;
                return true;
            }
        }
        return false;
    }
    
    public function isDirty()
    {
        return $this->attributes !== $this->original;
    }
    
    public function getDirty()
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }
}

