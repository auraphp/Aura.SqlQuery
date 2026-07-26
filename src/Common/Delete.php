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

/**
 *
 * An object for DELETE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Delete extends AbstractDmlQuery implements DeleteInterface
{
    use WhereTrait;

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
     */
    public function from($from)
    {
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
