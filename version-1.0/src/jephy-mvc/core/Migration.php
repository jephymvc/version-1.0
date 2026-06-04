<?php
namespace App\Core;

class Migration
{
    /**
     * @var array Registered migrations
     */
    private static $migrations = [];
    
    /**
     * Register a migration
     */
    public static function register($migrationClass)
    {
        self::$migrations[] = $migrationClass;
    }
    
    /**
     * Run all migrations
     */
    public static function migrate()
    {
        // Create migrations table if not exists
        self::createMigrationsTable();        
        $ran = self::getRanMigrations();        
        foreach (self::$migrations as $migration) {
            if (!in_array($migration, $ran)) {
                echo "Running: {$migration}\n";                
                $instance = new $migration();
                $instance->up();                
                self::recordMigration($migration);                
                echo "Completed: {$migration}\n";
            }
        }
    }
    
    /**
     * Rollback last migration
     */
    public static function rollback($steps = 1)
    {
        $ran = self::getRanMigrations();
        $toRollback = array_slice($ran, -$steps);
        
        foreach ($toRollback as $migration) {
            echo "Rolling back: {$migration}\n";
            
            $instance = new $migration();
            $instance->down();
            
            self::removeMigration($migration);
            
            echo "Rolled back: {$migration}\n";
        }
    }
    
    /**
     * Create migrations table
     */
    private static function createMigrationsTable()
    {
        if (!Schema::hasTable('migrations')) {
            Schema::create('migrations', function(Blueprint $table) {
                $table->increments('id');
                $table->string('migration', 255);
                $table->integer('batch');
                $table->timestamp('ran_at')->useCurrent();
            });
        }
    }
    
    /**
     * Get ran migrations
     */
    private static function getRanMigrations()
    {
        $db = Schema::getConnection();
        $stmt = $db->query("SELECT migration FROM migrations ORDER BY id");
        $results = $stmt->fetchAll();
        
        return array_map(function($row) {
            return $row->migration;
        }, $results);
    }
    
    /**
     * Record migration
     */
    private static function recordMigration($migration)
    {
        $db 	= Schema::getConnection();
        $batch 	= self::getNextBatchNumber();
        
        $stmt 	= $db->prepare("INSERT INTO migrations (migration, batch, ran_at) VALUES (?, ?, NOW())");
        $stmt->execute([$migration, $batch]);
    }
    
    /**
     * Remove migration record
     */
    private static function removeMigration($migration)
    {
        $db 	= Schema::getConnection();
        $stmt 	= $db->prepare("DELETE FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
    }
    
    /**
     * Get next batch number
     */
    private static function getNextBatchNumber()
    {
        $db = Schema::getConnection();
        $stmt = $db->query("SELECT MAX(batch) as max_batch FROM migrations");
        $result = $stmt->fetch();
        
        return ($result->max_batch ?? 0) + 1;
    }
    
    /**
     * Refresh all migrations (rollback then migrate)
     */
    public static function refresh()
    {
        self::rollback(1000); // Rollback all
        self::migrate();
    }
    
    /**
     * Reset all migrations
     */
    public static function reset()
    {
        $ran = self::getRanMigrations();
        
        foreach (array_reverse($ran) as $migration) {
            echo "Resetting: {$migration}\n";            
            $instance = new $migration();
            $instance->down();            
            self::removeMigration($migration);
        }
    }
}

// Helper function to get Schema prefix
function getPrefix()
{
    return App\Core\Schema::getPrefix();
}