<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;
use Aura\SqlQuery\Exception\BadMethodCallException;

/**
 *
 * An object for Sqlsrv SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Select extends Common\Select
{
    /**
     *
     * A builder for the query.
     *
     * @var SelectBuilder
     *
     */
    protected $builder;

    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     */
    protected function build(): string
    {
        return $this->builder->applyLimit(parent::build(), $this->getLimit(), $this->offset);
    }

    /**
     *
     * Refuses to make the SELECT a `FOR UPDATE`.
     *
     * SQL Server has no `FOR UPDATE` on a plain SELECT; it takes locks through
     * table hints such as `WITH (UPDLOCK)` instead, written in the FROM.
     * Rendering it anyway would hand back SQL that cannot run.
     *
     * @param bool $enable Passing false is allowed, and does nothing.
     *
     * @return $this
     *
     * @throws BadMethodCallException when enabling it.
     *
     */
    public function forUpdate(bool $enable = true): static
    {
        if ($enable) {
            throw new BadMethodCallException(
                get_class($this) . " doesn't support FOR UPDATE"
            );
        }

        return parent::forUpdate(false);
    }
}
