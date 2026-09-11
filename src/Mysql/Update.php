<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

/**
 *
 * An object for MySQL UPDATE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Update extends Common\Update implements Common\OrderByInterface, Common\LimitInterface
{
    use Common\LimitTrait;

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
            . $this->builder->buildLimit($this->getLimit());
    }

    /**
     *
     * Adds or removes LOW_PRIORITY flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function lowPriority(bool $enable = true): static
    {
        $this->setFlag('LOW_PRIORITY', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes IGNORE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function ignore(bool $enable = true): static
    {
        $this->setFlag('IGNORE', $enable);
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
