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
 * An interface for WITH clauses.
 *
 * @package Aura.SqlQuery
 *
 */
interface WithInterface
{
    /**
     *
     * Adds a common table expression (CTE) to the query.
     *
     * @param string $name The CTE name.
     *
     * @param string|SelectInterface $spec The CTE specification.
     *
     * @param array<array-key, string> $cols Optional column list for the
     * CTE.
     *
     * @return $this
     *
     */
    public function with($name, $spec, array $cols = []);

    /**
     *
     * Adds a recursive common table expression (CTE) to the query.
     *
     * @param string $name The CTE name.
     *
     * @param string|SelectInterface $spec The CTE specification.
     *
     * @param array<array-key, string> $cols Optional column list for the
     * CTE.
     *
     * @return $this
     *
     */
    public function withRecursive($name, $spec, array $cols = []);

    /**
     *
     * Does the query define any common table expressions?
     *
     * @return bool
     *
     */
    public function hasWith();

    /**
     *
     * Resets the WITH clause.
     *
     * @return $this
     *
     */
    public function resetWith();
}
