<?php
/**
 * 
 * This file is part of Aura for PHP.
 * 
 * @package Aura.Sql_Query
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Sql_Query\Traits;

/**
 * 
 * A trait for adding column value placeholders and setting values directly.
 * 
 * @package Aura.Sql_Query
 * 
 */
trait ValuesTrait
{
    /**
     * 
     * The column values for the query; the key is the column name and the
     * value is the column value.
     * 
     * @param array
     * 
     */
    protected $values;

    /**
     * 
     * Sets one column value placeholder; if an optional second parameter is
     * passed, that value is bound to the placeholder.
     * 
     * @param string $col The column name.
     * 
     * @param mixed $val Optional: a value to bind to the placeholder.
     * 
     * @return $this
     * 
     */
    public function col($col)
    {
        $key = $this->quoteName($col);
        $this->values[$key] = ":$col";
        $args = func_get_args();
        if (count($args) > 1) {
            $this->bindValue($col, $args[1]);
        }
        return $this;
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
        foreach ($cols as $key => $val) {
            if (is_int($key)) {
                // integer key means the value is the column name
                $this->col($val);
            } else {
                // the key is the column name and the value is a value to
                // be bound to that column
                $this->col($key, $val);
            }
        }
        return $this;
    }

    /**
     * 
     * Sets a column value directly; the value will not be escaped, although
     * fully-qualified identifiers in the value will be quoted.
     * 
     * @param string $col The column name.
     * 
     * @param string $value The column value expression.
     * 
     * @return $this
     * 
     */
    public function set($col, $value)
    {
        if ($value === null) {
            $value = 'NULL';
        }

        $key = $this->quoteName($col);
        $value = $this->quoteNamesIn($value);
        $this->values[$key] = $value;
        return $this;
    }
    
    /**
     * 
     * Appends the insert columns and values to the statement.
     * 
     * @return null
     * 
     */
    protected function buildValuesForInsert()
    {
        $this->stm .= ' ('
                    . $this->indentCsv(array_keys($this->values))
                    . PHP_EOL . ') VALUES ('
                    . $this->indentCsv(array_values($this->values))
                    . PHP_EOL . ')';
    }
    
    /**
     * 
     * Appends the update columns and values to the statement.
     * 
     * @return null
     * 
     */
    protected function buildValuesForUpdate()
    {
        $values = [];
        foreach ($this->values as $col => $value) {
            $values[] = "{$col} = {$value}";
        }
        $this->stm .= PHP_EOL . 'SET' . $this->indentCsv($values);
    }
}
