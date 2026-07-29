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
 * Adds a non-recursive common table expression (CTE) to the query.
 *
 * @param string $name The CTE name.
 * @param string|SelectInterface $spec The CTE specification.
 * @param array $cols The optional CTE column list.
 * @return $this The query instance.
 */
    public function with($name, $spec, array $cols = array());

    /**
 * Adds a recursive common table expression (CTE) to the query.
 *
 * @param string $name The CTE name.
 * @param string|SelectInterface $spec The CTE specification.
 * @param array $cols The optional CTE column list.
 * @return $this The query instance.
 */
    public function withRecursive($name, $spec, array $cols = array());

    /**
 * Determines whether the query defines any common table expressions.
 *
 * @return bool `true` if the query defines one or more common table expressions, `false` otherwise.
 */
    public function hasWith();

    /**
 * Clears all common table expressions from the query's WITH clause.
 *
 * @return $this The query instance.
 */
    public function resetWith();
}
