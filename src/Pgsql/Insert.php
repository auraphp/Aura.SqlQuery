<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

/**
 *
 * An object for PgSQL INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Insert extends Common\Insert implements ReturningInterface, Common\OnConflictUpdateInterface
{
    /**
     *
     * A builder for the query.
     *
     * @var InsertBuilder
     *
     */
    protected $builder;

    use ReturningTrait;
    use Common\OnConflictUpdateTrait;

    /**
     *
     * Whether to render the `ON CONFLICT DO NOTHING` clause.
     *
     * @var bool
     *
     */
    protected $ignore = false;

    /**
     *
     * Adds or removes the `ON CONFLICT DO NOTHING` clause, the Postgres
     * equivalent of the IGNORE flag on other databases.
     *
     * @param bool $enable Set or unset the clause (default true).
     *
     * @return $this
     *
     */
    public function ignore(bool $enable = true): static
    {
        $this->ignore = $enable;
        return $this;
    }

    /**
     *
     * Builds the statement.
     *
     * @return string
     *
     */
    protected function build(): string
    {
        return parent::build()
            . $this->builder->buildOnConflict(
                $this->conflict_target,
                $this->conflict_update_values,
                $this->conflict_where,
                $this->ignore
            )
            . $this->builder->buildReturning($this->returning);
    }

    /**
     *
     * Returns the proper name for passing to `PDO::lastInsertId()`.
     *
     * @param string $col The last insert ID column.
     *
     * @return string The sequence name "{$into_table}_{$col}_seq", or the
     * value from `$last_insert_id_names`.
     *
     */
    public function getLastInsertIdName(string $col): string
    {
        $name = parent::getLastInsertIdName($col);
        if (! $name) {
            $name = "{$this->into_raw}_{$col}_seq";
        }
        return $name;
    }
}
