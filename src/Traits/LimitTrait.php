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
 * A trait for adding LIMIT.
 * 
 * @package Aura.Sql_Query
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
     * Appends the `LIMIT` clause to the statement.
     * 
     * @return null
     * 
     */
    protected function buildLimit()
    {
        if ($this->limit) {
            $this->stm .= PHP_EOL . "LIMIT {$this->limit}";
        }
    }
}
