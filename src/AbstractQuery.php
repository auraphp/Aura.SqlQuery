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
     * Human-readable names for the $bind_sources values, for error messages.
     *
     * @var array
     *
     */
    protected $bind_source_labels = array(
        'col' => 'cols()',
        'cond' => 'a condition',
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
     * @param string $source The part of the query binding the value: 'col',
     * 'cond', 'conflict', 'duplicate_key', or null when bound by hand.
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

        if (
            $source !== null
            && $prior !== null
            && $prior !== $source
            && array_key_exists($name, $this->bind_values)
        ) {
            $was = isset($this->bind_source_labels[$prior])
                ? $this->bind_source_labels[$prior]
                : $prior;
            $now = isset($this->bind_source_labels[$source])
                ? $this->bind_source_labels[$source]
                : $source;

            throw new Exception\LogicException(
                "The placeholder ':{$name}' is already in use by {$was}, so "
                . "{$now} cannot bind it as well: one value would overwrite "
                . "the other. Use a different placeholder name for one of "
                . "them."
            );
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
            $this->bindValues($bind);
            return;
        }

        $cond = $this->quoter->quoteNamesIn($cond);
        $cond = $this->rebuildCondAndBindValues($cond, $bind);

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
     * @return string The rebuilt condition string.
     *
     */
    protected function rebuildCondAndBindValues($cond, array $bind_values)
    {
        $index = 0;
        $selects = [];

        foreach ($bind_values as $key => $val) {
            if ($val instanceof SelectInterface) {
                $selects[":{$key}"] = $val;
            } elseif (is_array($val)) {
                $cond = $this->getCond($key, $cond, $val, $index);
            } else {
                $this->bindValueFrom($key, $val, 'cond');
            }
            $index++;
        }

        foreach ($selects as $key => $select) {
            $selects[$key] = $select->getStatement();
            $this->bind_values = array_merge(
                $this->bind_values,
                $select->getBindValues()
            );
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
