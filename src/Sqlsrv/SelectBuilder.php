<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

/**
 *
 * An object for Sqlsrv SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class SelectBuilder extends Common\SelectBuilder
{
    /**
     *
     * Override so that LIMIT equivalent will be applied by applyLimit().
     *
     * @param int $limit Ignored.
     *
     * @param int $offset Ignored.
     *
     * @see build()
     *
     * @see applyLimit()
     *
     */
    public function buildLimitOffset($limit, $offset)
    {
        return '';
    }

    /**
     * Applies SQL Server-compatible limit and offset clauses to a SQL statement.
     *
     * @param string $stm The SQL statement to modify.
     * @param int $limit The maximum number of rows to return.
     * @param int $offset The number of rows to skip.
     * @return string The statement with the applicable limit and offset clauses.
     */
    public function applyLimit($stm, $limit, $offset)
    {
        if (! $limit && ! $offset) {
            return $stm; // no limit or offset
        }

        // limit but no offset?
        if ($limit && ! $offset) {
            // use TOP in place
            return preg_replace(
                '/^(SELECT( DISTINCT)?)/',
                "$1 TOP {$limit}",
                $stm
            );
        }

        // both limit and offset. must have an ORDER clause to work; OFFSET is
        // a sub-clause of the ORDER clause. cannot use FETCH without OFFSET.
        return $stm . PHP_EOL . "OFFSET {$offset} ROWS "
                    . "FETCH NEXT {$limit} ROWS ONLY";
    }

    /**
     * Determines whether SQL Server CTEs may include the `RECURSIVE` keyword.
     *
     * @return bool `true` if the keyword is supported, `false` otherwise.
     */
    protected function allowsRecursiveKeyword()
    {
        return false;
    }
}
