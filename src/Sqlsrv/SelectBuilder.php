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
    use NoRecursiveKeywordTrait;

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
    public function buildLimitOffset(int $limit, int $offset): string
    {
        return '';
    }

    /**
     *
     * Modify the statement applying limit/offset equivalent portions to it.
     *
     * @param string $stm The SQL statement.
     *
     * @param int $limit The LIMIT value.
     *
     * @param int $offset The OFFSET value.
     *
     * @return string
     *
     */
    public function applyLimit(string $stm, int $limit, int $offset): string
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

        // offset, with or without a limit. must have an ORDER clause to work;
        // OFFSET is a sub-clause of the ORDER clause. cannot use FETCH without
        // OFFSET, and FETCH NEXT 0 ROWS is an error rather than "no limit",
        // so it is left off when there is none.
        $stm .= PHP_EOL . "OFFSET {$offset} ROWS";
        if ($limit) {
            $stm .= " FETCH NEXT {$limit} ROWS ONLY";
        }
        return $stm;
    }
}
