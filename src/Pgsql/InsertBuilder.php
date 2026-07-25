<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

/**
 *
 * INSERT builder for Postgres.
 *
 * @package Aura.SqlQuery
 *
 */
class InsertBuilder extends Common\InsertBuilder
{
    use BuildReturningTrait;

    /**
     *
     * Builds the `ON CONFLICT DO NOTHING` clause of the statement.
     *
     * @param bool $ignore Whether the clause is enabled.
     *
     * @return string
     *
     */
    public function buildIgnore($ignore)
    {
        if (! $ignore) {
            return ''; // not applicable
        }

        return PHP_EOL . 'ON CONFLICT DO NOTHING';
    }
}
