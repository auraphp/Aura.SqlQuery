<?php
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\QueryInterface;

interface InsertInterface extends QueryInterface
{
    /**
     *
     * Sets the table to insert into.
     *
     * @param string $into The table to insert into.
     *
     * @return $this
     *
     */
    public function into($into);
    
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
}
