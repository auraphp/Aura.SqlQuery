<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\QuoterInterface;
use Closure;

/**
 *
 * Abstract query object.
 *
 * @package Aura.SqlQuery
 *
 */
abstract class AbstractQuery
{
    /**
     *
     * Data to be bound to the query.
     *
     * @var array
     *
     */
    protected $bind_values = array();

    /**
     *
     * Which part of the query bound each placeholder name; null means it was
     * bound by hand. Keys match $bind_values.
     *
     * @var array
     *
     */
    protected $bind_sources = array();

    /**
     *
     * A second claimant on a name a rendered UNION branch owns, for the case
     * where the active branch asks for the value already bound. Ownership
     * stays with the union, but the name is not free while this clause is
     * still using it. Keys match $bind_sources.
     *
     * @var array
     *
     */
    protected $bind_shared = array();

    /**
     *
     * Human-readable names for the $bind_sources values, for error messages.
     *
     * @var array
     *
     */
    protected $bind_source_labels = array(
        'col' => 'cols()',
        'cond' => 'a condition',
        'where' => 'a WHERE condition',
        'having' => 'a HAVING condition',
        'join' => 'a JOIN condition',
        'union' => 'a rendered UNION branch',
        'conflict' => 'doUpdateCol()',
        'duplicate_key' => 'onDuplicateKeyUpdateCol()',
    );

    /**
     *
     * The list of WHERE conditions.
     *
     * @var array
     *
     */
    protected $where = array();

    /**
     *
     * ORDER BY these columns.
     *
     * @var array
     *
     */
    protected $order_by = array();

    /**
     *
     * The list of flags.
     *
     * @var array
     *
     */
    protected $flags = array();

    /**
     *
     * A helper for quoting identifier names.
     *
     * @var Common\Quoter
     *
     */
    protected $quoter;

    /**
     *
     * A builder for the query.
     *
     * @var Common\AbstractBuilder
     *
     */
    protected $builder;

    /**
     * @var int
     */
    protected $inlineCount = 0;

    /**
     *
     * Constructor.
     *
     * @param Common\Quoter $quoter A helper for quoting identifier names.
     *
     * @param Common\AbstractBuilder $builder A builder for the query.
     *
     */
    public function __construct(QuoterInterface $quoter, $builder)
    {
        $this->quoter = $quoter;
        $this->builder = $builder;
    }

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * @return string
     *
     */
    public function __toString()
    {
        return $this->getStatement();
    }

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * @return string
     *
     */
    public function getStatement()
    {
        return $this->build();
    }

    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     */
    abstract protected function build();

    /**
     *
     * Returns the prefix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNamePrefix()
    {
        return $this->quoter->getQuoteNamePrefix();
    }

    /**
     *
     * Returns the suffix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNameSuffix()
    {
        return $this->quoter->getQuoteNameSuffix();
    }

    /**
     *
     * Binds multiple values to placeholders; merges with existing values.
     *
     * @param array $bind_values Values to bind to placeholders.
     *
     * @return $this
     *
     */
    public function bindValues(array $bind_values)
    {
        // array_merge() renumbers integer keys, which is bad for
        // question-mark placeholders
        foreach ($bind_values as $key => $val) {
            $this->bindValue($key, $val);
        }
        return $this;
    }

