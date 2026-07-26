<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;

/**
 *
 * INSERT builder for SQLite.
 *
 * @package Aura.SqlQuery
 *
 */
class InsertBuilder extends Common\InsertBuilder
{
    use Common\BuildOnConflictTrait;
}
