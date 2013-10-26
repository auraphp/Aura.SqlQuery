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
namespace Aura\Sql_Query\Sqlite;

use Aura\Sql_Query\Common;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for Sqlite DELETE queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Delete extends Common\Delete
{
    use Traits\LimitOffsetTrait;
    use Traits\OrderByTrait;
    
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
        $this->buildOrderBy();
        $this->buildLimit();
        return $this->stm;
    }
}
