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
namespace Aura\Sql_Query\Sqlite;

use Aura\Sql_Query\AbstractQuery;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for Sqlite UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends AbstractQuery
{
    use Traits\UpdateTrait;
    use Traits\SqliteFlagsTrait;
    use Traits\LimitOffsetTrait;
    use Traits\OrderByTrait;
    
    protected function build()
    {
        return 'UPDATE' . $this->buildFlags() . " {$this->table}" . PHP_EOL
             . $this->buildValuesForUpdate()
             . $this->buildWhere()
             . $this->buildOrderBy()
             . $this->buildLimitOffset()
             . PHP_EOL;
    }
}
