<?php
namespace App\Core;

use ReflectionClass;

abstract class Entity
{
    
	protected $table;
    protected $primaryKey 	= 'id';
    protected $attributes 	= [];
    protected $original 	= [];
    protected $timestamps 	= true;
    protected $db;
    protected $hooks;
    
    // Relationship properties
    protected $relations 			= [];
    protected $with 				= [];
    protected $loadedRelations 		= [];
    protected static $eagerLoads 	= [];
    
    public function __construct($attributes = [])
    {
        $this->db = Database::getInstance();
        $this->hooks = Framework::getHooks();
        
        if (empty($this->table)) {
            $className = (new ReflectionClass($this))->getShortName();
            $this->table = strtolower($className) . 's';
        }
        
        $this->fill($attributes);
    }
    
    public function fill($attributes)
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
            $this->attributes[$key] = $value;
        }
        $this->original = $this->attributes;
    }
    
    public function __get($key)
    {
        // Check if it's a relationship that needs to be loaded
        if (isset($this->relations[$key]) && !isset($this->loadedRelations[$key])) {
            $this->loadRelation($key);
        }
        
        // Check if it's already loaded
        if (isset($this->loadedRelations[$key])) {
            return $this->loadedRelations[$key];
        }
        
        // Check if it's a property
        if (property_exists($this, $key)) {
            return $this->$key;
        }
        
        // Check if it's an attribute
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        
        // Try to load relation on the fly if method exists
        if (method_exists($this, $key)) {
            $relation = $this->$key();
            $this->loadedRelations[$key] = $relation;
            return $relation;
        }
        
        // Check for dynamic properties like *_count from withCount()
        if (strpos($key, '_count') !== false) {
            $relationName = str_replace('_count', '', $key);
            if (isset($this->loadedRelations[$key])) {
                return $this->loadedRelations[$key];
            }
        }
        
        return null;
    }
    
    public function __set($key, $value)
    {
        if (property_exists($this, $key)) {
            $this->$key = $value;
        }
        $this->attributes[$key] = $value;
    }
    
    public function __call($method, $parameters)
    {
        // Handle dynamic relationship methods
        if (strpos($method, 'with') === 0) {
            $relation = lcfirst(substr($method, 4));
            return $this->with($relation);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist on " . get_class($this));
    }
    
    /**
     * One-to-One relationship
     */
    public function hasOne($relatedClass, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->primaryKey;
        
        return (new QueryBuilder($relatedClass::getTableStatic()))
            ->setEntity($relatedClass)
            ->where($foreignKey, $this->attributes[$localKey])
            ->first();
    }
    
    /**
     * One-to-Many relationship
     */
    public function hasMany($relatedClass, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->primaryKey;
        
        return (new QueryBuilder($relatedClass::getTableStatic()))
            ->setEntity($relatedClass)
            ->where($foreignKey, $this->attributes[$localKey])
            ->get();
    }
    
    /**
     * Inverse of One-to-One or One-to-Many (Belongs To)
     */
    public function belongsTo($relatedClass, $foreignKey = null, $ownerKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey($relatedClass);
        $ownerKey = $ownerKey ?: 'id';
        
        return (new QueryBuilder($relatedClass::getTableStatic()))
            ->setEntity($relatedClass)
            ->where($ownerKey, $this->attributes[$foreignKey])
            ->first();
    }
    
    /**
     * Many-to-Many relationship
     */
    public function belongsToMany($relatedClass, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null)
    {
        // Get table names
        $relatedTable = $relatedClass::getTableStatic();
        
        // Determine pivot table name if not provided
        if (!$pivotTable) {
            $tables = [$this->table, $relatedTable];
            sort($tables);
            $pivotTable = implode('_', $tables);
        }
        
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: (new $relatedClass())->getForeignKey();
        
        // Build query to get related records through pivot table
        $query = (new QueryBuilder($relatedTable))
            ->select("{$relatedTable}.*")
            ->join($pivotTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.{$relatedClass::getPrimaryKeyStatic()}")
            ->where("{$pivotTable}.{$foreignPivotKey}", $this->attributes[$this->primaryKey])
            ->setEntity($relatedClass);
        
        return $query->get();
    }
    
    /**
     * Define a relationship for eager loading
     */
    protected function defineRelation($name, $type, $relatedClass, $options = [])
    {
        $this->relations[$name] = [
            'type' => $type,
            'related' => $relatedClass,
            'options' => $options
        ];
        return $this;
    }
    
    /**
     * Load a specific relationship
     */
    protected function loadRelation($name)
    {
        if (!isset($this->relations[$name])) {
            return null;
        }
        
        $relation = $this->relations[$name];
        
        switch ($relation['type']) {
            case 'hasOne':
                $result = $this->hasOne(
                    $relation['related'],
                    $relation['options']['foreignKey'] ?? null,
                    $relation['options']['localKey'] ?? null
                );
                break;
                
            case 'hasMany':
                $result = $this->hasMany(
                    $relation['related'],
                    $relation['options']['foreignKey'] ?? null,
                    $relation['options']['localKey'] ?? null
                );
                break;
                
            case 'belongsTo':
                $result = $this->belongsTo(
                    $relation['related'],
                    $relation['options']['foreignKey'] ?? null,
                    $relation['options']['ownerKey'] ?? null
                );
                break;
                
            case 'belongsToMany':
                $result = $this->belongsToMany(
                    $relation['related'],
                    $relation['options']['pivotTable'] ?? null,
                    $relation['options']['foreignPivotKey'] ?? null,
                    $relation['options']['relatedPivotKey'] ?? null
                );
                break;
                
            default:
                $result = null;
        }
        
        $this->loadedRelations[$name] = $result;
        return $result;
    }
    
    /**
     * Eager load relationships for this instance
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        
        foreach ($relations as $relation) {
            if (!in_array($relation, $this->with)) {
                $this->with[] = $relation;
                // Load immediately
                $this->$relation;
            }
        }
        
        return $this;
    }
    
    /**
     * Set a relationship on the entity
     */
    public function setRelation($relation, $value)
    {
        $this->loadedRelations[$relation] = $value;
        return $this;
    }
    
    /**
     * Check if a relationship is loaded
     */
    public function relationLoaded($relation)
    {
        return isset($this->loadedRelations[$relation]);
    }
    
    /**
     * Get loaded relationships
     */
    public function getLoadedRelations()
    {
        return $this->loadedRelations;
    }
    
    /**
     * Get foreign key for this entity
     */
    protected function getForeignKey($relatedClass = null)
    {
        if ($relatedClass) {
            $relatedName = strtolower((new ReflectionClass($relatedClass))->getShortName());
            return $relatedName . '_id';
        }
        return strtolower((new ReflectionClass($this))->getShortName()) . '_id';
    }
    
    /**
     * Get table name
     */
    public function getTable()
    {
        return $this->table;
    }
    
    /**
     * Get table name statically
     */
    public static function getTableStatic()
    {
        $instance = new static();
        return $instance->getTable();
    }
    
    /**
     * Get primary key
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }
    
    /**
     * Get primary key statically
     */
    public static function getPrimaryKeyStatic()
    {
        $instance = new static();
        return $instance->getPrimaryKey();
    }
    
    /**
     * Get the value of the primary key
     */
    public function getKey()
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }
    
    /**
     * Get attribute by key
     */
    public function getAttribute($key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }
    
    /**
     * Set attribute
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }
    
    /**
     * Get all attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }
    
    /**
     * Save the entity
     */
    public function save()
    {
        // Execute before save hook
        $this->hooks->exec('beforeSave', [
            'entity' => $this,
            'attributes' => $this->attributes
        ]);
        
        if ($this->isNew()) {
            return $this->insert();
        } else {
            return $this->update();
        }
    }
    
    public function insert()
    {
        if ($this->timestamps) {
            $this->attributes['created_at'] = date('Y-m-d H:i:s');
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // Execute before insert hook
        $result = $this->hooks->exec('beforeInsert', [
            'entity' => $this,
            'attributes' => $this->attributes
        ]);
        
        if ($result === false) {
            return false;
        }
        
        $columns = implode(', ', array_keys($this->attributes));
        $placeholders = ':' . implode(', :', array_keys($this->attributes));		        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";        
        $stmt = $this->db->query($sql, $this->attributes);
        
        if ($stmt) {
            $this->attributes[$this->primaryKey] = $this->db->lastInsertId();
            $this->original = $this->attributes;
            
            // Execute after insert hook
            $this->hooks->exec('afterInsert', [
                'entity' => $this,
                'id' => $this->attributes[$this->primaryKey]
            ]);
            
            return true;
        }
        
        return false;
    }
    
    public function update()
    {
        if ($this->timestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // Execute before update hook
        $result = $this->hooks->exec('beforeUpdate', [
            'entity' => $this,
            'attributes' => $this->attributes,
            'original' => $this->original
        ]);
        
        if ($result === false) {
            return false;
        }
        
        $updates = [];
        foreach ($this->attributes as $key => $value) {
            if ($key !== $this->primaryKey) {
                $updates[] = "{$key} = :{$key}";
            }
        }
        
        $setClause = implode(', ', $updates);
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = :id";
        
        $params = $this->attributes;
        $params['id'] = $this->original[$this->primaryKey];
        
        $stmt = $this->db->query($sql, $params);
        
        if ($stmt) {
            $this->original = $this->attributes;
            
            // Execute after update hook
            $this->hooks->exec('afterUpdate', [
                'entity' => $this,
                'id' => $this->attributes[$this->primaryKey]
            ]);
            
            return true;
        }
        
        return false;
    }
    
    public function delete()
    {
        if ($this->isNew()) {
            return false;
        }
        
        // Execute before delete hook
        $result = $this->hooks->exec('beforeDelete', [
            'entity' => $this,
            'id' => $this->attributes[$this->primaryKey]
        ]);
        
        if ($result === false) {
            return false;
        }
        
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->query($sql, ['id' => $this->attributes[$this->primaryKey]]);
        
        if ($stmt) {
            // Execute after delete hook
            $this->hooks->exec('afterDelete', [
                'entity' => $this,
                'id' => $this->attributes[$this->primaryKey]
            ]);
            
            return true;
        }
        
        return false;
    }
    
    public function isNew()
    {
        return empty($this->original[$this->primaryKey]);
    }
    
    public function toArray($withRelations = false)
    {
        $data = $this->attributes;
        
        if ($withRelations) {
            foreach ($this->loadedRelations as $name => $relation) {
                if ($relation instanceof Entity) {
                    $data[$name] = $relation->toArray();
                } elseif (is_array($relation)) {
                    $data[$name] = array_map(function($item) {
                        return $item instanceof Entity ? $item->toArray() : $item;
                    }, $relation);
                } elseif ($relation !== null) {
                    $data[$name] = $relation;
                }
            }
        }
        
        return $data;
    }
    
    public function toJson($withRelations = false, $options = JSON_PRETTY_PRINT)
    {
        return json_encode($this->toArray($withRelations), $options);
    }
    
    public static function find($id, $with = [])
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = :id LIMIT 1";
        $stmt = $instance->db->query($sql, ['id' => $id]);
        $result = $stmt->fetch();
        
        if ($result) {
            $entity = new static($result);
            if (!empty($with)) {
                $entity->with($with);
            }
            return $entity;
        }
        
        return null;
    }
    
    public static function findBy($column, $value, $with = [])
    {
        $instance = new static();
        $sql = "SELECT * FROM {$instance->table} WHERE {$column} = :value LIMIT 1";
        $stmt = $instance->db->query($sql, ['value' => $value]);
        $result = $stmt->fetch();
        
        if ($result) {
            $entity = new static($result);
            if (!empty($with)) {
                $entity->with($with);
            }
            return $entity;
        }
        
        return null;
    }
    
    public static function where($column, $operator, $value = null)
    {
        $instance = new static();
        
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $query = (new QueryBuilder($instance->table))
            ->where($column, $operator, $value)
            ->setEntity(get_called_class());
            
        // Apply any global eager loads
        $calledClass = get_called_class();
        if (isset(self::$eagerLoads[$calledClass]) && !empty(self::$eagerLoads[$calledClass])) {
            $query->with(self::$eagerLoads[$calledClass]);
        }
        
        return $query;
    }
    
    public static function all($with = [])
    {
        $instance = new static();
        $query = (new QueryBuilder($instance->table))
            ->setEntity(get_called_class());
            
        if (!empty($with)) {
            $query->with($with);
        }
        
        return $query->get();
    }
    
    /**
     * Get first record
     */
    public static function first($with = [])
    {
        $instance = new static();
        $query = (new QueryBuilder($instance->table))
            ->setEntity(get_called_class())
            ->limit(1);
            
        if (!empty($with)) {
            $query->with($with);
        }
        
        $results = $query->get();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find or create
     */
    public static function findOrCreate($attributes, $values = [])
    {
        $instance = new static();
        
        // Try to find
        $query = (new QueryBuilder($instance->table))
            ->setEntity(get_called_class());
            
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }
        
        $entity = $query->first();
        
        // If not found, create
        if (!$entity) {
            $entity = new static(array_merge($attributes, $values));
            $entity->save();
        }
        
        return $entity;
    }
    
    /**
     * Find or fail
     */
    public static function findOrFail($id, $with = [])
    {
        $entity = static::find($id, $with);
        
        if (!$entity) {
            throw new \Exception("Entity with ID {$id} not found");
        }
        
        return $entity;
    }
    
    /**
     * Attach related entity (for many-to-many)
     */
    public function attach($related, $pivotData = [])
    {
        if (!$related instanceof Entity) {
            throw new \InvalidArgumentException('Related must be an Entity instance');
        }
        
        $pivotTable = $this->getPivotTableName(get_class($related));
        
        $data = [
            $this->getForeignKey() => $this->getKey(),
            $related->getForeignKey() => $related->getKey()
        ];
        
        if (!empty($pivotData)) {
            $data = array_merge($data, $pivotData);
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$pivotTable} ({$columns}) VALUES ({$placeholders})";
        
        return $this->db->query($sql, $data);
    }
    
    /**
     * Detach related entity (for many-to-many)
     */
    public function detach($related)
    {
        if (!$related instanceof Entity) {
            throw new \InvalidArgumentException('Related must be an Entity instance');
        }
        
        $pivotTable = $this->getPivotTableName(get_class($related));
        
        $sql = "DELETE FROM {$pivotTable} WHERE {$this->getForeignKey()} = :entity_id 
                AND {$related->getForeignKey()} = :related_id";
        
        return $this->db->query($sql, [
            'entity_id' => $this->getKey(),
            'related_id' => $related->getKey()
        ]);
    }
    
    /**
     * Sync many-to-many relationships
     */
    public function sync($relationName, $ids, $detaching = true)
    {
        if (!isset($this->relations[$relationName]) || $this->relations[$relationName]['type'] !== 'belongsToMany') {
            throw new \InvalidArgumentException("Relation {$relationName} must be a belongsToMany relationship");
        }
        
        $relation = $this->relations[$relationName];
        $relatedClass = $relation['related'];
        
        // Get current attached IDs
        $current = $this->$relationName;
        $currentIds = $current ? array_map(function($item) {
            return $item->getKey();
        }, $current) : [];
        
        // IDs to attach
        $attachIds = array_diff($ids, $currentIds);
        
        // IDs to detach
        $detachIds = $detaching ? array_diff($currentIds, $ids) : [];
        
        // Perform operations
        foreach ($detachIds as $id) {
            $related = $relatedClass::find($id);
            if ($related) {
                $this->detach($related);
            }
        }
        
        foreach ($attachIds as $id) {
            $related = $relatedClass::find($id);
            if ($related) {
                $this->attach($related);
            }
        }
        
        // Clear loaded relation
        unset($this->loadedRelations[$relationName]);
        
        return true;
    }
    
    /**
     * Update or create
     */
    public static function updateOrCreate($attributes, $values = [])
    {
        $instance = new static();
        
        // Try to find
        $query = (new QueryBuilder($instance->table))
            ->setEntity(get_called_class());
            
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }
        
        $entity = $query->first();
        
        // If found, update
        if ($entity) {
            foreach ($values as $key => $value) {
                $entity->$key = $value;
            }
            $entity->save();
        } else {
            // If not found, create
            $entity = new static(array_merge($attributes, $values));
            $entity->save();
        }
        
        return $entity;
    }
    
    /**
     * Get pivot table name
     */
    protected function getPivotTableName($relatedClass)
    {
        $relatedEntity = new $relatedClass();
        $tables = [$this->table, $relatedEntity->getTable()];
        sort($tables);
        return implode('_', $tables);
    }
    
    /**
     * Add global eager loads for this entity class
     */
    public static function addGlobalScope($relations)
    {
        $calledClass = get_called_class();
        if (!isset(self::$eagerLoads[$calledClass])) {
            self::$eagerLoads[$calledClass] = [];
        }
        
        if (is_string($relations)) {
            $relations = [$relations];
        }
        
        self::$eagerLoads[$calledClass] = array_merge(
            self::$eagerLoads[$calledClass],
            $relations
        );
    }
    
    /**
     * Get the entity's relationships
     */
    public function getRelations()
    {
        return $this->relations;
    }
    
    /**
     * Get the original attribute values
     */
    public function getOriginal()
    {
        return $this->original;
    }
    
    /**
     * Get a specific original attribute
     */
    public function getOriginalAttribute($key, $default = null)
    {
        return $this->original[$key] ?? $default;
    }
    
    /**
     * Check if an attribute has changed
     */
    public function isDirty($attributes = null)
    {
        if ($attributes === null) {
            return $this->attributes != $this->original;
        }
        
        if (is_string($attributes)) {
            $attributes = [$attributes];
        }
        
        foreach ($attributes as $attribute) {
            if ($this->attributes[$attribute] != $this->original[$attribute]) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an attribute hasn't changed
     */
    public function isClean($attributes = null)
    {
        return !$this->isDirty($attributes);
    }
    
    /**
     * Get the changed attributes
     */
    public function getDirty()
    {
        $dirty = [];
        
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value != $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }
        
        return $dirty;
    }
    
    /**
     * Get the entity's hooks
     */
    public function getHooks()
    {
        return $this->hooks;
    }
    
    /**
     * Get the entity's database connection
     */
    public function getDb()
    {
        return $this->db;
    }
}

