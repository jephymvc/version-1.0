<?php
namespace App\Core;
// jephy-mvc/core/BaseRepository.php


use App\Core\Config;
use App\Core\HookManager;

abstract class BaseRepository
{
    /**
     * @var \PDO Database connection
     */
    protected $db;
    
    /**
     * @var string Table name
     */
    protected $table;
    
    /**
     * @var string Primary key column
     */
    protected $primaryKey = 'id';
    
    /**
     * @var array Fillable fields
     */
    protected $fillable = [];
    
    /**
     * @var array Hidden fields
     */
    protected $hidden = [];
    
    /**
     * @var array Guarded fields
     */
    protected $guarded = ['id'];
    
    /**
     * @var Config Configuration instance
     */
    protected $config;
    
    /**
     * @var HookManager Hook manager instance
     */
    protected $hookManager;
    
    /**
     * @var bool Enable query logging
     */
    protected $logQueries = false;
    
    /**
     * @var array Query log
     */
    private $queryLog = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->hookManager = $this->getHookManager();
        $this->logQueries = $this->config->get('app.debug', false);
        $this->initializeDatabase();
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase()
    {
        $host = $this->config->get('database.host', 'localhost');
        $name = $this->config->get('database.name');
        $user = $this->config->get('database.user');
        $password = $this->config->get('database.password');
        $driver = $this->config->get('database.driver', 'mysql');
        
        if (!$name) {
            throw new \RuntimeException('Database name not configured');
        }
        
        try {
            $dsn = "{$driver}:host={$host};dbname={$name};charset=utf8mb4";
            $this->db = new \PDO($dsn, $user, $password);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get HookManager instance
     */
    protected function getHookManager()
    {
        global $hookManager;
        
        if ($hookManager instanceof HookManager) {
            return $hookManager;
        }
        
        return new HookManager();
    }
    
    /**
     * Log query
     */
    protected function logQuery($sql, $params = [], $time = 0)
    {
        if ($this->logQueries) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => $time
            ];
            
            error_log(sprintf("[SQL] %s | Time: %.2fms", $sql, $time));
        }
    }
    
    /**
     * Get query log
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }
    
    /**
     * Execute query with timing
     */
    protected function execute($sql, $params = [])
    {
        $start = microtime(true);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $time = (microtime(true) - $start) * 1000;
        
        $this->logQuery($sql, $params, $time);
        
        return $stmt;
    }
    
    /**
     * Get all records
     */
    public function all($page = null, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table}";
        
        if ($page !== null && $limit !== null) {
            $offset = ($page - 1) * $limit;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $stmt = $this->execute($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Find record by ID
     */
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->execute($sql, [':id' => $id]);
        
        return $stmt->fetch();
    }
    
    /**
     * Find records by field value
     */
    public function findBy($field, $value, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value";
        
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->execute($sql, [':value' => $value]);
        
        return $limit === 1 ? $stmt->fetch() : $stmt->fetchAll();
    }
    
    /**
     * Create new record
     */
    public function create($data)
    {
        // Filter fillable fields
        $data = $this->filterFillable($data);
        
        // Remove guarded fields
        $data = $this->removeGuarded($data);
        
        $fields = array_keys($data);
        $placeholders = array_map(function($field) {
            return ":{$field}";
        }, $fields);
        
        $sql = sprintf(
            "INSERT INTO {$this->table} (%s) VALUES (%s)",
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $params = [];
        foreach ($data as $key => $value) {
            $params[":{$key}"] = $value;
        }
        
        $this->execute($sql, $params);
        
        $id = $this->db->lastInsertId();
        
        // Fire hook
        $this->hookManager->fire('repository.created', [
            'table' => $this->table,
            'id' => $id,
            'data' => $data
        ]);
        
        return $this->find($id);
    }
    
    /**
     * Update record
     */
    public function update($id, $data)
    {
        // Filter fillable fields
        $data = $this->filterFillable($data);
        
        // Remove guarded fields
        $data = $this->removeGuarded($data);
        
        $setClause = [];
        foreach ($data as $key => $value) {
            $setClause[] = "{$key} = :{$key}";
        }
        
        $sql = sprintf(
            "UPDATE {$this->table} SET %s WHERE {$this->primaryKey} = :id",
            implode(', ', $setClause)
        );
        
        $params = [':id' => $id];
        foreach ($data as $key => $value) {
            $params[":{$key}"] = $value;
        }
        
        $this->execute($sql, $params);
        
        // Fire hook
        $this->hookManager->fire('repository.updated', [
            'table' => $this->table,
            'id' => $id,
            'data' => $data
        ]);
        
        return $this->find($id);
    }
    
    /**
     * Delete record
     */
    public function delete($id, $hardDelete = false)
    {
        if ($hardDelete) {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        } else {
            // Soft delete - assumes 'deleted_at' column exists
            $sql = "UPDATE {$this->table} SET deleted_at = NOW() WHERE {$this->primaryKey} = :id";
        }
        
        $stmt = $this->execute($sql, [':id' => $id]);
        
        // Fire hook
        $this->hookManager->fire('repository.deleted', [
            'table' => $this->table,
            'id' => $id,
            'hard' => $hardDelete
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Count records
     */
    public function count($where = null, $params = [])
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch();
        
        return $result->count;
    }
    
    /**
     * Filter fillable fields
     */
    protected function filterFillable($data)
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Remove guarded fields
     */
    protected function removeGuarded($data)
    {
        if (empty($this->guarded)) {
            return $data;
        }
        
        return array_diff_key($data, array_flip($this->guarded));
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
    
    /**
     * Get last inserted ID
     */
    public function lastInsertId()
    {
        return $this->db->lastInsertId();
    }
}