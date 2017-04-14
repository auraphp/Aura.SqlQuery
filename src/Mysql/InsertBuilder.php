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
     * Builds the UPDATE ON DUPLICATE KEY part of the statement.
     *
     * @return string
     *
     */
    public function buildValuesForUpdateOnDuplicateKey($col_on_update_values)
    {
        if (empty($col_on_update_values)) {
            return ''; // not applicable
        }

        $values = array();
        foreach ($col_on_update_values as $key => $row) {
            $values[] = $this->indent(array($key . ' = ' . $row));
        }

        return ' ON DUPLICATE KEY UPDATE'
            . implode (',', $values);
    }
}
