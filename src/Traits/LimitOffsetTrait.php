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
 * A trait for adding LIMIT and OFFSET.
 * 
 * @package Aura.Sql_Query
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
    
    protected function buildLimit()
    {
        if ($this->limit) {
            $this->stm .= PHP_EOL . "LIMIT {$this->limit}";
            if ($this->offset) {
                $this->stm .= " OFFSET {$this->offset}";
            }
        }
    }
}
