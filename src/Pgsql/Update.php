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
class Update extends AbstractQuery
{
    use Traits\UpdateTrait;
    use Traits\ReturningTrait;
    
    protected function build()
    {
        return "UPDATE {$this->table}" . PHP_EOL
             . $this->buildValuesForUpdate()
             . $this->buildWhere()
             . $this->buildReturning()
             . PHP_EOL;
    }
}
