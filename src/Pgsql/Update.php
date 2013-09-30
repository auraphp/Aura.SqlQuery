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
 * An object for PgSQL UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends Common\Update
{
    use Traits\ReturningTrait;
    
    protected function build()
    {
        $this->stm = rtrim(parent::build()) . PHP_EOL;
        $this->stm .= $this->buildReturning();
        return $this->stm . PHP_EOL;
    }
}
