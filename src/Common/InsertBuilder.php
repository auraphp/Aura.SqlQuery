<?php
namespace Aura\SqlQuery\Common;

class InsertBuilder extends AbstractBuilder
{

    /**
     *
     * Builds the INTO clause.
     *
     * @return string
     *
     */
    public function buildInto($into)
    {
        return " INTO " . $this->quoter->quoteName($into);
    }


    /**
     *
     * Builds the inserted columns and values of the statement.
     *
     * @return string
     *
     */
    public function buildValuesForInsert($col_values)
    {
        return ' ('
            . $this->indentCsv(array_keys($col_values))
            . PHP_EOL . ') VALUES ('
            . $this->indentCsv(array_values($col_values))
            . PHP_EOL . ')';
    }

    /**
     *
     * Builds the bulk-inserted columns and values of the statement.
     *
     * @return string
     *
     */
    public function buildValuesForBulkInsert($col_order, $col_values_bulk)
    {
        $cols = "    (" . implode(', ', $col_order) . ")";
        $vals = array();
        foreach ($col_values_bulk as $row_values) {
            $vals[] = "    (" . implode(', ', $row_values) . ")";
        }
        return PHP_EOL . $cols . PHP_EOL
            . "VALUES" . PHP_EOL
            . implode("," . PHP_EOL, $vals);
    }
}
