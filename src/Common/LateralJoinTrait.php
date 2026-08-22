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
 * Common code for LATERAL joins, on the dialects that support them.
 *
 * @package Aura.SqlQuery
 *
 */
trait LateralJoinTrait
{
    /**
     *
     * Adds a LATERAL JOIN to an aliased subselect and columns to the query.
     *
     * A LATERAL join lets the sub-select reference columns from the tables to
     * its left.
     *
     * @param string $join The join type: inner, left, cross, etc.
     *
     * @param string|SelectInterface $spec If a Select object, use as the
     * sub-select; if a string, the sub-select command string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @param string|null $cond Join on this condition. A LATERAL join requires an
     * ON clause except on the join types that forbid one, so when no
     * condition is given for the other types, "ON true" is used.
     *
     * @param array<int|string, mixed> $bind Values to bind to
     * ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws Exception\LogicException
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

        $spec = $this->subSelect($spec, '            ', 'join');
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
     * CROSS and NATURAL joins stand alone on every dialect that supports
     * LATERAL, so no default condition is supplied for them.
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
     * A NATURAL join derives its condition from the common column names and
     * rejects ON on every dialect. CROSS is dialect-specific: PostgreSQL
     * rejects ON there as well, whereas MySQL accepts it, since CROSS and
     * INNER are synonyms there. This default is the strict PostgreSQL rule;
     * Mysql\Select loosens it.
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
