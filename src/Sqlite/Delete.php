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
 * An object for Sqlite DELETE queries.
 *
 * @package Aura.Sql
 *
 */
class Delete extends AbstractQuery
{
    use Traits\DeleteTrait;
    use Traits\OrderByTrait;
    use Traits\LimitOffsetTrait;
    
    protected function build()
    {
        return 'DELETE' . $this->buildFlags() . " FROM {$this->from}" . PHP_EOL
             . $this->buildWhere()
             . $this->buildOrderBy()
             . $this->buildLimitOffset()
             . PHP_EOL;
    }
}
