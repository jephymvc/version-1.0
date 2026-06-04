<?php
// jephy-mvc/core/Blueprint.php
namespace App\Core;

class Blueprint
{
    private $table;
    private $columns = [];
    private $keys = [];
    private $addedColumns = [];
    private $modifiedColumns = [];
    private $droppedColumns = [];
    private $addedIndexes = [];
    private $droppedIndexes = [];
    private $foreignKeys = [];
    
    public function __construct($table)
    {
        $this->table = $table;
    }
    
    /**
     * Add an auto-incrementing ID column
     */
    public function id($name = 'id')
    {
        return $this->bigIncrements($name);
    }
    
    /**
     * Add increments column
     */
    public function increments($name)
    {
        $column = "`{$name}` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
        $this->columns[] = $column;
        return $this;
    }
    
    /**
     * Add big increments column
     */
    public function bigIncrements($name)
    {
        $column = "`{$name}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
        $this->columns[] = $column;
        return $this;
    }
    
    /**
     * Add string column
     */
    public function string($name, $length = 255)
    {
        $column = new Column($name, "VARCHAR({$length})");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add text column
     */
    public function text($name)
    {
        $column = new Column($name, "TEXT");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add long text column
     */
    public function longText($name)
    {
        $column = new Column($name, "LONGTEXT");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add integer column
     */
    public function integer($name, $length = 11)
    {
        $column = new Column($name, "INT({$length})");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add big integer column
     */
    public function bigInteger($name)
    {
        $column = new Column($name, "BIGINT");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add tiny integer column
     */
    public function tinyInteger($name)
    {
        $column = new Column($name, "TINYINT");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add boolean column
     */
    public function boolean($name)
    {
        $column = new Column($name, "TINYINT(1)");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add decimal column
     */
    public function decimal($name, $total = 8, $places = 2)
    {
        $column = new Column($name, "DECIMAL({$total}, {$places})");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add float column
     */
    public function float($name)
    {
        $column = new Column($name, "FLOAT");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add date column
     */
    public function date($name)
    {
        $column = new Column($name, "DATE");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add datetime column
     */
    public function dateTime($name)
    {
        $column = new Column($name, "DATETIME");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add timestamp column
     */
    public function timestamp($name)
    {
        $column = new Column($name, "TIMESTAMP");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add timestamps (created_at, updated_at)
     */
    public function timestamps()
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        return $this;
    }
    
    /**
     * Add soft deletes column
     */
    public function softDeletes()
    {
        $this->timestamp('deleted_at')->nullable();
        return $this;
    }
    
    /**
     * Add enum column
     */
    public function enum($name, $values)
    {
        $valuesStr = "'" . implode("', '", $values) . "'";
        $column = new Column($name, "ENUM({$valuesStr})");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add JSON column
     */
    public function json($name)
    {
        $column = new Column($name, "JSON");
        $this->columns[] = $column;
        return $column;
    }
    
    /**
     * Add primary key
     */
    public function primary($column)
    {
        $this->keys[] = "PRIMARY KEY (`{$column}`)";
        return $this;
    }
    
    /**
     * Add unique key
     */
    public function unique($column, $name = null)
    {
        $name = $name ?: "{$this->table}_{$column}_unique";
        $this->keys[] = "UNIQUE KEY `{$name}` (`{$column}`)";
        return $this;
    }
    
    /**
     * Add index
     */
    public function index($column, $name = null)
    {
        $name = $name ?: "{$this->table}_{$column}_index";
        $this->keys[] = "INDEX `{$name}` (`{$column}`)";
        return $this;
    }
    
    /**
     * Add foreign key
     */
    public function foreign($column)
    {
        return new ForeignKey($this, $column);
    }
    
    /**
     * Add foreign key constraint
     */
    public function addForeignKey($column, $references, $on, $name = null)
    {
        $name = $name ?: "{$this->table}_{$column}_foreign";
        $this->keys[] = "CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$on}`(`{$references}`)";
        return $this;
    }
    
    /**
     * For altering tables - add a new column
     */
    public function addColumn($column)
    {
        $this->addedColumns[] = $column;
        return $this;
    }
    
    /**
     * For altering tables - modify an existing column
     */
    public function modifyColumn($column)
    {
        $this->modifiedColumns[] = $column;
        return $this;
    }
    
    /**
     * For altering tables - drop a column
     */
    public function dropColumn($column)
    {
        $this->droppedColumns[] = $column;
        return $this;
    }
    
    /**
     * For altering tables - add an index
     */
    public function addIndex($index)
    {
        $this->addedIndexes[] = $index;
        return $this;
    }
    
    /**
     * For altering tables - drop an index
     */
    public function dropIndex($index)
    {
        $this->droppedIndexes[] = $index;
        return $this;
    }
	
	    
    /**
     * Drop primary key
     */
    public function dropPrimary()
    {
        $prefix = Schema::getPrefix();
        $table = $prefix . $this->table;
        $sql = "ALTER TABLE `{$table}` DROP PRIMARY KEY";
        Schema::execute($sql);
        return $this;
    }
    
    // Getters for Schema class
    public function getColumns() { return $this->columns; }
    public function getKeys() { return $this->keys; }
    public function getAddedColumns() { return $this->addedColumns; }
    public function getModifiedColumns() { return $this->modifiedColumns; }
    public function getDroppedColumns() { return $this->droppedColumns; }
    public function getAddedIndexes() { return $this->addedIndexes; }
    public function getDroppedIndexes() { return $this->droppedIndexes; }
}

