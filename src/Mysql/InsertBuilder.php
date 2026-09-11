<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

/**
 *
 * INSERT builder for MySQL.
 *
 * @package Aura.SqlQuery
 *
 */
class InsertBuilder extends Common\InsertBuilder
{
    /**
     *
     * Builds the inserted columns and values of the statement; MySQL does
     * not support `DEFAULT VALUES`, so an insert with no columns uses the
     * empty-list form instead.
     *
     * @param array<string, string> $col_values The column names and values.
     *
     * @return string
     *
     */
    public function buildValuesForInsert(array $col_values)
    {
        if (empty($col_values)) {
            return ' () VALUES ()';
        }

        return parent::buildValuesForInsert($col_values);
    }

    /**
     *
     * Builds the UPDATE ON DUPLICATE KEY part of the statement.
     *
     * @param array<string, string>|null $col_on_update_values Columns and
     * values to use for ON DUPLICATE KEY UPDATE.
     *
     * @return string
     *
     */
    public function buildValuesForUpdateOnDuplicateKey(?array $col_on_update_values)
    {
        if (empty($col_on_update_values)) {
            return ''; // not applicable
        }

        $values = [];
        foreach ($col_on_update_values as $key => $row) {
            $values[] = $this->indent([$key . ' = ' . $row]);
        }

        return ' ON DUPLICATE KEY UPDATE'
            . implode (',', $values);
    }
}
