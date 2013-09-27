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
namespace Aura\Sql_Query\Pgsql;

use Aura\Sql_Query\AbstractQuery;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for PgSQL UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Delete extends AbstractQuery
{
    use Traits\DeleteTrait;
    use Traits\ReturningTrait;
    
    /**
     * 
     * Converts this query object to a string.
     * 
     * @return string
     * 
     */
    protected function build()
    {
        return "DELETE FROM {$this->from}" . PHP_EOL
             . $this->buildWhere()
             . $this->buildReturning()
             . PHP_EOL;
    }
}
