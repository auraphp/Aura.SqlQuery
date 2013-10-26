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
class Delete extends Common\Delete
{
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
        parent::build();
        $this->buildReturning();
        return $this->stm;
    }
}
