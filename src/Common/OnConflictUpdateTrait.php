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
 * A trait implementing OnConflictUpdateInterface.
 *
 * @package Aura.SqlQuery
 *
 */
trait OnConflictUpdateTrait
{
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
            $cols = array();
            foreach ($target as $col) {
                $cols[] = $this->quoter->quoteName($col);
            }
            $this->conflict_target = '(' . implode(', ', $cols) . ')';
        } else {
            $target = trim($target);
            if (stripos($target, 'ON CONSTRAINT ') === 0) {
                $constraint = trim(substr($target, 14));
                $this->conflict_target = 'ON CONSTRAINT ' . $this->quoter->quoteName($constraint);
            } else {
                $this->conflict_target = '(' . $this->quoter->quoteName($target) . ')';
            }
        }
        return $this;
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
            $this->bindValue($bind, $value[0]);
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
     * @param string $value The column value expression.
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
