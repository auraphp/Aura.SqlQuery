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
 * An object for PgSQL INSERT queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Insert extends Common\Insert
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
    
    /**
     * 
     * Returns the proper name for passing to `PDO::lastInsertId()`.
     * 
     * @param string $col The last insert ID column.
     * 
     * @return string The sequence name "{$into_table}_{$col}_seq".
     * 
     */
    public function getLastInsertIdName($col)
    {
        return "{$this->into}_{$col}_seq";
    }
}
