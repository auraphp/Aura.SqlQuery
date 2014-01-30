<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql_Query
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\AbstractQuery;

/**
 *
 * An object for DELETE queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Delete extends AbstractQuery implements DeleteInterface
{
    /**
     *
     * The table to delete from.
     *
     * @var string
     *
     */
    protected $from;

    /**
     *
     * Sets the table to delete from.
     *
     * @param string $table The table to delete from.
     *
     * @return $this
     *
     */
    public function from($table)
    {
        $this->from = $this->quoteName($table);
        return $this;
    }
    
    /**
     * 
     * Builds this query object into a string.
     * 
     * @return string
     * 
     */
    protected function build()
    {
        return 'DELETE'
            . $this->buildFlags()
            . $this->buildFrom()
            . $this->buildWhere()
            . $this->buildOrderBy()
            . $this->buildLimit()
            . $this->buildReturning();
    }
    
    /**
     * 
     * Builds the FROM clause.
     *
     * @return string
     * 
     */
    protected function buildFrom()
    {
        return " FROM {$this->from}";
    }

    /**
     *
     * Adds a WHERE condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return $this
     *
     */
    public function where($cond)
    {
        $bind = func_get_args();
        array_shift($bind);

        $this->addWhere($cond, 'AND', $bind);

        return $this;
    }

    /**
     *
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return $this
     *
     * @see where()
     *
     */
    public function orWhere($cond)
    {
        $bind = func_get_args();
        array_shift($bind);

        $this->addWhere($cond, 'OR', $bind);

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
}
