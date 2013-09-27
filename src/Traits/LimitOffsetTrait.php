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
namespace Aura\Sql_Query\Traits;

/**
 * 
 * A trait for adding LIMIT and OFFSET.
 * 
 * @package Aura.Sql
 * 
 */
trait LimitOffsetTrait
{
    use LimitTrait;
    
    /**
     *
     * Return rows after this offset.
     *
     * @var int
     *
     */
    protected $offset = 0;

    /**
     *
     * Sets a limit offset on the query.
     *
     * @param int $offset Start returning after this many rows.
     *
     * @return $this
     *
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }
    
    protected function buildLimitOffset()
    {
        $limit = $this->buildLimit();
        if (! $limit) {
            // no limit, so can't do offset
            return;
        }
        
        if (! $this->offset) {
            // no offset, so return only limit
            return $limit;
        }
        
        // return limit and offset
        return rtrim($limit) . " OFFSET {$this->offset}" . PHP_EOL;
    }
}
