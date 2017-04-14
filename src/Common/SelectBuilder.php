<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception;

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
     * Builds the columns clause.
     *
     * @return string
     *
     * @throws Exception when there are no columns in the SELECT.
     *
     */
    public function buildCols($cols)
    {
        if (empty($cols)) {
            throw new Exception('No columns in the SELECT.');
        }
        return $this->indentCsv($cols);
    }

    /**
     *
     * Builds the FROM clause.
     *
     * @return string
     *
     */
    public function buildFrom($from, $join)
    {
        if (empty($from)) {
            return ''; // not applicable
        }

        $refs = array();
        foreach ($from as $from_key => $from) {
            if (isset($join[$from_key])) {
                $from = array_merge($from, $join[$from_key]);
            }
            $refs[] = implode(PHP_EOL, $from);
        }
        return PHP_EOL . 'FROM' . $this->indentCsv($refs);
    }

    /**
     *
     * Builds the GROUP BY clause.
     *
     * @return string
     *
     */
    public function buildGroupBy($group_by)
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
     * @return string
     *
     */
    public function buildHaving($having)
    {
        if (empty($having)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'HAVING' . $this->indent($having);
    }

    /**
     *
     * Builds the FOR UPDATE clause.
     *
     * @return string
     *
     */
    public function buildForUpdate($for_update)
    {
        if (! $for_update) {
            return ''; // not applicable
        }

        return PHP_EOL . 'FOR UPDATE';
    }
}
