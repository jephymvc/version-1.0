<?php
namespace App\Core;
// jephy-mvc/core/MigrationRunner.php

class MigrationRunner
{
    private static $migrationsTable = 'migrations';
    
    /**
     * Create migrations table if not exists
     */
    public static function init()
    {
        if (!Schema::hasTable(self::$migrationsTable)) {
            Schema::create(self::$migrationsTable, function(Blueprint $table) {
                $table->id();
                $table->string('migration', 255);
                $table->integer('batch');
                $table->timestamps();
            });
        }
    }
    
    /**
     * Run all pending migrations
     */
    public static function migrate($path = null)
    {
        self::init();
        
        $path = $path ?: __DIR__ . '/../app/migrations/';
        $files = glob($path . '*.php');
        
        $executedMigrations = self::getExecutedMigrations();
        $batch = self::getNextBatchNumber();
        $migrated = [];
        
        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            
            if (!in_array($migrationName, $executedMigrations)) {
                require_once $file;
                $className = "App\\Migrations\\{$migrationName}";
                $migration = new $className();
                
                echo "Migrating: {$migrationName}\n";
                $migration->up();
                
                self::recordMigration($migrationName, $batch);
                $migrated[] = $migrationName;
            }
        }
        
        if (!empty($migrated)) {
            echo "Migrated: " . implode(', ', $migrated) . "\n";
        } else {
            echo "Nothing to migrate\n";
        }
    }
    
    /**
     * Rollback last batch
     */
    public static function rollback()
    {
        self::init();
        
        $lastBatch = self::getLastBatchNumber();
        
        if ($lastBatch === null) {
            echo "Nothing to rollback\n";
            return;
        }
        
        $migrations = self::getMigrationsByBatch($lastBatch);
        
        foreach (array_reverse($migrations) as $migration) {
            require_once __DIR__ . '/../app/migrations/' . $migration->migration . '.php';
            $className = "App\\Migrations\\{$migration->migration}";
            $instance = new $className();
            
            echo "Rolling back: {$migration->migration}\n";
            $instance->down();
            
            self::deleteMigration($migration->migration);
        }
        
        echo "Rolled back batch {$lastBatch}\n";
    }
    
    /**
     * Reset all migrations
     */
    public static function reset()
    {
        self::init();
        
        $migrations = self::getAllMigrations();
        
        foreach (array_reverse($migrations) as $migration) {
            require_once __DIR__ . '/../app/migrations/' . $migration->migration . '.php';
            $className = "App\\Migrations\\{$migration->migration}";
            $instance = new $className();
            
            echo "Resetting: {$migration->migration}\n";
            $instance->down();
            
            self::deleteMigration($migration->migration);
        }
        
        echo "All migrations reset\n";
    }
    
    /**
     * Refresh migrations (reset + migrate)
     */
    public static function refresh()
    {
        self::reset();
        self::migrate();
    }
    
    /**
     * Get executed migrations
     */
    private static function getExecutedMigrations()
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("SELECT migration FROM " . self::$migrationsTable . " ORDER BY id");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    /**
     * Record migration
     */
    private static function recordMigration($migration, $batch)
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("INSERT INTO " . self::$migrationsTable . " (migration, batch, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$migration, $batch]);
    }
    
    /**
     * Delete migration record
     */
    private static function deleteMigration($migration)
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("DELETE FROM " . self::$migrationsTable . " WHERE migration = ?");
        $stmt->execute([$migration]);
    }
    
    /**
     * Get next batch number
     */
    private static function getNextBatchNumber()
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("SELECT MAX(batch) as max_batch FROM " . self::$migrationsTable);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return ($result->max_batch ?? 0) + 1;
    }
    
    /**
     * Get last batch number
     */
    private static function getLastBatchNumber()
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("SELECT MAX(batch) as max_batch FROM " . self::$migrationsTable);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result->max_batch;
    }
    
    /**
     * Get migrations by batch
     */
    private static function getMigrationsByBatch($batch)
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("SELECT * FROM " . self::$migrationsTable . " WHERE batch = ? ORDER BY id");
        $stmt->execute([$batch]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get all migrations
     */
    private static function getAllMigrations()
    {
        $db = Schema::getConnection();
        $stmt = $db->prepare("SELECT * FROM " . self::$migrationsTable . " ORDER BY id");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
