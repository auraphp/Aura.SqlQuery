<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

/**
 *
 * An interface for UPSERT (ON CONFLICT DO UPDATE) queries.
 *
 * @package Aura.SqlQuery
 *
 */
interface OnConflictUpdateInterface
{
    /**
     *
     * Sets the conflict target column(s) or constraint name.
     *
     * @param string|array<array-key, string> $target The conflict target
     * column(s) or constraint name.
     *
     * @return $this
     *
     */
    public function onConflict($target);

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
    public function doUpdateCol($col, ...$value);

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
    public function doUpdateCols(array $cols);

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
    public function doUpdate($col, $value);

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
    public function doUpdateWhere($condition, ...$bind);
}
