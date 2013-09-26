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
namespace Aura\Sql\Query\Sqlite;

use Aura\Sql\Query\Traits;

/**
 *
 * An object for Sqlite DELETE queries.
 *
 * @package Aura.Sql
 *
 */
class Delete extends AbstractSqlite
{
    use Traits\DeleteTrait;
    use Traits\OrderByTrait;
    use Traits\LimitOffsetTrait;
    
    protected function build()
    {
        return 'DELETE' . $this->buildFlags() . " FROM {$this->from}"
             . $this->buildWhere()
             . $this->buildOrderBy()
             . $this->buildLimitOffset()
             . PHP_EOL;
    }
}
