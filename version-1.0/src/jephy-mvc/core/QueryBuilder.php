<?php
namespace App\Core;
use App\Core\Config;
/**
 * Raw SQL expression wrapper
 */
class RawExpression
{
    private string $expression;
    
    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }
    
    public function getExpression(): string
    {
        return $this->expression;
    }
}

class QueryBuilder
{
    private $table;
    private $entityClass;
    private $db;
    private $config;
    private $wheres = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    private $joins = [];
    private $selects = ['*'];
    private $insertData = [];
    private $updateData = [];
    private $sets = []; // For method chaining UPDATE
    
    // New properties for relationships
    private $with = [];
    private $withCount = [];
    private $eagerLoaded = false;
    
    public function __construct($table)
    {
        $this->config 	= Config::getInstance();
		#	$this->table 	= $table;
        $this->table 	= $this->config->get( 'database.driver' ) != "pgsql" ? $table : "public.\"{$table}\"";
        $this->db 		= Database::getInstance();
		
    }
    
    // =============================================
    // SELECT & READ METHODS (EXISTING)
    // =============================================
    
    public function setEntity($entityClass)
    {
        $this->entityClass = $entityClass;
        return $this;
    }
    
    public function select($columns)
    {
        if (is_array($columns)) {
            $this->selects = $columns;
        } else {
            $this->selects = func_get_args();
        }
        return $this;
    }
    
    public function where($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'type' => 'AND'
        ];
        
