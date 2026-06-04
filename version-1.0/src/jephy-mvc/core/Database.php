<?php
namespace App\Core;

use PDO;
use PDOException;
use App\Core\Config;

class Database
{
    private static $instance;
    private $pdo;
    private $config;
    private $driver;
    
    private function __construct()
    {
        $this->connect();
        $this->config = Config::getInstance();
        $this->driver = $this->config->get( 'database.driver' );
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function getConnection()
    {
        return self::getInstance()->getPdo();
    }
	
	private function connect()
	{
		try {
			
			$config 	= Config::getInstance();
			$driver 	= $config->get('database.driver') ?: 'mysql';
			$host 		= $config->get('database.host') ?: 'localhost';
			$dbname 	= $config->get('database.name') ?: 'jephy';
			$username 	= $config->get('database.user') ?: 'root';
			$password 	= $config->get('database.password') ?: '';
			$port 		= $config->get('database.port') ?: 3306;
			
			// Build DSN based on driver
			switch ($driver) {
				case 'pgsql':
				case 'postgresql':
					// PostgreSQL - no charset in DSN
					$dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
					
					// Add SSL for AWS RDS
					if (strpos($host, 'rds.amazonaws.com') !== false) {
						$dsn .= ";sslmode=require";
					}
					break;
					
				case 'sqlite':
					// SQLite
					$dsn = "sqlite:{$dbname}";
					break;
					
				case 'sqlsrv':
				case 'mssql':
					// SQL Server
					$dsn = "sqlsrv:Server={$host},{$port};Database={$dbname}";
					break;
					
				case 'oci':
				case 'oracle':
					// Oracle
					$dsn = "oci:dbname=//{$host}:{$port}/{$dbname};charset=UTF8";
					break;
					
				case 'mysql':
				default:
					// MySQL - includes charset
					$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
					break;
			}
			
			// For MySQL and others that need username/password
			if ($driver === 'sqlite') {
				$this->pdo = new PDO($dsn, null, null, [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				]);
			} else {
				$this->pdo = new PDO($dsn, $username, $password, [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
				]);
			}
			
		} catch (PDOException $e) {
			// Log the actual DSN for debugging (remove in production)
			error_log("Connection failed for driver: $driver, DSN: " . ($dsn ?? 'not set'));
			throw new \Exception("Database connection failed: " . $e->getMessage());
		}
	}	

    
    public function getPdo()
    {
        return $this->pdo;
    }
    
    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
		
		$sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";    
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where)
    {
        $setParts = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $paramName = "set_" . $key;
            $setParts[] = "`$key` = :$paramName";
            $params[$paramName] = $value;
        }
        
        $whereParts = [];
        $i = 0;
        foreach ($where as $key => $value) {
            $paramName = "where_" . $i;
            $whereParts[] = "`$key` = :$paramName";
            $params[$paramName] = $value;
            $i++;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setParts);
        $sql .= " WHERE " . implode(' AND ', $whereParts);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
	
