<?php
// jephy-mvc/core/Schema.php
namespace App\Core;

use App\Core\Config;
use PDO;
use PDOException;

class Schema
{
    /**
     * @var PDO Database connection
     */
    private static $connection;
    
    /**
     * @var array Table prefixes
     */
    private static $prefix = '';
    
    /**
     * @var array Supported column types
     */
    private static $columnTypes = [
        'increments', 'bigIncrements', 'string', 'text', 'integer', 'bigInteger',
        'float', 'double', 'decimal', 'boolean', 'date', 'datetime', 'timestamp',
        'time', 'year', 'binary', 'json', 'enum', 'char', 'tinyInteger', 'smallInteger',
        'mediumInteger', 'longText', 'mediumText', 'tinyText'
    ];
    
    /**
     * Initialize database connection
     */
    private static function getConnection()
    {
        
		if (self::$connection === null) {
			
            $config 		= Config::getInstance();            
            $host 			= $config->get( 'database.host', 'localhost' );
            $name 			= $config->get( 'database.name' );
            $user 			= $config->get( 'database.user' );
            $password 		= $config->get( 'database.password' );
            $driver 		= $config->get( 'database.driver', 'mysql' );
            self::$prefix 	= $config->get( 'database.prefix', '' );
            
            if (!$name) {
                throw new \RuntimeException('Database name not configured');
            }
			
            $dsn = "";
			 
            try {               
				
				// Build DSN based on driver
				switch ( $driver ) {
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
				
                self::$connection = new PDO($dsn, $user, $password);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
				
            } catch (PDOException $e) {
                throw new \RuntimeException("Schema connection failed: " . $e->getMessage());
            }
        }
        
        return self::$connection;
    }
    
    /**
     * Create a new table
     */
    public static function create($table, $callback)
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql('create');
        return self::execute($sql);
    }
    
    /**
     * Alter an existing table
     */
    public static function table($table, $callback)
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql('alter');
        return self::execute($sql);
    }
    
    /**
     * Drop a table
     */
    public static function drop($table)
    {
        $table = self::$prefix . $table;
        $sql = "DROP TABLE IF EXISTS `{$table}`";
        return self::execute($sql);
    }
    
    /**
     * Drop a table if exists
     */
    public static function dropIfExists($table)
    {
        return self::drop($table);
    }
    
    /**
     * Rename a table
     */
    public static function rename($oldTable, $newTable)
    {
        $oldTable = self::$prefix . $oldTable;
        $newTable = self::$prefix . $newTable;
        $sql = "RENAME TABLE `{$oldTable}` TO `{$newTable}`";
        return self::execute($sql);
    }
    
    /**
     * Check if table exists
     */
    public static function hasTable($table)
    {
        $table = self::$prefix . $table;
        $sql = "SHOW TABLES LIKE '{$table}'";
        $result = self::execute($sql);
        return $result->rowCount() > 0;
    }
    
    /**
     * Check if column exists
     */
    public static function hasColumn($table, $column)
    {
        $table = self::$prefix . $table;
        $sql = "SHOW COLUMNS FROM `{$table}` WHERE Field = :column";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute([':column' => $column]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get all columns for a table
     */
    public static function getColumns($table)
    {
        $table = self::$prefix . $table;
        $sql = "SHOW COLUMNS FROM `{$table}`";
        $result = self::execute($sql);
        return $result->fetchAll();
    }
    
    /**
     * Execute SQL statement
     */
    private static function execute($sql)
    {
        $connection = self::getConnection();
        $stmt = $connection->prepare($sql);
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction()
    {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit()
    {
        return self::getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback()
    {
        return self::getConnection()->rollBack();
    }
}

