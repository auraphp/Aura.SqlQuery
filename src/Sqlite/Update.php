<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;

/**
 *
 * An object for Sqlite UPDATE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Update extends Common\Update implements Common\OrderByInterface, Common\LimitOffsetInterface
{
    use Common\LimitOffsetTrait;
    use OrConflictTrait;

    /**
     *
     * Builds the statement.
     *
     * @return string
     *
     */
    protected function build(): string
    {
        $this->assertOneOrConflictFlag();
        return parent::build()
            . $this->builder->buildLimitOffset($this->getLimit(), $this->offset);
    }

    /**
     *
     * Adds or removes OR ABORT flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orAbort(bool $enable = true): static
    {
        $this->setFlag('OR ABORT', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR FAIL flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orFail(bool $enable = true): static
    {
        $this->setFlag('OR FAIL', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR IGNORE flag.
     *
     * @deprecated use ignore instead
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orIgnore(bool $enable = true): static
    {
        $this->ignore($enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR IGNORE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function ignore(bool $enable = true): static
    {
        $this->setFlag('OR IGNORE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR REPLACE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orReplace(bool $enable = true): static
    {
        $this->setFlag('OR REPLACE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR ROLLBACK flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orRollback(bool $enable = true): static
    {
        $this->setFlag('OR ROLLBACK', $enable);
        return $this;
    }

    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return $this
     *
     */
    public function orderBy(array $spec): static
    {
        return $this->addOrderBy($spec);
    }
}
