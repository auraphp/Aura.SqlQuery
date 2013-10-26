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
namespace Aura\Sql_Query\Pgsql;

use Aura\Sql_Query\Common;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for PgSQL UPDATE queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Update extends Common\Update
{
    use Traits\ReturningTrait;
    
    /**
     * 
     * Builds this query object into a string.
     * 
     * @return string
     * 
     */
    protected function build()
    {
        parent::build();
        $this->buildReturning();
        return $this->stm;
    }
}
