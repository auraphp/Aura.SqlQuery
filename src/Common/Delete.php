<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractDmlQuery;
use Aura\SqlQuery\Exception\BadMethodCallException;
use Aura\SqlQuery\Exception\LogicException;

/**
 *
 * An object for DELETE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Delete extends AbstractDmlQuery implements DeleteInterface
{
    /**
     *
     * A builder for the query.
     *
     * @var DeleteBuilder
     *
     */
    protected $builder;

    use WhereTrait;
    use TableListTrait;

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
     * @param string $from The table to delete from.
     *
     * @return $this
     *
     * @throws LogicException when the spec names more than one table.
     *
     */
    public function from($from)
    {
        $names = $this->splitNamesList($from);
        if (count($names) > 1) {
            throw new LogicException(
                "A DELETE takes one table, and '{$from}' names several. "
                . '`DELETE FROM a, b` is not valid on any of the supported '
                . "databases -- MySQL's multi-table delete is a different "
                . 'shape again, `DELETE a, b FROM a JOIN b`. Match the other '
                . 'table with a sub-select in the WHERE clause instead.'
            );
        }

        $this->from = $this->quoter->quoteName($from);
        return $this;
    }

    /**
     *
     * Adds IGNORE flag depending on DB syntax.
     *
     * @param bool $enable Set or unset flag (default true).
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function ignore($enable = true)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support IGNORE flag");
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
            . $this->builder->buildFlags($this->flags)
            . $this->builder->buildFrom($this->from)
            . $this->builder->buildWhere($this->where)
            . $this->builder->buildOrderBy($this->order_by);
    }
}
