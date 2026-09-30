<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;


/**
 *
 * Base builder for all query objects.
 *
 * @package Aura.SqlQuery
 *
 */
abstract class AbstractBuilder
{
    /**
     *
     * The LIMIT to write when there is an OFFSET but no limit, for a dialect
     * that cannot have one without the other; null for a dialect that can.
     *
     */
    protected const ?string NO_LIMIT = null;

    /**
     *
     * The order to write the modifier keywords in. MySQL fixes it for
     * INSERT and UPDATE, where the priority comes before IGNORE: it reads
     * `UPDATE LOW_PRIORITY IGNORE` and rejects `UPDATE IGNORE LOW_PRIORITY`.
     * SELECT and DELETE take their options in any order, so one order for
     * all of them costs nothing and makes the output independent of the
     * order the methods were called in. A flag not listed here keeps the
     * order it was set in, after these.
     *
     * @var list<string>
     *
     */
    protected const FLAG_ORDER = [
        'DISTINCT',
        'LOW_PRIORITY',
        'DELAYED',
        'HIGH_PRIORITY',
        'STRAIGHT_JOIN',
        'SQL_SMALL_RESULT',
        'SQL_BIG_RESULT',
        'SQL_BUFFER_RESULT',
        'SQL_CACHE',
        'SQL_NO_CACHE',
        'SQL_CALC_FOUND_ROWS',
        'QUICK',
        'IGNORE',
    ];

    /**
     *
     * Builds the flags as a space-separated string, in the order the
     * grammar requires rather than the order they were set in.
     *
     * @param array<string, true> $flags The flags to build.
     *
     * @return string
     *
     */
    public function buildFlags(array $flags): string
    {
        if (empty($flags)) {
            return ''; // not applicable
        }

        $rank = array_flip(static::FLAG_ORDER);
        $names = array_keys($flags);

        // usort() is stable, so unlisted flags keep the order they were set in
        usort($names, fn (string $a, string $b): int =>
            ($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX));

        return ' ' . implode(' ', $names);
    }

    /**
     *
     * Builds the WITH clause.
     *
     * @param array<string, string> $with The CTE elements.
     *
     * @param bool $recursive True if recursive, false if not.
     *
     * @return string
     *
     */
    public function buildWith(array $with, bool $recursive = false): string
    {
        if (empty($with)) {
            return ''; // not applicable
        }

        $keyword = $recursive && $this->allowsRecursiveKeyword()
            ? 'WITH RECURSIVE'
            : 'WITH';

        return $keyword . ' ' . implode(',' . PHP_EOL, $with) . PHP_EOL;
    }

    /**
     *
     * Does this dialect allow the RECURSIVE keyword in WITH?
     *
     * @return bool
     *
     */
    protected function allowsRecursiveKeyword(): bool
    {
        return true;
    }

    /**
     *
     * Builds the `WHERE` clause of the statement.
     *
     * @param list<string> $where The WHERE elements.
     *
     * @return string
     *
     */
    public function buildWhere(array $where): string
    {
        if (empty($where)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'WHERE' . $this->indent($where);
    }

    /**
     *
     * Builds the `ORDER BY ...` clause of the statement.
     *
     * @param list<string> $order_by The ORDER BY elements.
     *
     * @return string
     *
     */
    public function buildOrderBy(array $order_by): string
    {
        if (empty($order_by)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'ORDER BY' . $this->indentCsv($order_by);
    }

    /**
     *
     * Builds the `LIMIT` clause of the statement.
     *
     * @param int $limit The LIMIT element.
     *
     * @return string
     *
     */
    public function buildLimit(int $limit): string
    {
        if (empty($limit)) {
            return '';
        }
        return PHP_EOL . "LIMIT {$limit}";
    }

    /**
     *
     * Builds the `LIMIT ... OFFSET` clause of the statement.
     *
     * @param int $limit The LIMIT element.
     *
     * @param int $offset The OFFSET element.
     *
     * @return string
     *
     */
    public function buildLimitOffset(int $limit, int $offset): string
    {
        $clause = '';

        // a dialect whose grammar has no OFFSET without LIMIT spells "no
        // limit" as the largest one it takes
        if (empty($limit) && !empty($offset) && static::NO_LIMIT !== null) {
            $clause .= 'LIMIT ' . static::NO_LIMIT;
        }

        if (!empty($limit)) {
            $clause .= "LIMIT {$limit}";
        }

        if (!empty($offset)) {
            $clause .= " OFFSET {$offset}";
        }

        if (!empty($clause)) {
            $clause = PHP_EOL . trim($clause);
        }

        return $clause;
    }

    /**
     *
     * Returns an array as an indented comma-separated values string.
     *
     * @param list<string> $list The values to convert.
     *
     * @return string
     *
     */
    public function indentCsv(array $list): string
    {
        return PHP_EOL . '    '
             . implode(',' . PHP_EOL . '    ', $list);
    }

    /**
     *
     * Returns an array as an indented string.
     *
     * @param list<string> $list The values to convert.
     *
     * @return string
     *
     */
    public function indent(array $list): string
    {
        return PHP_EOL . '    '
             . implode(PHP_EOL . '    ', $list);
    }
}
