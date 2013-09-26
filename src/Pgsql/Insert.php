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
namespace Aura\Sql\Query\Pgsql;

use Aura\Sql\Query\Traits;

/**
 *
 * An object for PgSQL INSERT queries.
 *
 * @package Aura.Sql
 *
 */
class Insert extends AbstractPgsql
{
    use Traits\InsertTrait;
    use Traits\ReturningTrait;
    
    protected function build()
    {
        return "INSERT INTO {$this->into}"
             . $this->buildValuesForInsert()
             . $this->buildReturning()
             . PHP_EOL;
    }
}
