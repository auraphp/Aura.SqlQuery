<?php
namespace Aura\SqlQuery\Common;

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