	public function select($table, $columns = '*', $where = [], $orderBy = '', $limit = null, $offset = null)
    {
        // Handle array orderBy
        if (is_array($orderBy)) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderParts[] = "`$field` $direction";
            }
            $orderBy = implode(', ', $orderParts);
        }
        
        // Handle column list
        if (is_array($columns)) {
            $columns = implode(', ', array_map(function($col) {
                return "`$col`";
            }, $columns));
        } elseif ($columns !== '*') {
            $columns = "`$columns`";
        }
        
        $sql = "SELECT $columns FROM `$table`";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            $i = 0;
            foreach ($where as $key => $value) {
                // Handle operators
                if (is_array($value) && isset($value['operator'])) {
                    $operator = strtoupper($value['operator']);
                    $val = $value['value'] ?? null;
                    
                    switch ($operator) {
                        case '!=':
                        case '<>':
                        case '>':
                        case '<':
                        case '>=':
                        case '<=':
                            $paramName = "where_" . $i;
                            $conditions[] = "`$key` $operator :$paramName";
                            $params[$paramName] = $val;
                            break;
                            
                        case 'IN':
                        case 'NOT IN':
                            if (is_array($val)) {
                                $inParams = [];
                                foreach ($val as $inIdx => $inVal) {
                                    $inParamName = "where_in_" . $i . "_" . $inIdx;
                                    $inParams[] = ":$inParamName";
                                    $params[$inParamName] = $inVal;
                                }
                                $conditions[] = "`$key` $operator (" . implode(', ', $inParams) . ")";
                            }
                            break;
                            
                        case 'LIKE':
                        case 'NOT LIKE':
                            $paramName = "where_" . $i;
                            $conditions[] = "`$key` $operator :$paramName";
                            $params[$paramName] = $val;
                            break;
                            
                        case 'IS':
                        case 'IS NOT':
                            $conditions[] = "`$key` $operator NULL";
                            break;
                            
                        case 'BETWEEN':
                        case 'NOT BETWEEN':
                            if (is_array($val) && count($val) === 2) {
                                $paramName1 = "where_" . $i . "_1";
                                $paramName2 = "where_" . $i . "_2";
                                $conditions[] = "`$key` $operator :$paramName1 AND :$paramName2";
                                $params[$paramName1] = $val[0];
                                $params[$paramName2] = $val[1];
                            }
                            break;
                    }
                } else {
                    // Simple equality
                    $paramName = "where_" . $i;
                    $conditions[] = "`$key` = :$paramName";
                    $params[$paramName] = $value;
                }
                $i++;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit_value";
            $params['limit_value'] = (int)$limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET :offset_value";
                $params['offset_value'] = (int)$offset;
            }
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            
            // Bind parameters with proper type
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
                } elseif (is_bool($value)) {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_BOOL);
                } else {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_STR);
                }
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("SQL Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new \Exception("Database query failed: " . $e->getMessage() . " | SQL: " . $sql);
        }
    }
    
    public function selectAlt($table, $columns = '*', $where = [], $orderBy = '', $limit = null, $offset = null)
    {
        // Handle column list
        if (is_array($columns)) {
            $columns = implode(', ', array_map(function($col) {
                return "`$col`";
            }, $columns));
        } elseif ($columns !== '*') {
            $columns = "`$columns`";
        }
        
        $sql = "SELECT $columns FROM `$table`";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            $i = 0;
            foreach ($where as $key => $value) {
                // Handle different operators
                if (is_array($value) && isset($value['operator'])) {
                    $paramName = "where_" . $i;
                    $operator = $value['operator'];
                    $val = $value['value'] ?? null;
                    
                    if ($operator === 'IN' && is_array($val)) {
                        $inParams = [];
                        foreach ($val as $inIdx => $inVal) {
                            $inParamName = "where_in_" . $i . "_" . $inIdx;
                            $inParams[] = ":$inParamName";
                            $params[$inParamName] = $inVal;
                        }
                        $conditions[] = "`$key` IN (" . implode(', ', $inParams) . ")";
                    } elseif ($operator === 'LIKE') {
                        $conditions[] = "`$key` LIKE :$paramName";
                        $params[$paramName] = $val;
                    } elseif ($operator === 'BETWEEN' && is_array($val) && count($val) === 2) {
                        $paramName1 = "where_" . $i . "_1";
                        $paramName2 = "where_" . $i . "_2";
                        $conditions[] = "`$key` BETWEEN :$paramName1 AND :$paramName2";
                        $params[$paramName1] = $val[0];
                        $params[$paramName2] = $val[1];
                    } else {
                        $conditions[] = "`$key` $operator :$paramName";
                        $params[$paramName] = $val;
                    }
                } else {
                    $paramName = "where_" . $i;
                    $conditions[] = "`$key` = :$paramName";
                    $params[$paramName] = $value;
                }
                $i++;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT :limit_value";
            $params['limit_value'] = (int)$limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET :offset_value";
                $params['offset_value'] = (int)$offset;
            }
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            
            // Bind parameters with proper type
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_STR);
                }
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("SQL Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new \Exception("Database query failed: " . $e->getMessage() . " | SQL: " . $sql);
        }
    }
    
    public function selectOne($table, $columns = '*', $where = [])
    {
        $results = $this->select($table, $columns, $where, '', 1);
        return $results[0] ?? null;
    }
    
    public function delete($table, $where)
    {
        $whereParts = [];
        $params = [];
        $i = 0;
        
        foreach ($where as $key => $value) {
            $paramName = "where_" . $i;
            $whereParts[] = "`$key` = :$paramName";
            $params[$paramName] = $value;
            $i++;
        }
        
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereParts);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    public function count($table, $where = [])
    {
        
		$sql = "SELECT COUNT(*) as count FROM `$table`";
		       
        
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            $i = 0;
            foreach ($where as $key => $value) {
                $paramName = "where_" . $i;
                $conditions[] = "`$key` = :$paramName";
                $params[$paramName] = $value;
                $i++;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
    
    public function exists($table, $where)
    {
        return $this->count($table, $where) > 0;
    }
    
    public function query($sql, $params = [])
    {
		$stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    #	public function lastInsertId()
    #	{
    #	    return $this->pdo->lastInsertId();
    #	}
    
	public function lastInsertId($name = null)
	{
		if ($this->driver === 'pgsql') {
			// For PostgreSQL, if no sequence name provided, return null
			// Better to use RETURNING clause in your queries
			if ($name === null) {
				return null;
			}
			return $this->pdo->lastInsertId($name);
		}
		
		// For MySQL, simple lastInsertId works
		return $this->pdo->lastInsertId();
	}
	
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }
}

