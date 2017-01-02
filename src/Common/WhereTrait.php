<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Common;

/**
 *
 * Common code for WHERE clauses.
 *
 * @package Aura.SqlQuery
 *
 */
trait WhereTrait
{
    /**
     *
     * Adds a WHERE condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to be bound to placeholders
     *
     * @return $this
     *
     */
    public function where($cond, ...$bind)
    {
        $this->addWhere('AND', $cond, ...$bind);
        return $this;
    }

    /**
     *
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to be bound to placeholders
     *
     * @return $this
     *
     * @see where()
     *
     */
    public function orWhere($cond, ...$bind)
    {
        $this->addWhere('OR', $cond, ...$bind);
        return $this;
    }

    /**
     *
     * Adds a WHERE condition to the query by AND or OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $andor Add the condition using this operator, typically
     * 'AND' or 'OR'.
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return $this
     *
     */
    abstract protected function addWhere($andor, $cond, ...$bind);
}
