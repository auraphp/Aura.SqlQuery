<?php
/**
 * 
 * This file is part of Aura for PHP.
 * 
 * @package Aura.Sql
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Sql\Query;

/**
 * 
 * A trait for adding LIMIT.
 * 
 * @package Aura.Sql
 * 
 */
trait LimitTrait
{
    /**
     *
     * The number of rows to select
     *
     * @var int
     *
     */
    protected $limit = 0;

    /**
     *
     * Sets a limit count on the query.
     *
     * @param int $limit The number of rows to select.
     *
     * @return $this
     *
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }
    
    /**
     * 
     * Modifies an SQL string **in place** to add a `LIMIT ... OFFSET` clause.
     * 
     * @param string $text The SQL string.
     * 
     * @param int $count The number of rows to return.
     * 
     * @param int $offset Skip this many rows first.
     * 
     * @return void
     * 
     */
    protected function getLimitClause()
    {
        if ($this->limit) {
            return "LIMIT {$this->limit}" . PHP_EOL;
        }
    }
}
