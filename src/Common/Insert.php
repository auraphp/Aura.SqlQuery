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
use Aura\SqlQuery\Exception\InvalidArgumentException;

/**
 *
 * An object for INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Insert extends AbstractDmlQuery implements InsertInterface
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
     * The table to insert into (quoted).
     *
     * @var string
     *
     */
    protected $into;

    /**
     *
     * The table to insert into (raw, for last-insert-id use).
     *
     * @var string
     *
     */
    protected $into_raw;

    /**
     *
     * A map of fully-qualified `table.column` names to last-insert-id names.
     * This is used to look up the right last-insert-id name for a given table
     * and column. Generally useful only for extended tables in Postgres.
     *
     * @var array<string, string>|null
     *
     */
    protected $last_insert_id_names;

    /**
     *
     * The current row-number we are adding column values for. This comes into
     * play only with bulk inserts.
     *
     * @var int
     *
     */
    protected $row = 0;

    /**
     *
     * A collection of `$col_values` for previous rows in bulk inserts.
     *
     * @var array<int, array<string, string>>
     *
     */
    protected $col_values_bulk = [];

    /**
     *
     * A collection of `$bind_values` for previous rows in bulk inserts.
     *
     * @var array<string, mixed>
     *
     */
    protected $bind_values_bulk = [];

    /**
     *
     * Which banked bulk names are taken, as `<name>_<row>` => 'bulk_col'.
     * The banked values live outside `$bind_values` until getBindValues()
     * merges them, so `$bind_sources` cannot record them and this stands in
     * for it.
     *
     * @var array<string, string>
     *
     */
    protected $bind_sources_bulk = [];

    /**
     *
     * The order in which columns will be bulk-inserted; this is taken from the
     * very first inserted row.
     *
     * @var list<string>
     *
     */
    protected $col_order = [];

    /**
     *
     * Sets the map of fully-qualified `table.column` names to last-insert-id
     * names. Generally useful only for extended tables in Postgres.
     *
     * @param array<string, string> $last_insert_id_names The list of ID
     * names.
     *
     * @return void
     *
     */
    public function setLastInsertIdNames(array $last_insert_id_names)
    {
        $this->last_insert_id_names = $last_insert_id_names;
    }

    /**
     *
     * Sets the table to insert into.
     *
     * @param string $into The table to insert into.
     *
     * @return $this
     *
     */
    public function into(string $into)
    {
        $this->into_raw = $into;
        $this->into = $this->quoter->quoteName($into);
        return $this;
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
        $stm = 'INSERT'
            . $this->builder->buildFlags($this->flags)
            . $this->builder->buildInto($this->into);

        if ($this->row) {
            $this->finishRow();
            $stm .= $this->builder->buildValuesForBulkInsert($this->col_order, $this->col_values_bulk);
        } else {
            $stm .= $this->builder->buildValuesForInsert($this->col_values);
        }

        return $stm;
    }

    /**
     *
     * Returns the proper name for passing to `PDO::lastInsertId()`.
     *
     * @param string $col The last insert ID column.
     *
     * @return mixed Normally null, since most drivers do not need a name;
     * alternatively, a string from `$last_insert_id_names`.
     *
     */
    public function getLastInsertIdName(string $col)
    {
        $key = $this->into_raw . '.' . $col;
        if (isset($this->last_insert_id_names[$key])) {
            return $this->last_insert_id_names[$key];
        }
    }

    /**
     *
     * Sets one column value placeholder; if an optional second parameter is
     * passed, that value is bound to the placeholder.
     *
     * @param string $col The column name.
     *
     * @param mixed ...$value Optional: a value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function col(string $col, mixed ...$value)
    {
        return $this->addCol($col, ...$value);
    }

    /**
     *
     * Sets multiple column value placeholders. If an element is a key-value
     * pair, the key is treated as the column name and the value is bound to
     * that column.
     *
     * @param array<int|string, mixed> $cols A list of column names,
     * optionally as key-value pairs where the key is a column name and the
     * value is a bind value for that column.
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
     * @param string $col   The column name.
     *
     * @param string|null $value The column value expression.
     *
     * @return $this
     *
     */
    public function set(string $col, ?string $value)
    {
        return $this->setCol($col, $value);
    }

    /**
     *
     * Gets the values to bind to placeholders.
     *
     * @return array<int|string, mixed>
     *
     */
    public function getBindValues()
    {
        $values = parent::getBindValues();

        foreach ($this->bind_values_bulk as $name => $value) {
            // a hand bind overwrites the value wherever it lands, as it does
            // everywhere else, so it keeps the name against the banked value.
            // Nothing else can be holding one: a sourced bind under a banked
            // name throws, so an unclaimed name here was bound by hand.
            if (
                array_key_exists($name, $values)
                && ! isset($this->bind_sources[$name])
            ) {
                continue;
            }
            $values[$name] = $value;
        }

        return $values;
    }

    /**
     *
     * Adds multiple rows for bulk insert.
     *
     * @param array<array-key, array<int|string, mixed>> $rows An array of
     * rows, where each element is an array of column key-value pairs. The
     * values are bound to placeholders.
     *
     * @return $this
     *
     */
    public function addRows(array $rows)
    {
        foreach ($rows as $cols) {
            $this->addRow($cols);
        }
        if ($this->row > 1) {
            $this->finishRow();
        }
        return $this;
    }

    /**
     *
     * Add one row for bulk insert; increments the row counter and optionally
     * adds columns to the new row.
     *
     * When adding the first row, the counter is not incremented.
     *
     * After calling `addRow()`, you can further call `col()`, `cols()`, and
     * `set()` to work with the newly-added row. Calling `addRow()` again will
     * finish off the current row and start a new one.
     *
     * @param array<int|string, mixed> $cols An array of column key-value
     * pairs; the values are bound to placeholders.
     *
     * @return $this
     *
     */
    public function addRow(array $cols = [])
    {
        if (empty($this->col_values)) {
            return $this->cols($cols);
        }

        if (empty($this->col_order)) {
            $this->col_order = array_keys($this->col_values);
        }

        $this->finishRow();
        $this->row ++;
        $this->cols($cols);
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
    public function ignore(bool $enable = true)
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
    public function orReplace(bool $enable = true)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support OR REPLACE flag");
    }

    /**
     *
     * Sets the conflict target column(s) or constraint name.
     *
     * @param string|array<array-key, string> $target
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function onConflict(string|array $target)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support ON CONFLICT clause");
    }

    /**
     *
     * Sets one column value placeholder for the UPDATE on conflict.
     *
     * @param string $col
     * @param mixed ...$value
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function doUpdateCol(string $col, mixed ...$value)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support DO UPDATE SET clause");
    }

    /**
     *
     * Sets multiple column value placeholders for the UPDATE on conflict.
     *
     * @param array<int|string, mixed> $cols
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function doUpdateCols(array $cols)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support DO UPDATE SET clause");
    }

    /**
     *
     * Sets a column value directly for the UPDATE on conflict.
     *
     * @param string $col
     * @param string|null $value
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function doUpdate(string $col, ?string $value)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support DO UPDATE SET clause");
    }

    /**
     *
     * Adds a WHERE condition for the UPDATE on conflict.
     *
     * @param string $condition
     * @param array<int|string, mixed> ...$bind
     * @throws BadMethodCallException
     * @return static
     *
     */
    public function doUpdateWhere(string $condition, array ...$bind)
    {
        // override in child classes
        throw new BadMethodCallException(get_class($this) . " doesn't support ON CONFLICT ... WHERE clause");
    }

    /**
     *
     * Finishes off the current row in a bulk insert, collecting the bulk
     * values and resetting for the next row.
     *
     * Only the names this row banked are cleared. Clearing the whole of
     * bind_values would take the rest of the query's placeholders with it --
     * the upsert values and their WHERE conditions, which belong to the
     * statement as a whole rather than to any one row. build() finishes the
     * last row, so those names would go missing from a statement that still
     * spells them, and execute() would fail on the unbound placeholder.
     *
     * @return void
     *
     */
    protected function finishRow()
    {
        if (empty($this->col_values)) {
            return;
        }

        foreach ($this->col_order as $col) {
            $name = $this->finishCol($col);
            if ($name !== null) {
                unset($this->bind_values[$name], $this->bind_sources[$name]);
            }
        }

        $this->col_values = [];
    }

    /**
     *
     * Finishes off a single column of the current row in a bulk insert.
     *
     * @param string $col The column to finish off.
     *
     * @return string|null The placeholder name this row banked, which the
     * caller clears; null for a raw value, which never had one.
     *
     * @throws InvalidArgumentException on named column missing from row.
     *
     */
    protected function finishCol(string $col)
    {
        if (! array_key_exists($col, $this->col_values)) {
            throw new InvalidArgumentException("Column $col missing from row {$this->row}.");
        }

        // get the current col_value
        $value = $this->col_values[$col];

        // is it *not* a placeholder?
        if (! str_starts_with($value, ':')) {
            // copy the value as-is
            $this->col_values_bulk[$this->row][$col] = $value;
            return null;
        }

        // retain col_values in bulk with the row number appended
        $this->col_values_bulk[$this->row][$col] = "{$value}_{$this->row}";

        // the existing placeholder name without : or row number
        $name = substr($value, 1);

        // retain bind_value in bulk with new placeholder
        if (array_key_exists($name, $this->bind_values)) {
            $banked = "{$name}_{$this->row}";

            // the generated name is a claim on the flat bind array like any
            // other. If a clause got there first, banking over it would
            // discard that clause's value in the getBindValues() merge.
            //
            // A column of this same row is the exception: `a` in row 1 banks
            // `a_1`, which a sibling column literally named `a_1` is holding
            // at that moment. Both are row placeholders, and finishRow()
            // clears them -- the sibling banks itself as `a_1_1` -- so they
            // never meet in the merge.
            $prior = isset($this->bind_sources[$banked])
                ? $this->bind_sources[$banked]
                : null;

            if ($prior !== null && $prior !== 'col') {
                $this->throwCollision($banked, $prior, 'bulk_col');
            }

            $this->bind_values_bulk[$banked] = $this->bind_values[$name];
            $this->bind_sources_bulk[$banked] = 'bulk_col';
        }

        return $name;
    }

    /**
     *
     * Clears the banked bulk values along with the rest, so the names they
     * held are free to claim again. Leaving the banked sources behind would
     * refuse a name nothing is bound to any more.
     *
     * The rows themselves stay. `$col_values_bulk` is structure rather than
     * bound values -- the non-bulk path keeps `$col_values` the same way --
     * so the statement goes on spelling every placeholder with nothing bound
     * to it, which is what this method means everywhere else.
     *
     * @return $this
     *
     */
    public function resetBindValues()
    {
        $this->bind_values_bulk = [];
        $this->bind_sources_bulk = [];
        return parent::resetBindValues();
    }

    /**
     *
     * Adds the banked bulk names to the collision check. They are not in
     * `$bind_values`, so the inherited check cannot see them, yet they win
     * the merge in getBindValues() and would silently discard whatever a
     * clause bound under the same name.
     *
     * {@inheritdoc}
     *
     */
    protected function bindValueFrom(int|string $name, mixed $value, ?string $source)
    {
        // 'col' is exempt for the same reason it is in finishCol(): a later
        // row's column may be named `a_1` while `a` in row 1 has banked that
        // name, and both are cleared before the merge.
        if (
            $source !== null
            && $source !== 'col'
            && isset($this->bind_sources_bulk[$name])
        ) {
            $this->throwCollision($name, $this->bind_sources_bulk[$name], $source);
        }

        return parent::bindValueFrom($name, $value, $source);
    }
}
