<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

/**
 *
 * An object for PgSQL SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Select extends Common\Select
{
    /**
     *
     * Adds a LATERAL JOIN to an aliased subselect and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string|Select $spec If a Select
     * object, use as the sub-select; if a string, the sub-select
     * command string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @param string $cond Join on this condition.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws Exception
     *
     */
    public function lateralJoinSubSelect($join, $spec, $name, $cond = null, array $bind = array())
    {
        $join = strtoupper(ltrim("$join JOIN LATERAL"));
        $this->addTableRef("$join (SELECT ...) AS", $name);

        $spec = $this->subSelect($spec, '            ');
        $name = $this->quoter->quoteName($name);
        $cond = $this->fixJoinCondition($cond, $bind);

        $text = rtrim("$join ($spec        ) $name $cond");
        return $this->addJoin('        ' . $text);
    }
}
