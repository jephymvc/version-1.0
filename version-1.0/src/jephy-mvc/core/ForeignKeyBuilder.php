<?php
// jephy-mvc/core/ForeignKeyBuilder.php
namespace App\Core;

class ForeignKeyBuilder
{
    private $blueprint;
    private $foreign;
    
    public function __construct($blueprint, &$foreign)
    {
        $this->blueprint = $blueprint;
        $this->foreign = $foreign;
    }
    
    /**
     * Set referenced table and column
     */
    public function references($column)
    {
        $this->foreign['references'] = [
            'column' => $column,
            'table' => null
        ];
        return $this;
    }
    
    /**
     * Set referenced table
     */
    public function on($table)
    {
        $this->foreign['references']['table'] = $table;
        $this->foreign['name'] = $this->generateForeignKeyName();
        $this->blueprint->addForeignKey($this->foreign);
        return $this;
    }
    
    /**
     * Set ON DELETE action
     */
    public function onDelete($action)
    {
        $this->foreign['onDelete'] = $action;
        return $this;
    }
    
    /**
     * Set ON UPDATE action
     */
    public function onUpdate($action)
    {
        $this->foreign['onUpdate'] = $action;
        return $this;
    }
    
    /**
     * Generate foreign key name
     */
    private function generateForeignKeyName()
    {
        $table = $this->foreign['references']['table'];
        $column = $this->foreign['column'];
        return "fk_{$table}_{$column}";
    }
}