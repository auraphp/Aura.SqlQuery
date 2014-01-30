<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql_Query
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql_Query\Pgsql;

use Aura\Sql_Query\Common;

/**
 *
 * An object for PgSQL UPDATE queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Delete extends Common\Delete implements Common\ReturningInterface
{
    /**
     *
     * Adds returning columns to the query.
     *
     * Multiple calls to returning() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array $cols The column(s) to add to the query.
     *
     * @return $this
     *
     */
    public function returning(array $cols)
    {
        return $this->addReturning($cols);
    }
}
