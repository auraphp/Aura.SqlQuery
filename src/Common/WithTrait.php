<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception\InvalidArgumentException;
use Aura\SqlQuery\Exception\LogicException;

/**
 *
 * A trait implementing WithInterface.
 *
 * @package Aura.SqlQuery
 *
 */
trait WithTrait
{
    /**
     *
     * The common table expressions, already rendered, keyed by name.
     *
     * @var array
     *
     */
    protected $with = array();

    /**
     *
     * Is this a recursive CTE query?
     *
     * @var bool
     *
     */
    protected $with_recursive = false;

    /**
     *
     * Renders a sub-SELECT and takes over the values it bound.
     *
     * Declared here because it is not part of AbstractQuery: it belongs to
     * Select, which is the only query this trait can be mixed into today.
     * Using the trait on a query without it is a fatal error at the call
     * rather than a silent one, and adding CTEs to INSERT, UPDATE or DELETE
     * means giving them this first.
     *
     * @param string|SelectInterface $spec A sub-SELECT specification.
     *
     * @param string $indent Indent each line with this string.
     *
     * @param string $source The part of this query the sub-select is being
     * rendered into, which claims the names it binds.
     *
     * @return string
     *
     */
    abstract protected function subSelect($spec, $indent, $source = 'table');

    /**
     *
     * Adds a common table expression (CTE) to the query.
     *
     * The CTE is rendered on the spot rather than kept as an object, as a
     * union branch is and for the same reason: it is a second query with a
     * life of its own, and holding it would have edits made to it after this
     * call reach back into a statement it was only ever added to once.
     *
     * The names it binds pass to 'with', which belongs to the statement
     * rather than to any one clause of it -- a CTE is written once at the
     * top and every branch of a union below may name it.
     *
     * @param string $name The CTE name.
     *
     * @param string|SelectInterface $spec The CTE specification.
     *
     * @param array $cols Optional column list for the CTE.
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the CTE has no name.
     *
     * @throws LogicException when handed this very query, or when the name
     * is already taken.
     *
     */
    public function with($name, $spec, array $cols = array())
    {
        // a query cannot be a CTE of itself: it would have to be rendered
        // into the clause at the moment it must stand apart from it. The
        // recursion a CTE does allow is written inside the sub-select, by
        // naming the CTE in its own FROM; a clone is what this asks for.
        if ($spec === $this) {
            throw new LogicException(
                'Cannot use a query as a CTE of itself; pass a clone of it instead.'
            );
        }

        $name = trim((string) $name);
        if ($name === '') {
            throw new InvalidArgumentException('with() requires a CTE name.');
        }

        // a repeated name is not two CTEs: the statement can only mean one
        // of them and SQL does not say which, so the second would replace
        // the first without a word.
        if (isset($this->with[$name])) {
            throw new LogicException(
                "The CTE '{$name}' is already defined; use a different name, "
                . 'or call resetWith() first.'
            );
        }

        $head = $this->quoter->quoteName($name);
        if (! empty($cols)) {
            $quoted = array();
            foreach ($cols as $col) {
                $quoted[] = $this->quoter->quoteName($col);
            }
            $head .= ' (' . implode(', ', $quoted) . ')';
        }

        // the sub-select's values are taken over as it is rendered, and one
        // of them may collide with a name a clause of this query already
        // holds. That leaves the values bound before it claimed for a CTE
        // this query is not going to have: names held against nothing, and
        // placeholders reported by getBindValues() that the statement never
        // spells. Put them back and let the collision through.
        $bind_values = $this->bind_values;
        $bind_sources = $this->bind_sources;
        $bind_shared = $this->bind_shared;

        try {
            $body = $this->subSelect($spec, '    ', 'with');
        } catch (\Exception $e) {
            $this->bind_values = $bind_values;
            $this->bind_sources = $bind_sources;
            $this->bind_shared = $bind_shared;
            throw $e;
        }

        $this->with[$name] = $head . ' AS (' . $body . ')';
        return $this;
    }

    /**
     *
     * Adds a recursive common table expression (CTE) to the query.
     *
     * RECURSIVE is written once for the whole clause rather than per CTE,
     * which is what standard SQL spells: `WITH RECURSIVE a AS (...), b AS
     * (...)`, where a non-recursive member is still legal. So one recursive
     * CTE makes the clause recursive and the others are unaffected.
     *
     * @param string $name The CTE name.
     *
     * @param string|SelectInterface $spec The CTE specification.
     *
     * @param array $cols Optional column list for the CTE.
     *
     * @return $this
     *
     */
    public function withRecursive($name, $spec, array $cols = array())
    {
        // added first, so that a CTE that cannot render leaves the clause as
        // it was rather than marking it recursive on the way to throwing.
        $this->with($name, $spec, $cols);
        $this->with_recursive = true;
        return $this;
    }

    /**
     *
     * Does the query define any common table expressions?
     *
     * @return bool
     *
     */
    public function hasWith()
    {
        return (bool) $this->with;
    }

    /**
     *
     * Resets the WITH clause.
     *
     * @return $this
     *
     */
    public function resetWith()
    {
        $this->with = array();
        $this->with_recursive = false;

        // the CTEs are gone, so nothing binds their placeholders any more:
        // release the names they were holding. A name a clause is sharing
        // passes to that clause rather than being freed.
        $this->removeBindSources('with');
        return $this;
    }
}
