<?php
namespace Aura\Sql_Query;

interface UpdateInterface extends QueryInterface
{
    /**
     *
     * Sets the table to update.
     *
     * @param string $table The table to update.
     *
     * @return $this
     *
     */
    public function table($table);
    
    /**
     * 
     * Sets one column value placeholder.
     * 
     * @param string $col The column name.
     * 
     * @return $this
     * 
     */
    public function col($col);

    /**
     * 
     * Sets multiple column value placeholders.
     * 
     * @param array $cols A list of column names.
     * 
     * @return $this
     * 
     */
    public function cols(array $cols);

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
    public function set($col, $value);

    /**
     * 
     * Adds a WHERE condition to the query by AND.
     * 
     * @param string $cond The WHERE condition.
     * 
     * @return $this
     * 
     */
    public function where($cond);

    /**
     * 
     * Adds a WHERE condition to the query by OR; otherwise identical to 
     * `where()`.
     * 
     * @param string $cond The WHERE condition.
     * 
     * @return $this
     * 
     * @see where()
     * 
     */
    public function orWhere($cond);
}
