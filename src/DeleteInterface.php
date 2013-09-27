<?php
namespace Aura\Sql_Query;

interface DeleteInterface extends QueryInterface
{
    /**
     *
     * Sets the table to delete from.
     *
     * @param string $table The table to delete from.
     *
     * @return $this
     *
     */
    public function from($from);
    
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
