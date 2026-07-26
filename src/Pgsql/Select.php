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
use Aura\SqlQuery\Exception;

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
     * @param string $cond Join on this condition. Postgres requires an ON
     * clause on every LATERAL join except CROSS and NATURAL, so when no
     * condition is given for those other join types, "ON true" is used.
     *
     * @param array $bind Values to bind to ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws Exception
     *
     */
    public function lateralJoinSubSelect($join, $spec, $name, $cond = null, array $bind = [])
    {
        $join = strtoupper(ltrim("$join JOIN LATERAL"));

        if ($cond && $this->joinForbidsCondition($join)) {
            throw new Exception\LogicException(
                "A $join cannot take a condition."
            );
        }

        $this->addTableRef("$join (SELECT ...)", $name);

        $spec = $this->subSelect($spec, '            ');
        $name = $this->quoter->quoteName($name);
        $cond = $this->fixJoinCondition($cond, $bind);

        if ($cond === '' && ! $this->isUnconditionalJoin($join)) {
            $cond = 'ON true';
        }

        $text = rtrim("$join ($spec        ) $name $cond");
        return $this->addJoin('        ' . $text);
    }

    /**
     *
     * Does this join type render without an ON clause when no condition is
     * given?
     *
     * @param string $join The upper-cased join clause.
     *
     * @return bool
     *
     */
    protected function isUnconditionalJoin($join)
    {
        return str_starts_with($join, 'CROSS ')
            || str_starts_with($join, 'NATURAL ');
    }

    /**
     *
     * Does this join type reject an ON clause outright, so that supplying a
     * condition can only produce a syntax error?
     *
     * PostgreSQL rejects ON on both CROSS and NATURAL joins. Other dialects
     * differ -- MySQL accepts it on CROSS, where CROSS and INNER are
     * synonyms -- so this is separate from isUnconditionalJoin() and meant to
     * be overridden.
     *
     * @param string $join The upper-cased join clause.
     *
     * @return bool
     *
     */
    protected function joinForbidsCondition($join)
    {
        return str_starts_with($join, 'CROSS ')
            || str_starts_with($join, 'NATURAL ');
    }
}
