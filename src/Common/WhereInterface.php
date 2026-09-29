<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

/**
 *
 * An interface for WHERE clauses.
 *
 * @package Aura.SqlQuery
 *
 */
interface WhereInterface
{
    /**
     *
     * Adds a WHERE condition to the query by AND.
     *
     * @param string|\Closure $cond The WHERE condition.
     *
     * @param array<int|string, mixed> $bind Values to be bound to
     * placeholders.
     *
     * @return $this
     *
     */
    public function where(string|\Closure $cond, array $bind = []): static;

    /**
     *
     * Adds a WHERE condition to the query by OR.
     *
     * @param string|\Closure $cond The WHERE condition.
     *
     * @param array<int|string, mixed> $bind Values to be bound to
     * placeholders.
     *
     * @return $this
     *
     * @see where()
     *
     */
    public function orWhere(string|\Closure $cond, array $bind = []): static;
}
