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
 * Common UPDATE builder.
 *
 * @package Aura.SqlQuery
 *
 */
class UpdateBuilder extends AbstractBuilder
{
    /**
     *
     * Builds the table portion of the UPDATE.
     *
     * @param string $table The table name.
     *
     * @return string
     *
     */
    public function buildTable(string $table)
    {
        return " {$table}";
    }

    /**
     *
     * Builds the columns and values for the statement.
     *
     * @param array<string, string> $col_values The columns and values.
     *
     * @return string
     *
     */
    public function buildValuesForUpdate(array $col_values)
    {
        $values = [];
        foreach ($col_values as $col => $value) {
            $values[] = "{$col} = {$value}";
        }
        return PHP_EOL . 'SET' . $this->indentCsv($values);
    }
}
