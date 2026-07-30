<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception;

/**
 *
 * A trait implementing OnConflictUpdateInterface.
 *
 * @package Aura.SqlQuery
 *
 */
trait OnConflictUpdateTrait
{
    /**
     *
     * Whether this dialect accepts `ON CONSTRAINT <name>` as the conflict
     * target; SQLite takes only a column list, so it overrides this.
     *
     * @return bool
     *
     */
    protected function allowsConstraintTarget()
    {
        return true;
    }

    /**
     *
     * The conflict target column(s) or constraint name.
     *
     * @var string|array
     *
     */
    protected $conflict_target;

    /**
     *
     * Column values for UPDATE on conflict.
     *
     * @var array
     *
     */
    protected $conflict_update_values = [];

    /**
     *
     * WHERE conditions for UPDATE on conflict.
     *
     * @var array
     *
     */
    protected $conflict_where = [];

    /**
     *
     * Sets the conflict target column(s) or constraint name.
     *
     * @param string|array $target The conflict target column(s) or constraint name.
     *
     * @return $this
     *
     */
    public function onConflict($target)
    {
        if (is_array($target)) {
            $cols = [];
            foreach ($target as $col) {
                $cols[] = $this->quoter->quoteName($this->assertConflictName($col));
            }
            if (empty($cols)) {
                throw new Exception\InvalidArgumentException(
                    'onConflict() requires a column name or constraint.'
                );
            }
            $this->conflict_target = '(' . implode(', ', $cols) . ')';
            return $this;
        }

        $target = trim((string) $target);

        // the keyword with nothing after it; trimming has already taken the
        // space the prefix test below looks for, so catch it here or it goes
        // on to be quoted as a column named "ON CONSTRAINT"
        if (strcasecmp($target, 'ON CONSTRAINT') === 0) {
            throw new Exception\InvalidArgumentException(
                'onConflict() requires a column name or constraint.'
            );
        }

        if (stripos($target, 'ON CONSTRAINT ') === 0) {
            if (! $this->allowsConstraintTarget()) {
                throw new Exception\BadMethodCallException(
                    get_class($this)
                    . " doesn't support a constraint-name conflict target"
                );
            }
            $constraint = $this->assertConflictName(substr($target, 14));
            $this->conflict_target = 'ON CONSTRAINT ' . $this->quoter->quoteName($constraint);
            return $this;
        }

        $this->conflict_target = '(' . $this->quoter->quoteName($this->assertConflictName($target)) . ')';
        return $this;
    }

    /**
     *
     * Returns the trimmed name, rejecting an empty one: it would render as
     * `ON CONFLICT ()` or a zero-length quoted identifier, which the database
     * refuses to parse.
     *
     * @param string $name The column or constraint name.
     *
     * @return string
     *
     * @throws Exception\InvalidArgumentException
     *
     */
    protected function assertConflictName($name)
    {
        $name = trim((string) $name);

        if ($name === '') {
            throw new Exception\InvalidArgumentException(
                'onConflict() requires a column name or constraint.'
            );
        }

        return $name;
    }

    /**
     *
     * Sets one column value placeholder for the UPDATE on conflict; if an optional
     * second parameter is passed, that value is bound to the placeholder.
     *
     * @param string $col The column name.
     *
     * @param array $value Optional: a value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function doUpdateCol($col, ...$value)
    {
        $key = $this->quoter->quoteName($col);
        if (count($value) > 0) {
            $bind = $col . '__on_conflict';
            $this->conflict_update_values[$key] = ":$bind";
            $this->bindValueFrom($bind, $value[0], 'conflict');
        } else {
            $this->conflict_update_values[$key] = 'excluded.' . $key;
        }
        return $this;
    }

    /**
     *
     * Sets multiple column value placeholders for the UPDATE on conflict. If an element
     * is a key-value pair, the key is treated as the column name and the value is bound
     * to that column.
     *
     * @param array $cols A list of column names, optionally as key-value
     * pairs where the key is a column name and the value is a bind value for
     * that column.
     *
     * @return $this
     *
     */
    public function doUpdateCols(array $cols)
    {
        foreach ($cols as $key => $val) {
            if (is_int($key)) {
                $this->doUpdateCol($val);
            } else {
                $this->doUpdateCol($key, $val);
            }
        }
        return $this;
    }

    /**
     *
     * Sets a column value directly for the UPDATE on conflict; the value will not be
     * escaped, although fully-qualified identifiers in the value will be quoted.
     *
     * @param string $col The column name.
     *
     * @param string|null $value The column value expression.
     *
     * @return $this
     *
     */
    public function doUpdate($col, $value)
    {
        if ($value === null) {
            $value = 'NULL';
        }
        $key = $this->quoter->quoteName($col);
        $value = $this->quoter->quoteNamesIn($value);
        $this->conflict_update_values[$key] = $value;
        return $this;
    }

    /**
     *
     * Adds a WHERE condition for the UPDATE on conflict.
     *
     * @param string $condition The WHERE condition.
     *
     * @param array $bind Optional: values to bind to the condition.
     *
     * @return $this
     *
     */
    public function doUpdateWhere($condition, ...$bind)
    {
        $condition = $this->quoter->quoteNamesIn($condition);
        if (count($bind) > 0) {
            $condition = $this->rebuildCondAndBindValues($condition, $bind[0]);
        }

        if ($this->conflict_where) {
            $this->conflict_where[] = "AND $condition";
        } else {
            $this->conflict_where[] = $condition;
        }
        return $this;
    }
}
