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
 * A trait for adding RETURNING.
 * 
 * @package Aura.Sql_Query
 * 
 */
trait ReturningTrait
{
    /**
     *
     * The columns to be returned.
     *
     * @var array
     *
     */
    protected $returning = [];

    /**
     *
     * Adds returning columns to the query.
     *
     * Multiple calls to returning() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array $cols The column(s) to add to the query.
     *
     * @return $this
     *
     */
    public function returning(array $cols)
    {
        foreach ($cols as $col) {
            $this->returning[] = $this->quoteNamesIn($col);
        }
        return $this;
    }
    
    /**
     * 
     * Appends the `RETURNING` clause to the statement.
     * 
     * @return null
     * 
     */
    protected function buildReturning()
    {
        if ($this->returning) {
            $this->stm .= PHP_EOL . 'RETURNING' . $this->indentCsv($this->returning);
        }
    }
}
