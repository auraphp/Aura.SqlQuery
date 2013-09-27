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
 * An object for Sqlite INSERT queries.
 *
 * @package Aura.Sql
 *
 */
class Insert extends AbstractQuery
{
    use Traits\InsertTrait;
    use Traits\SqliteFlagsTrait;
    
    protected function build()
    {
        return 'INSERT' . $this->buildFlags() . " INTO {$this->into}"
             . $this->buildValuesForInsert()
             . PHP_EOL;
    }
}
