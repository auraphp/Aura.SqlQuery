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

use Aura\Sql_Query\Common;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for PgSQL INSERT queries.
 *
 * @package Aura.Sql
 *
 */
class Insert extends Common\Insert
{
    use Traits\ReturningTrait;
    
    protected function build()
    {
        return parent::build()
             . $this->buildReturning()
             . PHP_EOL;
    }
}
