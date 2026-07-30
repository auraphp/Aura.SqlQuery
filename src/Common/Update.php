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
 * An object for UPDATE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Update extends AbstractDmlQuery implements UpdateInterface
{
    /**
     *
     * A builder for the query.
     *
     * @var UpdateBuilder
     *
     */
    protected $builder;

    use TableListTrait;

    use WhereTrait;

    /**
     *
     * The table to update.
     *
     * @var string
     *
     */
    protected $table;

    /**
     *
     * Sets the table to update.
     *
     * @param string $table The table to update.
     *
     * @return $this
     *
     * @throws LogicException when the spec names more than one table.
     *
     */
    public function table($table)
    {
        $names = $this->splitNamesList($table);
        if (count($names) > 1) {
            throw new LogicException(
                "An UPDATE takes one table, and '{$table}' names several. "
                . 'Every database spells a multi-table update differently -- '
                . 'MySQL with a comma, PostgreSQL and SQLite with FROM, SQL '
                . 'Server with FROM and a JOIN -- so there is no portable '
                . 'form to build here. Match the other table with a '
                . 'sub-select in the WHERE clause instead.'
            );
        }

        $this->table = $this->quoter->quoteName($table);
        return $this;
    }

    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     * @throws LogicException when there are no columns to update.
     *
     */
    protected function build()
    {
        if (! $this->hasCols()) {
            throw new LogicException('No columns to update.');
        }

        return 'UPDATE'
            . $this->builder->buildFlags($this->flags)
            . $this->builder->buildTable($this->table)
            . $this->builder->buildValuesForUpdate($this->col_values)
            . $this->builder->buildWhere($this->where)
            . $this->builder->buildOrderBy($this->order_by);
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
     * Adds OR REPLACE flag depending on DB syntax.
     *
     * @param bool $enable Set or unset flag (default true).
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function orReplace($enable = true)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support OR REPLACE flag");
    }

    /**
     *
     * Sets one column value placeholder; if an optional second parameter is
     * passed, that value is bound to the placeholder.
     *
     * @param string $col The column name.
     *
     * @param array $value
     *
     * @return $this
     */
    public function col($col, ...$value)
    {
        return $this->addCol($col, ...$value);
    }

    /**
     *
     * Sets multiple column value placeholders. If an element is a key-value
     * pair, the key is treated as the column name and the value is bound to
     * that column.
     *
     * @param array $cols A list of column names, optionally as key-value
     * pairs where the key is a column name and the value is a bind value for
     * that column.
     *
     * @return $this
     *
     */
    public function cols(array $cols)
    {
        return $this->addCols($cols);
    }

    /**
     *
     * Sets a column value directly; the value will not be escaped, although
     * fully-qualified identifiers in the value will be quoted.
     *
     * @param string $col The column name.
     *
     * @param string|null $value The column value expression.
     *
     * @return $this
     *
     */
    public function set($col, $value)
    {
        return $this->setCol($col, $value);
    }
}