    /**
     *
     * Binds a single value to the query.
     *
     * @param string $name The placeholder name or number.
     *
     * @param mixed $value The value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function bindValue($name, $value)
    {
        return $this->bindValueFrom($name, $value, null);
    }

    /**
     *
     * Binds a single value, recording which part of the query asked for it.
     *
     * Two different parts of a query claiming the same placeholder name is a
     * mistake: only one value can survive in the flat bind array, so the
     * other is silently discarded and the statement runs with the wrong data.
     * Throw instead of losing it -- even when the two values happen to agree
     * today, since either part may revise its value afterwards and there is
     * nothing to re-check it against.
     *
     * A null source means the caller bound the value by hand, which may
     * always overwrite: rebinding before execution, and reusing a query
     * object with fresh values, are both legitimate.
     *
     * @param string $name The placeholder name or number.
     *
     * @param mixed $value The value to bind to the placeholder.
     *
     * @param string|null $source The part of the query binding the value:
     * 'col', 'where', 'having', 'join', 'cond' for a condition with no
     * clause of its own, 'conflict', 'duplicate_key', 'union', or null when
     * bound by hand.
     *
     * @return $this
     *
     * @throws Exception\LogicException when two different parts of the query
     * claim one placeholder name.
     *
     */
    protected function bindValueFrom($name, $value, $source)
    {
        $prior = isset($this->bind_sources[$name])
            ? $this->bind_sources[$name]
            : null;

        // A rendered UNION branch owns its placeholders, but a later branch
        // filtering on the same value -- one tenant id across both halves --
        // asks for exactly what is already bound, and nothing is lost by
        // letting it through. Ownership stays with the union: were it to pass
        // to the new clause, a later resetWhere() would free a name the
        // rendered SQL still binds. Record the clause as a second claimant
        // all the same, so that resetUnions() hands the name over to it
        // instead of freeing a name this clause is still using.
        if (
            $prior === 'union'
            && array_key_exists($name, $this->bind_values)
            && $this->bind_values[$name] === $value
        ) {
            if ($source !== null && $source !== 'union') {
                $shared = isset($this->bind_shared[$name])
                    ? $this->bind_shared[$name]
                    : null;
                if ($shared !== null && $shared !== $source) {
                    $this->throwCollision($name, $shared, $source);
                }
                $this->bind_shared[$name] = $source;
            }
            return $this;
        }

        if (
            $source !== null
            && $prior !== null
            && array_key_exists($name, $this->bind_values)
            && (
                $prior !== $source
                || (
                    $this->bind_values[$name] !== $value
                    && !in_array($source, ['col', 'conflict', 'duplicate_key'], true)
                )
            )
        ) {
            $this->throwCollision($name, $prior, $source);
        }

        $this->bind_values[$name] = $value;

        // A hand-bound value overwrites the value but does not claim the
        // name: otherwise binding by hand between two parts of the query
        // would erase the record of who claimed it first, and the collision
        // they would have had goes undetected. Any other source reaching
        // here either claimed the name already or found it free, since a
        // second claimant throws above.
        if ($source !== null) {
            $this->bind_sources[$name] = $source;
        }

        return $this;
    }

    /**
     *
     * Takes over the values a sub-select has bound, keeping the part of that
     * sub-select which claimed each one so the collision check reads the same
     * as it would there.
     *
     * @param SelectInterface $select The sub-select to take the values from.
     *
     * @param string $default_source The source to record for a value the
     * sub-select bound by hand, which has no claimant to carry over.
     *
     * @return $this
     *
     */
    protected function bindValuesFromSelect(SelectInterface $select, $default_source)
    {
        $bind_sources = ($select instanceof AbstractQuery) ? $select->bind_sources : [];

        foreach ($select->getBindValues() as $name => $value) {
            $source = isset($bind_sources[$name])
                ? $bind_sources[$name]
                : $default_source;
            $this->bindValueFrom($name, $value, $source);
        }

        return $this;
    }

