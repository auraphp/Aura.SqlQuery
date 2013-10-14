<?php
/**
 * 
 * This file is part of Aura for PHP.
 * 
 * @package Aura.Sql
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Sql_Query\Traits;

/**
 * 
 * A trait for adding WHERE conditions.
 * 
 * @package Aura.Sql
 * 
 */
trait WhereTrait
{
    /**
     * 
     * The list of WHERE conditions.
     * 
     * @var array
     * 
     */
    protected $where = [];

    protected $bind_where = [];
    
    /**
     * 
     * Adds a WHERE condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     * 
     * @param string $cond The WHERE condition.
     * 
     * @return $this
     * 
     */
    public function where($cond)
    {
        // quote names in the condition
        $cond = $this->quoteNamesIn($cond);
        
        // bind values to the condition
        $bind = func_get_args();
        array_shift($bind);
        foreach ($bind as $value) {
            $this->bind_where[] = $value;
        }

        if ($this->where) {
            $this->where[] = "AND $cond";
        } else {
            $this->where[] = $cond;
        }

        // done
        return $this;
    }

    /**
     * 
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     * 
     * @param string $cond The WHERE condition.
     * 
     * @return $this
     * 
     * @see where()
     * 
     */
    public function orWhere($cond)
    {
        // quote names in the condition
        $cond = $this->quoteNamesIn($cond);
        
        // bind values to the condition
        $bind = func_get_args();
        array_shift($bind);
        foreach ($bind as $value) {
            $this->bind_where[] = $value;
        }

        if ($this->where) {
            $this->where[] = "OR $cond";
        } else {
            $this->where[] = $cond;
        }

        // done
        return $this;
    }
    
    /**
     * 
     * Gets the values to bind to placeholders.
     * 
     * @return array
     * 
     */
    public function getBindValues()
    {
        $bind_values = $this->bind_values;
        $i = 1;
        foreach ($this->bind_where as $val) {
            $bind_values[$i] = $val;
            $i ++;
        }
        return $bind_values;
    }
    
    /**
     * 
     * Builds the WHERE conditions into the statement.
     * 
     * @return null
     * 
     */
    protected function buildWhere()
    {
        if ($this->where) {
            $this->stm .= PHP_EOL . 'WHERE' . $this->indent($this->where);
        }
    }
}
