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
        if ($bind) {
            $cond = $this->autobind($cond, $bind);
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
        if ($bind) {
            $cond = $this->autobind($cond, $bind);
        }

        if ($this->where) {
            $this->where[] = "OR $cond";
        } else {
            $this->where[] = $cond;
        }

        // done
        return $this;
    }
    
    protected function buildWhere()
    {
        if ($this->where) {
            return 'WHERE' . $this->indent($this->where);
        }
    }
}
