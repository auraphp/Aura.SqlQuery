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
 * DELETE builder for SQLite.
 *
 * @package Aura.SqlQuery
 *
 */
class DeleteBuilder extends Common\DeleteBuilder
{
    /**
     *
     * SQLite has no OFFSET without LIMIT; a negative LIMIT means no
     * limit.
     *
     */
    protected const ?string NO_LIMIT = '-1';
}
