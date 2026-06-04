<?php
namespace App\Core;
// jephy-mvc/core/Column.php

class Column
{
    private $name;
    private $type;
    private $nullable = false;
    private $default = null;
    private $unsigned = false;
    private $autoIncrement = false;
    private $comment = null;
    private $after = null;
    private $charset = null;
    
    public function __construct($name, $type)
    {
        $this->name = $name;
        $this->type = $type;
    }
    
    /**
     * Make column nullable
     */
    public function nullable()
    {
        $this->nullable = true;
        return $this;
    }
    
    /**
     * Set default value
     */
    public function default($value)
    {
        $this->default = $value;
        return $this;
    }
    
    /**
     * Make column unsigned (for integers)
     */
    public function unsigned()
    {
        $this->unsigned = true;
        return $this;
    }
    
    /**
     * Make column auto-incrementing
     */
    public function autoIncrement()
    {
        $this->autoIncrement = true;
        return $this;
    }
    
    /**
     * Add comment to column
     */
    public function comment($comment)
    {
        $this->comment = $comment;
        return $this;
    }
    
    /**
     * Place column after another column
     */
    public function after($column)
    {
        $this->after = $column;
        return $this;
    }
    
    /**
     * Set charset for column
     */
    public function charset($charset)
    {
        $this->charset = $charset;
        return $this;
    }
    
    /**
     * Build the column SQL string
     */
    public function build()
    {
        $sql = "`{$this->name}` {$this->type}";
        
        if ($this->unsigned) {
            $sql .= " UNSIGNED";
        }
        
        if (!$this->nullable) {
            $sql .= " NOT NULL";
        }
        
        if ($this->autoIncrement) {
            $sql .= " AUTO_INCREMENT";
        }
        
        if ($this->default !== null) {
            if ($this->default === 'CURRENT_TIMESTAMP') {
                $sql .= " DEFAULT CURRENT_TIMESTAMP";
            } else {
                $sql .= " DEFAULT '{$this->default}'";
            }
        }
        
        if ($this->comment) {
            $sql .= " COMMENT '{$this->comment}'";
        }
        
        if ($this->after) {
            $sql .= " AFTER `{$this->after}`";
        }
        
        if ($this->charset) {
            $sql .= " CHARACTER SET {$this->charset}";
        }
        
        return $sql;
    }
    
    public function __toString()
    {
        return $this->build();
    }
}

