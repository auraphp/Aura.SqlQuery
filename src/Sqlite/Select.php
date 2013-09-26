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
 * An object for Sqlite SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractSqlite
{
    use Traits\SelectTrait;
}
