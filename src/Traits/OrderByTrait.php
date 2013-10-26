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
 * A trait for adding ORDER BY.
 * 
 * @package Aura.Sql_Query
 * 
 */
trait OrderByTrait
{
    /**
     *
     * ORDER BY these columns.
     *
     * @var array
     *
     */
    protected $order_by = [];

    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return $this
     *
     */
    public function orderBy(array $spec)
    {
        foreach ($spec as $col) {
            $this->order_by[] = $this->quoteNamesIn($col);
        }
        return $this;
    }

    /**
     * 
     * Returns the ORDER BY query clause.
     * 
     * @return string
     * 
     */
    protected function buildOrderBy()
    {
        if ($this->order_by) {
            $this->stm .= PHP_EOL . 'ORDER BY' . $this->indentCsv($this->order_by);
        }
    }
}