    /**
     *
     * Reports two parts of the query claiming one placeholder name.
     *
     * @param string $name The placeholder name.
     *
     * @param string $prior The part of the query holding the name.
     *
     * @param string $source The part of the query asking for it as well.
     *
     * @return void
     *
     * @throws Exception\LogicException always.
     *
     */
    protected function throwCollision($name, $prior, $source)
    {
        $was = isset($this->bind_source_labels[$prior])
            ? $this->bind_source_labels[$prior]
            : $prior;
        $now = isset($this->bind_source_labels[$source])
            ? $this->bind_source_labels[$source]
            : $source;

        // "a WHERE condition ... a WHERE condition" reads like a bug when
        // both halves are the same clause; say "another" instead, taking
        // the article off the label first.
        if ($prior === $source) {
            $now = 'another ' . preg_replace('/^an? /', '', $now);
        }

        // a positional placeholder is bound by number and keeps its `?` in
        // the statement, so quoting it as ':1' would name a token the query
        // does not contain and send the reader looking for it.
        $which = ctype_digit((string) $name)
            ? "The positional placeholder {$name}"
            : "The placeholder ':{$name}'";

        throw new Exception\LogicException(
            "{$which} is already in use by {$was}, so "
            . "{$now} cannot bind it as well: one value would overwrite "
            . "the other. Use a different placeholder for one of them."
        );
    }

    /**
     *
     * Gets the values to bind to placeholders.
     *
     * @return array
     *
     */
    public function getBindValues()
    {
        return $this->bind_values;
    }

    /**
     *
     * Reset all values bound to named placeholders.
     *
     * @return $this
     *
     */
    public function resetBindValues()
    {
        $this->bind_values = array();
        $this->bind_sources = array();
        $this->bind_shared = array();
        return $this;
    }

    /**
     *
     * Releases the placeholder names a query source claimed, so they may be
     * claimed again. The bound values themselves are kept: the clause resets
     * have never removed them, and union() depends on that.
     *
     * A name another clause is sharing passes to that clause rather than
     * being released, since it is still in use.
     *
     * @param string $source The source to remove (e.g. 'where', 'having').
     *
     * @return $this
     *
     */
    protected function removeBindSources($source)
    {
        // drop the record of who claimed the name, but keep the value: the
        // clause resets never removed bound values, and union() depends on
        // that -- it renders the current half to SQL, placeholders and all,
        // then resets, so deleting the values leaves that SQL with tokens
        // nothing can bind.
        foreach ($this->bind_shared as $name => $src) {
            if ($src === $source) {
                unset($this->bind_shared[$name]);
            }
        }

        foreach ($this->bind_sources as $name => $src) {
            if ($src !== $source) {
                continue;
            }
            // a name another clause is still using is not free: hand it over
            // rather than releasing it, or that clause's placeholder could be
            // rebound to a different value with nothing to report the clash.
            if (isset($this->bind_shared[$name])) {
                $this->bind_sources[$name] = $this->bind_shared[$name];
                unset($this->bind_shared[$name]);
                continue;
            }
            unset($this->bind_sources[$name]);
        }
        return $this;
    }

    /**
     *
     * Sets or unsets specified flag.
     *
     * @param string $flag Flag to set or unset
     *
     * @param bool $enable Flag status - enabled or not (default true)
     *
     * @return null
     *
     */
    protected function setFlag($flag, $enable = true)
    {
        if ($enable) {
            $this->flags[$flag] = true;
        } else {
            unset($this->flags[$flag]);
        }
    }

    /**
     *
     * Returns true if the specified flag was enabled by setFlag().
     *
     * @param string $flag Flag to check
     *
     * @return bool
     *
     */
    protected function hasFlag($flag)
    {
        return isset($this->flags[$flag]);
    }

    /**
     *
     * Reset all query flags.
     *
     * @return $this
     *
     */
    public function resetFlags()
    {
        $this->flags = array();
        return $this;
    }

    /**
     *
     * Adds conditions and binds values to a clause.
     *
     * @param string $clause The clause to work with, typically 'where' or
     * 'having'.
     *
     * @param string $andor Add the condition using this operator, typically
     * 'AND' or 'OR'.
     *
     * @param string $cond The WHERE condition.
     *
     * @param array $bind arguments to bind to placeholders
     *
     * @return null
     *
     */
    protected function addClauseCondWithBind($clause, $andor, $cond, $bind)
    {
        if ($cond instanceof Closure) {
            $this->addClauseCondClosure($clause, $andor, $cond);
            foreach ($bind as $key => $val) {
                $this->bindValueFrom($key, $val, $clause);
            }
            return;
        }

        $cond = $this->quoter->quoteNamesIn($cond);
        $cond = $this->rebuildCondAndBindValues($cond, $bind, $clause);

        $clause =& $this->$clause;
        if ($clause) {
            $clause[] = "$andor $cond";
        } else {
            $clause[] = $cond;
        }
    }

