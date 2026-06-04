<?php
namespace App\Core;
// jephy-mvc/core/ForeignKey.php

class ForeignKey
{
    private $blueprint;
    private $column;
    private $references = null;
    private $on = null;
    private $onDelete = null;
    private $onUpdate = null;
    private $name = null;
    
    public function __construct($blueprint, $column)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
    }
    
    /**
     * Set referenced table and column
     */
    public function references($column)
    {
        $this->references = $column;
        return $this;
    }
    
    /**
     * Set referenced table
     */
    public function on($table)
    {
        $this->on = $table;
        $this->blueprint->addForeignKey($this->column, $this->references, $this->on, $this->name);
        
        // Add on delete constraint if set
        if ($this->onDelete) {
            $this->blueprint->addForeignKeyConstraint($this->name, $this->onDelete, $this->onUpdate);
        }
        
        return $this->blueprint;
    }
    
    /**
     * Set ON DELETE CASCADE
     */
    public function onDelete($action)
    {
        $this->onDelete = $action;
        return $this;
    }
    
    /**
     * Set ON UPDATE CASCADE
     */
    public function onUpdate($action)
    {
        $this->onUpdate = $action;
        return $this;
    }
    
    /**
     * Set custom constraint name
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }
}
