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
use Aura\SqlQuery\Exception;

/**
 *
 * An object for MySQL INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Insert extends Common\Insert
{
    /**
     *
     * A builder for the query.
     *
     * @var InsertBuilder
     *
     */
    protected $builder;

    /**
     *
     * if true, use a REPLACE sql command instead of INSERT
     *
     * @var bool
     *
     */
    protected $use_replace = false;

    /**
     *
     * Column values for ON DUPLICATE KEY UPDATE section of query; the key is
     * the column name and the value is the column value.
     *
     * @var array<string, string>|null
     *
     */
    protected $col_on_update_values;

    /**
     *
     * Use a REPLACE statement.
     * Matches similar orReplace() function for Sqlite
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orReplace($enable = true)
    {
        $this->use_replace = $enable;
        return $this;
    }

    /**
     *
     * Adds or removes HIGH_PRIORITY flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function highPriority($enable = true)
    {
        $this->setFlag('HIGH_PRIORITY', $enable);
        return $this;
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
    public function lowPriority($enable = true)
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
    public function ignore($enable = true)
    {
        $this->setFlag('IGNORE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes DELAYED flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function delayed($enable = true)
    {
        $this->setFlag('DELAYED', $enable);
        return $this;
    }

    /**
     *
     * Sets one column value placeholder in ON DUPLICATE KEY UPDATE section;
     * if an optional second parameter is passed, that value is bound to the
     * placeholder.
     *
     * @param string $col The column name.
     *
     * @param mixed ...$value Optional: a value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function onDuplicateKeyUpdateCol($col, ...$value)
    {
        $key = $this->quoter->quoteName($col);
        $bind = $col . '__on_duplicate_key';
        $this->col_on_update_values[$key] = ":$bind";
        if (count($value) > 0) {
            $this->bindValueFrom($bind, $value[0], 'duplicate_key');
        }
        return $this;
    }

    /**
     *
     * Sets multiple column value placeholders in ON DUPLICATE KEY UPDATE
     * section. If an element is a key-value pair, the key is treated as the
     * column name and the value is bound to that column.
     *
     * @param array<int|string, mixed> $cols A list of column names,
     * optionally as key-value pairs where the key is a column name and the
     * value is a bind value for that column.
     *
     * @return $this
     *
     */
    public function onDuplicateKeyUpdateCols(array $cols)
    {
        foreach ($cols as $key => $val) {
            if (is_int($key)) {
                // integer key means the value is the column name
                $this->onDuplicateKeyUpdateCol($val);
            } else {
                // the key is the column name and the value is a value to
                // be bound to that column
                $this->onDuplicateKeyUpdateCol($key, $val);
            }
        }
        return $this;
    }

    /**
     *
     * Sets a column value directly in ON DUPLICATE KEY UPDATE section; the
     * value will not be escaped, although fully-qualified identifiers in the
     * value will be quoted.
     *
     * @param string $col The column name.
     *
     * @param string|null $value The column value expression.
     *
     * @return $this
     *
     */
    public function onDuplicateKeyUpdate($col, $value)
    {
        if ($value === null) {
            $value = 'NULL';
        }

        $key = $this->quoter->quoteName($col);
        $value = $this->quoter->quoteNamesIn($value);
        $this->col_on_update_values[$key] = $value;
        return $this;
    }

    /**
     *
     * The flags REPLACE will not accept; the rest of the INSERT flags carry
     * over unchanged.
     *
     * @var string[]
     *
     */
    protected $replace_forbids_flags = ['HIGH_PRIORITY', 'IGNORE'];

    /**
     *
     * Throws if a flag was set that REPLACE does not accept; rewriting the
     * INSERT keyword leaves the flags in place, so the statement would reach
     * the database as a parse error.
     *
     * @return void
     *
     * @throws Exception\LogicException
     *
     */
    protected function assertReplaceFlags()
    {
        foreach ($this->replace_forbids_flags as $flag) {
            if ($this->hasFlag($flag)) {
                throw new Exception\LogicException(
                    "A REPLACE cannot take the $flag flag."
                );
            }
        }
    }

    /**
     *
     * The priority modifiers; a statement takes at most one of them. REPLACE
     * accepts a narrower set than INSERT, which assertReplaceFlags() covers.
     *
     * @var string[]
     *
     */
    protected $priority_flags = ['LOW_PRIORITY', 'HIGH_PRIORITY', 'DELAYED'];

    /**
     *
     * Throws if more than one priority modifier was set; they are
     * alternatives to each other, so MySQL rejects a statement carrying two.
     *
     * @return void
     *
     * @throws Exception\LogicException
     *
     */
    protected function assertOnePriorityFlag()
    {
        $set = [];
        foreach ($this->priority_flags as $flag) {
            if ($this->hasFlag($flag)) {
                $set[] = $flag;
            }
        }

        if (count($set) > 1) {
            throw new Exception\LogicException(
                'A statement takes only one priority modifier; got '
                . implode(' and ', $set) . '.'
            );
        }
    }

    /**
     *
     * MySQL is the one dialect here that does not take a WITH clause on
     * INSERT: it allows a CTE only inside the SELECT an `INSERT ... SELECT`
     * draws from, which this package does not build. Rendering the clause
     * anyway would produce a statement that can only fail at execute time,
     * and it would fail there with a bare syntax error naming the WITH the
     * caller wrote deliberately. So it is refused here instead.
     *
     * @param string $name The CTE name.
     *
     * @param string|Common\SelectInterface $spec The CTE specification.
     *
     * @param array<array-key, string> $cols Optional column list for the CTE.
     *
     * @return $this
     *
     * @throws Exception\BadMethodCallException always.
     *
     */
    public function with($name, $spec, array $cols = [])
    {
        throw new Exception\BadMethodCallException(
            'MySQL does not allow a WITH clause on INSERT.'
        );
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
        if ($this->use_replace && ! empty($this->col_on_update_values)) {
            throw new Exception\LogicException(
                'A REPLACE statement cannot take an ON DUPLICATE KEY UPDATE clause.'
            );
        }

        $stm = parent::build();

        $this->assertOnePriorityFlag();

        if ($this->use_replace) {
            $this->assertReplaceFlags();
            // change INSERT to REPLACE
            $stm = 'REPLACE' . substr($stm, 6);
        }

        return $stm
            . $this->builder->buildValuesForUpdateOnDuplicateKey($this->col_on_update_values);
    }
}
