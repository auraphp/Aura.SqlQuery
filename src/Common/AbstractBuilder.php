<?php
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception;

abstract class AbstractBuilder
{
    public function __construct($quoter)
    {
        $this->quoter = $quoter;
    }

    /**
     *
     * Builds the flags as a space-separated string.
     *
     * @return string
     *
     */
    public function buildFlags($flags)
    {
        if (empty($flags)) {
            return ''; // not applicable
        }

        return ' ' . implode(' ', array_keys($flags));
    }

    /**
     *
     * Builds the `WHERE` clause of the statement.
     *
     * @return string
     *
     */
    public function buildWhere($where)
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
     * @return string
     *
     */
    public function buildOrderBy($order_by)
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
     * @return string
     *
     */
    public function buildLimit($limit)
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
     * @return string
     *
     */
    public function buildLimitOffset($limit, $offset)
    {
        $clause = '';

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
     * Builds the `RETURNING` clause of the statement.
     *
     * @return string
     *
     */
    public function buildReturning($returning)
    {
        if (empty($returning)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'RETURNING' . $this->indentCsv($returning);
    }

    /**
     *
     * Returns an array as an indented comma-separated values string.
     *
     * @param array $list The values to convert.
     *
     * @return string
     *
     */
    public function indentCsv(array $list)
    {
        return PHP_EOL . '    '
             . implode(',' . PHP_EOL . '    ', $list);
    }

    /**
     *
     * Returns an array as an indented string.
     *
     * @param array $list The values to convert.
     *
     * @return string
     *
     */
    public function indent(array $list)
    {
        return PHP_EOL . '    '
             . implode(PHP_EOL . '    ', $list);
    }
}