        return $this;
    }
    
    public function orWhere($column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'type' => 'OR'
        ];
        
        return $this;
    }
    
    public function whereIn($column, array $values)
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'type' => 'AND'
        ];
        
        return $this;
    }
    
    public function whereNull($column)
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
            'type' => 'AND'
        ];
        
        return $this;
    }
    
    public function whereNotNull($column)
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null,
            'type' => 'AND'
        ];
        
        return $this;
    }
    
    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy[] = "{$column} {$direction}";
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
    
    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => $type
        ];
        
        return $this;
    }
    
    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    public function rightJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
    
    // =============================================
    // RELATIONSHIP METHODS (NEW)
    // =============================================
    
    /**
     * Eager load relationships
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        
        foreach ($relations as $relation) {
            if (is_array($relation)) {
                // Handle nested eager loading: ['posts.comments']
                foreach ($relation as $key => $value) {
                    if (is_numeric($key)) {
                        $this->with[] = $value;
                    } else {
                        // With constraints: ['posts' => function($query) { ... }]
                        $this->with[$key] = $value;
                    }
                }
            } else {
                $this->with[] = $relation;
            }
        }
        
        return $this;
    }
    
    /**
     * Eager load relationship counts
     */
    public function withCount($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }
        
        foreach ($relations as $relation) {
            $this->withCount[] = $relation;
        }
        
        return $this;
    }
    
    /**
     * Load a specific relationship
     */
    public function load($relation)
    {
        $results = $this->get();
        if (!empty($results)) {
            $this->eagerLoadRelations($results, [$relation]);
        }
        return $results;
    }
    
    // =============================================
    // GET METHODS WITH RELATIONSHIP SUPPORT
    // =============================================
    
    public function get()
    {
        $sql = $this->buildSelect();
        $params = $this->getWhereParams();
        
        $stmt = $this->db->query($sql, $params);
        $results = $stmt->fetchAll();
        
        if ($this->entityClass) {
            $entities = array_map(function($item) {
                return new $this->entityClass($item);
            }, $results);
            
            // Eager load relationships if specified
            if (!empty($this->with) || !empty($this->withCount)) {
                $this->eagerLoadRelations($entities);
            }
            
            return $entities;
        }
        
        return $results;
    }
    
    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        
        return !empty($results) ? $results[0] : null;
    }
    
    public function find($id)
    {
        return $this->where('id', $id)->first();
    }
    
    public function pluck($column, $key = null)
    {
        $this->select($key ? [$column, $key] : [$column]);
        $results = $this->get();
        
        if (empty($results)) {
            return [];
        }
        
        $plucked = [];
        foreach ($results as $item) {
            if ($key && is_object($item)) {
                $plucked[$item->$key] = $item->$column;
            } elseif ($key && is_array($item)) {
                $plucked[$item[$key]] = $item[$column];
            } elseif (is_object($item)) {
                $plucked[] = $item->$column;
            } else {
                $plucked[] = $item[$column];
            }
        }
        
        return $plucked;
    }
    
    public function count()
    {
        $this->selects = ['COUNT(*) as count'];
        $result = $this->first();
        return $result ? (is_object($result) ? $result->count : $result['count']) : 0;
    }
    
    public function sum($column)
    {
        $this->selects = ["SUM({$column}) as sum"];
        $result = $this->first();
        return $result ? (is_object($result) ? $result->sum : $result['sum']) : 0;
    }
    
    public function avg($column)
    {
        $this->selects = ["AVG({$column}) as avg"];
        $result = $this->first();
        return $result ? (is_object($result) ? $result->avg : $result['avg']) : 0;
    }
    
    public function max($column)
    {
        $this->selects = ["MAX({$column}) as max"];
        $result = $this->first();
        return $result ? (is_object($result) ? $result->max : $result['max']) : 0;
    }
    
    public function min($column)
    {
        $this->selects = ["MIN({$column}) as min"];
        $result = $this->first();
        return $result ? (is_object($result) ? $result->min : $result['min']) : 0;
    }
    
    // =============================================
    // EAGER LOADING IMPLEMENTATION
    // =============================================
    
    /**
     * Eager load relationships for entities
     */
    private function eagerLoadRelations(array &$entities)
    {
        if (empty($entities) || $this->eagerLoaded) {
            return;
        }
        
        $entity = $entities[0];
        
        // Load regular relationships
        foreach ($this->with as $key => $value) {
            if (is_numeric($key)) {
                $relation = $value;
                $constraints = null;
            } else {
                $relation = $key;
                $constraints = $value;
            }
            
            $this->eagerLoadRelation($entities, $relation, $constraints);
        }
        
        // Load relationship counts
        foreach ($this->withCount as $relation) {
            $this->eagerLoadCount($entities, $relation);
        }
        
        $this->eagerLoaded = true;
    }
    
    /**
     * Eager load a specific relationship
     */
    private function eagerLoadRelation(array &$entities, $relation, $constraints = null)
    {
        if (empty($entities)) {
            return;
        }
        
        $entity = $entities[0];
        
        // Check if relationship method exists
        if (!method_exists($entity, $relation)) {
            return;
        }
        
        // Get the first relationship to determine type
        $relationship = $entity->$relation();
        
        // Determine relationship type
        if ($relationship instanceof QueryBuilder) {
            // It's a hasOne or belongsTo relationship
            $this->eagerLoadOne($entities, $relation, $constraints);
        } elseif (is_array($relationship) || $relationship === null) {
            // It's a hasMany or belongsToMany relationship
            $this->eagerLoadMany($entities, $relation, $constraints);
        }
    }
    
    /**
     * Eager load one-to-one or belongs-to relationships
     */
    private function eagerLoadOne(array &$entities, $relation, $constraints = null)
    {
        $entity = $entities[0];
        
        // Get foreign key and local key from the relationship
        $method = new \ReflectionMethod(get_class($entity), $relation);
        $method->setAccessible(true);
        
        try {
            // Get the query builder from the relationship
            $relatedQuery = $method->invoke($entity);
            
            if (!$relatedQuery instanceof QueryBuilder) {
                return;
            }
            
            // Get the entity class from the query builder
            $relatedClass = $relatedQuery->getEntityClass();
            
            if (!$relatedClass) {
                return;
            }
            
            // Collect foreign keys
            $foreignKeys = [];
            foreach ($entities as $entity) {
                $foreignKey = $this->getForeignKeyFromEntity($entity, $relation);
                if ($foreignKey) {
                    $foreignKeys[$entity->getKey()] = $foreignKey;
                }
            }
            
            if (empty($foreignKeys)) {
                return;
            }
            
            // Query related entities
            $relatedEntities = (new $relatedClass())
                ->whereIn('id', array_values($foreignKeys))
                ->get();
            
            // Map related entities to parent entities
            $relatedMap = [];
            foreach ($relatedEntities as $relatedEntity) {
                $relatedMap[$relatedEntity->getKey()] = $relatedEntity;
            }
            
            // Attach related entities
            foreach ($entities as $entity) {
                $foreignKey = $foreignKeys[$entity->getKey()] ?? null;
                if ($foreignKey && isset($relatedMap[$foreignKey])) {
                    $entity->setRelation($relation, $relatedMap[$foreignKey]);
                }
            }
            
        } catch (\Exception $e) {
            // If reflection fails, use simple lazy loading
            foreach ($entities as $entity) {
                $entity->$relation;
            }
        }
    }
    
    /**
     * Eager load one-to-many or many-to-many relationships
     */
    private function eagerLoadMany(array &$entities, $relation, $constraints = null)
    {
        $entity = $entities[0];
        
        // For simplicity, we'll use lazy loading for many relationships
        // In a production system, you'd want to implement proper eager loading
        // with a single query to avoid N+1
        foreach ($entities as $entity) {
            $entity->$relation;
        }
    }
    
    /**
     * Eager load relationship counts
     */
    private function eagerLoadCount(array &$entities, $relation)
    {
        // This would require more complex implementation
        // For now, we'll set a placeholder
        foreach ($entities as $entity) {
            $entity->{$relation . '_count'} = 0;
        }
    }
    
    /**
     * Get foreign key from entity relationship
     */
    private function getForeignKeyFromEntity($entity, $relation)
    {
        // Try common foreign key patterns
        $patterns = [
            $relation . '_id',
            strtolower((new \ReflectionClass($entity))->getShortName()) . '_id'
        ];
        
        foreach ($patterns as $pattern) {
            if (isset($entity->$pattern)) {
                return $entity->$pattern;
            }
        }
        
        return null;
    }
    
    // =============================================
    // ENHANCED UPDATE METHODS (EXISTING)
    // =============================================
    
    public function set($column, $value)
    {
        $this->sets[$column] = $value;
        return $this;
    }
    
    public function setMultiple(array $data)
    {
        foreach ($data as $column => $value) {
            $this->sets[$column] = $value;
        }
        return $this;
    }
    
    public function increment($column, $amount = 1)
    {
        $this->sets[$column] = new RawExpression("{$column} + {$amount}");
        return $this;
    }
    
    public function decrement($column, $amount = 1)
    {
        $this->sets[$column] = new RawExpression("{$column} - {$amount}");
        return $this;
    }
    
    public function setRaw($column, $expression)
    {
        $this->sets[$column] = new RawExpression($expression);
        return $this;
    }
    
    public function update()
    {
        
		$data 	= !empty($this->sets) ? $this->sets : $this->updateData;        
        if (empty($data)) {
            throw new \RuntimeException('No data to update. Use set(), setMultiple(), or pass data array.');
        }        
        $sql 	= $this->buildUpdateWithSets($data);
        $params = array_merge($this->getUpdateParamsFromSets($data), $this->getWhereParams());
        
        $stmt 	= $this->db->query($sql, $params);
        return $stmt->rowCount();
		
    }
    
    public function updateArray(array $data)
    {
        $this->updateData = $data;
        $sql = $this->buildUpdate();
        $params = array_merge($this->getUpdateParams(), $this->getWhereParams());
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function updateAlt($data)
    {
        $sets = [];
        $params = $this->getWhereParams();
        
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = :set_{$key}";
            $params["set_{$key}"] = $value;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }
        
        return $this->db->query($sql, $params)->rowCount();
    }
    
    public function updateBatch(array $data, $keyColumn = 'id')
    {
        if (empty($data)) {
            return 0;
        }
        
        $sql = $this->buildBatchUpdate($data, $keyColumn);
        $params = $this->getBatchUpdateParams($data, $keyColumn);
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function updateJoin($table, $first, $operator, $second)
    {
        $data = !empty($this->sets) ? $this->sets : $this->updateData;
        
        if (empty($data)) {
            throw new \RuntimeException('No data to update. Use set() or setMultiple() first.');
        }
        
        $this->joins[] = [
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'type' => 'INNER'
        ];
        
        $sql = $this->buildUpdateWithJoin($data);
        $params = array_merge($this->getUpdateParamsFromSets($data), $this->getWhereParams());
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // =============================================
    // INSERT METHODS (EXISTING)
    // =============================================
    
    public function insert(array $data)
    {
        #	print_r( $data );
		$this->insertData 	= $data;
        $sql 				= $this->buildInsert();
        $params 			= $this->getInsertParams();
		#	print_r( $params );
        $stmt 				= $this->db->query($sql, $params);
		return $this->db->lastInsertId();
    }
    
    public function insertBatch(array $data)
    {
        if (empty($data)) {
            return 0;
        }
        
        $sql = $this->buildBatchInsert($data);
        $params = $this->getBatchInsertParams($data);
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // =============================================
    // DELETE & UPSERT METHODS (EXISTING)
    // =============================================
    
    public function delete()
    {
        $sql = $this->buildDelete();
        $params = $this->getWhereParams();
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function upsert(array $insertData, array $updateData = null)
    {
        $this->insertData = $insertData;
        $this->updateData = $updateData ?? $insertData;
        
        $sql = $this->buildUpsert();
        $params = array_merge(
            $this->getInsertParams(),
            $this->getUpdateParams()
        );
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // =============================================
    // BUILD METHODS (EXISTING)
    // =============================================
    
    private function buildSelect()
    {
        $select = implode(', ', $this->selects);
        $sql = "SELECT {$select} FROM {$this->table}";
        
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
        }
        
        if ($this->offset !== null) {
            $sql .= " OFFSET " . $this->offset;
        }
        
        return $sql;
    }
    
    private function buildWheres()
    {
        $clauses = [];
        
        foreach ($this->wheres as $index => $where) {
            $clause = $index === 0 ? '' : " {$where['type']} ";
            
            if ($where['operator'] === 'IN') {
                $placeholders = array_map(function($i) use ($index) {
                    return ":where_{$index}_{$i}";
                }, array_keys($where['value']));
                $clause .= "{$where['column']} IN (" . implode(', ', $placeholders) . ")";
            } elseif ($where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL') {
                $clause .= "{$where['column']} {$where['operator']}";
            } else {
                $clause .= "{$where['column']} {$where['operator']} :where_{$index}";
            }
            
            $clauses[] = $clause;
        }
        
        return implode('', $clauses);
    }
    
    private function buildInsert()
    {
        $columns 		= implode(', ', array_keys($this->insertData));
        $placeholders 	= implode(', ', array_map(function($key) {
            return ":insert_{$key}";
        }, array_keys($this->insertData)));
        
        return "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
    }
      
    private function buildInsertAlt()
    {
        $columns 		= implode(', ', array_keys($this->insertData));
        $placeholders 	= implode(', ', array_map(function($key) {
            return ":insert_{$key}";
        }, array_keys($this->insertData)));
        
        return "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
    }
    
    private function buildBatchInsert(array $data)
    {
        $columns = implode(', ', array_keys($data[0]));
        $rows = [];
        $paramIndex = 0;
        
        foreach ($data as $rowIndex => $row) {
            $placeholders = [];
            foreach ($row as $key => $value) {
                $placeholders[] = ":batch_{$rowIndex}_{$key}";
            }
            $rows[] = '(' . implode(', ', $placeholders) . ')';
        }
        
        return "INSERT INTO {$this->table} ({$columns}) VALUES " . implode(', ', $rows);
    }
    
    private function buildUpdate()
    {
        $sets = [];
        foreach ($this->updateData as $key => $value) {
            $sets[] = "{$key} = :update_{$key}";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }
        
        return $sql;
    }
    
    private function buildUpdateWithSets(array $sets)
    {
        $setClauses = [];
        foreach ($sets as $key => $value) {
            if ($value instanceof RawExpression) {
                $setClauses[] = "{$value->getExpression()}";
            } else {
                $setClauses[] = "{$key} = :set_{$key}";
            }
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
        }
        
        return $sql;
    }
    
    private function buildUpdateWithJoin(array $sets)
    {
        $setClauses = [];
        foreach ($sets as $key => $value) {
            if ($value instanceof RawExpression) {
                $setClauses[] = "{$this->table}.{$value->getExpression()}";
            } else {
                $setClauses[] = "{$this->table}.{$key} = :set_{$key}";
            }
        }
        
        $sql = "UPDATE {$this->table}";
        
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        $sql .= " SET " . implode(', ', $setClauses);
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }
        
        return $sql;
    }
    
    private function buildBatchUpdate(array $data, $keyColumn)
    {
        $cases = [];
        $ids = [];
        
        foreach ($data as $index => $row) {
            $id = $row[$keyColumn];
            $ids[] = $id;
            
            foreach ($row as $column => $value) {
                if ($column !== $keyColumn) {
                    if (!isset($cases[$column])) {
                        $cases[$column] = [];
                    }
                    $cases[$column][] = "WHEN {$keyColumn} = :id_{$index} THEN :value_{$index}_{$column}";
                }
            }
        }
        
        $sql = "UPDATE {$this->table} SET ";
        
        $setClauses = [];
        foreach ($cases as $column => $whenClauses) {
            $setClauses[] = "{$column} = CASE " . implode(' ', $whenClauses) . " ELSE {$column} END";
        }
        
        $sql .= implode(', ', $setClauses);
        $sql .= " WHERE {$keyColumn} IN (" . implode(', ', array_fill(0, count($ids), '?')) . ")";
        
        return $sql;
    }
    
    private function buildDelete()
    {
        $sql = "DELETE FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWheres();
        }
        
        return $sql;
    }
    
    private function buildUpsert()
    {
        $insertSql = $this->buildInsert();
        
        $updates = [];
        foreach ($this->updateData as $key => $value) {
            $updates[] = "{$key} = VALUES({$key})";
        }
        
        return $insertSql . " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);
    }
    
    // =============================================
    // PARAMETER METHODS (EXISTING)
    // =============================================
    
    private function getWhereParams()
    {
        $params = [];
        
        foreach ($this->wheres as $index => $where) {
            if ($where['operator'] === 'IN') {
                foreach ($where['value'] as $i => $value) {
                    $params["where_{$index}_{$i}"] = $value;
                }
            } elseif ($where['operator'] !== 'IS NULL' && $where['operator'] !== 'IS NOT NULL') {
                $params["where_{$index}"] = $where['value'];
            }
        }
        
        return $params;
    }
    
    private function getInsertParams()
    {
        $params = [];
        foreach ($this->insertData as $key => $value) {
            $params["insert_{$key}"] = $value;
        }
        return $params;
    }
    
    private function getBatchInsertParams(array $data)
    {
        $params = [];
        foreach ($data as $rowIndex => $row) {
            foreach ($row as $key => $value) {
                $params["batch_{$rowIndex}_{$key}"] = $value;
            }
        }
        return $params;
    }
    
    private function getUpdateParams()
    {
        $params = [];
        foreach ($this->updateData as $key => $value) {
            $params["update_{$key}"] = $value;
        }
        return $params;
    }
    
    private function getUpdateParamsFromSets(array $sets)
    {
        $params = [];
        foreach ($sets as $key => $value) {
            if (!($value instanceof RawExpression)) {
                $params["set_{$key}"] = $value;
            }
        }
        return $params;
    }
    
    private function getBatchUpdateParams(array $data, $keyColumn)
    {
        $params = [];
        $ids = [];
        
        foreach ($data as $index => $row) {
            $ids[] = $row[$keyColumn];
            
            foreach ($row as $column => $value) {
                if ($column !== $keyColumn) {
                    $params["value_{$index}_{$column}"] = $value;
                }
            }
            $params["id_{$index}"] = $row[$keyColumn];
        }
        
        foreach ($ids as $id) {
            $params[] = $id;
        }
        
        return $params;
    }
    
    // =============================================
    // HELPER METHODS
    // =============================================
    
    public function getEntityClass()
    {
        return $this->entityClass;
    }
    
    public function getTable()
    {
        return $this->table;
    }
    
    /**
     * Get the raw SQL query (for debugging)
     */
    public function toSql()
    {
        return $this->buildSelect();
    }
    
    /**
     * Get query parameters (for debugging)
     */
    public function getBindings()
    {
        return $this->getWhereParams();
    }
    
    /**
     * Paginate results
     */
    public function paginate($perPage = 15, $page = null)
    {
        $page = $page ?? ($_GET['page'] ?? 1);
        $offset = ($page - 1) * $perPage;
        
        $total = $this->count();
        $results = $this->limit($perPage)->offset($offset)->get();
        
        return [
            'data' => $results,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => (int)$page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }
}

