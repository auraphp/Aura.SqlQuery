<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception\BadMethodCallException;
use Aura\SqlQuery\Exception\InvalidArgumentException;

/**
 *
 * A trait implementing OnConflictUpdateInterface.
 *
 * @package Aura.SqlQuery
 *
 */
trait OnConflictUpdateTrait
{
    use TableListTrait;

    /**
     *
     * Whether this dialect accepts `ON CONSTRAINT <name>` as the conflict
     * target; SQLite takes only a column list, so it overrides this.
     *
     * @return bool
     *
     */
    protected function allowsConstraintTarget(): bool
    {
        return true;
    }

    /**
     *
     * The conflict target column(s) or constraint name, rendered ready for
     * the clause.
     *
     * @var string|null
     *
     */
    protected $conflict_target;

    /**
     *
     * Column values for UPDATE on conflict.
     *
     * @var array<string, string>
     *
     */
    protected $conflict_update_values = [];

    /**
     *
     * WHERE conditions for UPDATE on conflict.
     *
     * @var list<string>
     *
     */
    protected $conflict_where = [];

    /**
     *
     * Sets the conflict target column(s) or constraint name.
     *
     * @param string|array<array-key, string> $target The conflict target:
     * a column name, a list of them (as an array or a comma-separated
     * string), or `ON CONSTRAINT <name>`.
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the target is empty,
     * or is an index expression rather than a column name.
     *
     */
    public function onConflict(string|array $target): static
    {
        if (is_array($target)) {
            $cols = [];
            foreach ($target as $col) {
                $cols[] = $this->quoter->quoteName($this->assertConflictName($col));
            }
            if (empty($cols)) {
                throw new InvalidArgumentException(
                    'onConflict() requires a column name or constraint.'
                );
            }
            $this->conflict_target = '(' . implode(', ', $cols) . ')';
            return $this;
        }

        $target = trim($target);

        // any run of whitespace separates the keywords, and the keyword with
        // nothing after it is no target at all -- matched here, or it would
        // go on to be quoted as a column named "ON CONSTRAINT"
        if (preg_match('/^ON\s+CONSTRAINT(?:\s+(.*))?$/is', $target, $matches)) {
            $constraint = $this->assertConflictName($matches[1] ?? '');
            if (! $this->allowsConstraintTarget()) {
                throw new BadMethodCallException(
                    get_class($this)
                    . " doesn't support a constraint-name conflict target"
                );
            }
            $this->conflict_target = 'ON CONSTRAINT ' . $this->quoter->quoteName($constraint);
            return $this;
        }

        // a comma-separated list is the array form written out; quoting it
        // whole would give `("a," "b")`, which names no column
        $names = $this->splitNamesList($target);
        if (count($names) > 1) {
            return $this->onConflict($names);
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
     * @throws InvalidArgumentException
     *
     */
    protected function assertConflictName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException(
                'onConflict() requires a column name or constraint.'
            );
        }

        // an index expression cannot be quoted as a name, and needs a
        // parenthesis of its own in the target; name the unique index's
        // constraint instead, or its columns
        if (str_contains($name, '(')) {
            throw new InvalidArgumentException(
                "onConflict() takes column names or a constraint, not the "
                . "expression '{$name}'."
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
     * @param mixed ...$value Optional: a value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function doUpdateCol(string $col, mixed ...$value): static
    {
        return $this->atomically(function () use ($col, $value): void {
            $key = $this->quoter->quoteName($col);
            if (count($value) > 0) {
                $bind = $this->placeholderFor($col) . '__on_conflict';
                $this->conflict_update_values[$key] = ":$bind";
                $this->bindValueFrom($bind, $value[0], 'conflict');
            } else {
                $this->conflict_update_values[$key] = 'excluded.' . $key;
            }
        });
    }

    /**
     *
     * Sets multiple column value placeholders for the UPDATE on conflict. If an element
     * is a key-value pair, the key is treated as the column name and the value is bound
     * to that column.
     *
     * @param array<int|string, mixed> $cols A list of column names,
     * optionally as key-value pairs where the key is a column name and the
     * value is a bind value for that column.
     *
     * @return $this
     *
     */
    public function doUpdateCols(array $cols): static
    {
        return $this->atomically(function () use ($cols): void {
            foreach ($cols as $key => $val) {
                if (is_int($key)) {
                    $this->doUpdateCol($val);
                } else {
                    $this->doUpdateCol($key, $val);
                }
            }
        });
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
    public function doUpdate(string $col, ?string $value): static
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
     * @param array<int|string, mixed> ...$bind Optional: values to bind to
     * the condition.
     *
     * @return $this
     *
     */
    public function doUpdateWhere(string $condition, array ...$bind): static
    {
        return $this->atomically(function () use ($condition, $bind): void {
            $condition = $this->quoter->quoteNamesIn($condition);
            if (count($bind) > 0) {
                $condition = $this->rebuildCondAndBindValues($condition, $bind[0]);
            }

            if ($this->conflict_where) {
                $this->conflict_where[] = "AND $condition";
            } else {
                $this->conflict_where[] = $condition;
            }
        });
    }
}