    /**
     *
     * Adds to a clause through a closure, enclosing within parentheses.
     *
     * @param string $clause The clause to work with, typically 'where' or
     * 'having'.
     *
     * @param string $andor Add the condition using this operator, typically
     * 'AND' or 'OR'.
     *
     * @param callable $closure The closure that adds to the clause.
     *
     * @return null
     *
     */
    protected function addClauseCondClosure($clause, $andor, $closure)
    {
        // retain the prior set of conditions, and temporarily reset the clause
        // for the closure to work with (otherwise there will be an extraneous
        // opening AND/OR keyword)
        $set = $this->$clause;
        $this->$clause = [];

        // invoke the closure, which will re-populate the $this->$clause
        $closure($this);

        // are there new clause elements?
        if (! $this->$clause) {
            // no: restore the old ones, and done
            $this->$clause = $set;
            return;
        }

        // append an opening parenthesis to the prior set of conditions,
        // with AND/OR as needed ...
        if ($set) {
            $set[] = "{$andor} (";
        } else {
            $set[] = "(";
        }

        // append the new conditions to the set, with indenting
        foreach ($this->$clause as $cond) {
            $set[] = "    {$cond}";
        }
        $set[] = ")";

        // ... then put the full set of conditions back into $this->$clause
        $this->$clause = $set;
    }

    /**
     *
     * Rebuilds a condition string, replacing sequential placeholders with
     * named placeholders, and binding the sequential values to the named
     * placeholders.
     *
     * @param string $cond The condition with sequential placeholders.
     *
     * @param array $bind_values The values to bind to the sequential
     * placeholders under their named versions.
     *
     * @param string $clause The source clause name.
     *
     * @return string The rebuilt condition string.
     *
     */
    protected function rebuildCondAndBindValues($cond, array $bind_values, $clause = 'cond')
    {
        $index = 0;
        $selects = [];

        foreach ($bind_values as $key => $val) {
            if ($val instanceof SelectInterface) {
                $selects[":{$key}"] = $val;
            } elseif (is_array($val)) {
                $cond = $this->getCond($key, $cond, $val, $index);
            } else {
                $this->bindValueFrom($key, $val, $clause);
            }
            $index++;
        }

        foreach ($selects as $key => $select) {
            $selects[$key] = $select->getStatement();
            $this->bindValuesFromSelect($select, $clause);
        }

        $cond = strtr($cond, $selects);
        return $cond;
    }

    protected function inlineArray(array $array)
    {
        $keys = [];
        foreach ($array as $val) {
            $this->inlineCount++;
            $key = "__{$this->inlineCount}__";
            $this->bindValueFrom($key, $val, 'cond');
            $keys[] = ":{$key}";
        }
        return implode(', ', $keys);
    }

    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return $this
     *
     */
    protected function addOrderBy(array $spec)
    {
        foreach ($spec as $col) {
            $this->order_by[] = $this->quoter->quoteNamesIn($col);
        }
        return $this;
    }

    /**
     * @param int|string $key
     * @param string     $cond
     * @param array      $val
     * @param int        $index
     *
     * @return string
     */
    private function getCond($key, $cond, array $val, $index)
    {
        if (is_string($key)) {
            return str_replace(':' . $key, $this->inlineArray($val), $cond);
        }
        assert(is_int($key));

        if (preg_match_all('/\?/', $cond, $matches, PREG_OFFSET_CAPTURE) !== false) {
            return substr_replace($cond, $this->inlineArray($val), $matches[0][$index][1], 1);
        }

        return $cond;
    }
}
