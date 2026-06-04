<?php
// jephy-mvc/core/Facades/Schema.php
namespace App\Core\Facades;

use App\Core\Schema as BaseSchema;

class Schema
{
    /**
     * Create a new table
     */
    public static function create($table, $callback)
    {
        return BaseSchema::create($table, $callback);
    }
    
    /**
     * Alter an existing table
     */
    public static function table($table, $callback)
    {
        return BaseSchema::table($table, $callback);
    }
    
    /**
     * Drop a table
     */
    public static function drop($table)
    {
        return BaseSchema::drop($table);
    }
    
    /**
     * Drop a table if exists
     */
    public static function dropIfExists($table)
    {
        return BaseSchema::dropIfExists($table);
    }
    
    /**
     * Rename a table
     */
    public static function rename($from, $to)
    {
        return BaseSchema::rename($from, $to);
    }
    
    /**
     * Check if table exists
     */
    public static function hasTable($table)
    {
        return BaseSchema::hasTable($table);
    }
    
    /**
     * Check if column exists
     */
    public static function hasColumn($table, $column)
    {
        return BaseSchema::hasColumn($table, $column);
    }
    
    /**
     * Get all columns
     */
    public static function getColumns($table)
    {
        return BaseSchema::getColumns($table);
    }
}
