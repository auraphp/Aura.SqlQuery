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
namespace Aura\Sql_Query\Mysql;

use Aura\Sql_Query\Common;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for MySQL UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends Common\Update
{
    use Traits\LimitTrait;
    use Traits\OrderByTrait;
    
    /**
     * 
     * Converts this query object to a string.
     * 
     * @return string
     * 
     */
    protected function build()
    {
        $this->stm = rtrim(parent::build()) . PHP_EOL;
        $this->stm .= $this->buildOrderBy();
        $this->stm .= $this->buildLimit();
        return $this->stm . PHP_EOL;
    }

    /**
     *
     * Adds or removes LOW_PRIORITY flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function lowPriority($enable = true)
    {
        $this->setFlag('LOW_PRIORITY', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes IGNORE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function ignore($enable = true)
    {
        $this->setFlag('IGNORE', $enable);
        return $this;
    }
}
