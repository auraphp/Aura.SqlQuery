<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception\LogicException;

/**
 *
 * Common SELECT builder.
 *
 * @package Aura.SqlQuery
 *
 */
class SelectBuilder extends AbstractBuilder
{
    /**
     *
     * Builds the columns portion of the SELECT.
     *
     * @param list<string> $cols The columns.
     *
     * @return string
     *
     * @throws LogicException when there are no columns in the SELECT.
     *
     */
    public function buildCols(array $cols): string
    {
        if (empty($cols)) {
            throw new LogicException('No columns in the SELECT.');
        }
        return $this->indentCsv($cols);
    }

    /**
     *
     * Builds the FROM clause.
     *
     * @param list<list<string>> $from The FROM elements.
     *
     * @param array<int, list<string>> $join The JOIN elements.
     *
     * @return string
     *
     */
    public function buildFrom(array $from, array $join): string
    {
        if (empty($from)) {
            return ''; // not applicable
        }

        $refs = [];
        foreach ($from as $from_key => $from_val) {
            if (isset($join[$from_key])) {
                $from_val = array_merge($from_val, $join[$from_key]);
            }
            $refs[] = implode(PHP_EOL, $from_val);
        }
        return PHP_EOL . 'FROM' . $this->indentCsv($refs);
    }

    /**
     *
     * Builds the GROUP BY clause.
     *
     * @param list<string> $group_by The GROUP BY elements.
     *
     * @return string
     *
     */
    public function buildGroupBy(array $group_by): string
    {
        if (empty($group_by)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'GROUP BY' . $this->indentCsv($group_by);
    }

    /**
     *
     * Builds the HAVING clause.
     *
     * @param list<string> $having The HAVING elements.
     *
     * @return string
     *
     */
    public function buildHaving(array $having): string
    {
        if (empty($having)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'HAVING' . $this->indent($having);
    }

    /**
     *
     * Builds the FOR UPDATE portion of the SELECT.
     *
     * @param bool $for_update True if FOR UPDATE, false if not.
     *
     * @return string
     *
     */
    public function buildForUpdate(bool $for_update): string
    {
        if (! $for_update) {
            return ''; // not applicable
        }

        return PHP_EOL . 'FOR UPDATE';
    }
}
