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

use Aura\Sql\Query\AbstractQuery;
use Aura\Sql\Query\Traits;

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
        return 'UPDATE' . $this->buildFlags() . " {$this->into}"
             . $this->buildValuesForUpdate()
             . $this->buildOrderBy()
             . $this->buildLimitOffset()
             . PHP_EOL;
    }
}
