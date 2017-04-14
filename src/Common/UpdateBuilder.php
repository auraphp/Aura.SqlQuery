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
     * Builds the table clause.
     *
     * @return null
     *
     */
    public function buildTable($table)
    {
        return " {$table}";
    }

    /**
     *
     * Builds the updated columns and values of the statement.
     *
     * @return string
     *
     */
    public function buildValuesForUpdate($col_values)
    {
        $values = array();
        foreach ($col_values as $col => $value) {
            $values[] = "{$col} = {$value}";
        }
        return PHP_EOL . 'SET' . $this->indentCsv($values);
    }
}
