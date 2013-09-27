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
use Aura\Sql_Query\SelectInterface;

/**
 *
 * An object for Sqlite SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractQuery implements SelectInterface
{
    use Traits\SelectTrait;
}
