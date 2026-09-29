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
use Aura\SqlQuery\Exception\BadMethodCallException;

/**
 *
 * An object for Sqlite SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Select extends Common\Select
{
    /**
     *
     * Refuses to make the SELECT a `FOR UPDATE`.
     *
     * SQLite has no row locks to take: a write locks the whole database, and
     * `FOR UPDATE` is a syntax error there.
     * Rendering it anyway would hand back SQL that cannot run.
     *
     * @param bool $enable Passing false is allowed, and does nothing.
     *
     * @return $this
     *
     * @throws BadMethodCallException when enabling it.
     *
     */
    public function forUpdate(bool $enable = true): static
    {
        if ($enable) {
            throw new BadMethodCallException(
                get_class($this) . " doesn't support FOR UPDATE"
            );
        }

        return parent::forUpdate(false);
    }
}
