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
     * @var Quoter
     *
     */
    protected $quoter;

    /**
     *
     * A builder for the query.
     *
     * @var AbstractBuilder
     *
     */
    protected $builder;

    protected $seq_bind_prefix = '';

    protected $seq_bind_number = 0;

    /**
     *
     * Constructor.
     *
     * @param Quoter $quoter A helper for quoting identifier names.
     *
     * @param AbstractBuilder $builder A builder for the query.
     *
     */
    public function __construct(
        QuoterInterface $quoter,
        $builder,
        $seq_bind_prefix = ''
    ) {
        $this->quoter = $quoter;
        $this->builder = $builder;
        $this->seq_bind_prefix = $seq_bind_prefix;
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
        $this->bind_values[$name] = $value;
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
     * Adds conditions to a clause, and binds values.
     *
     * @param string $clause The clause to work with, typically 'where' or
     * 'having'.
     *
     * @param string $andor Add the condition using this operator, typically
     * 'AND' or 'OR'.
     *
     * @param array $cond The intermixed WHERE snippets and bind values.
     *
     * @return null
     *
     */
    protected function addClauseConditions($clause, $andor, array $cond)
    {
        if (empty($cond)) {
            return;
        }

        if ($cond[0] instanceof Closure) {
            $this->addClauseClosure($clause, $andor, $cond[0]);
            return;
        }

        $cond = $this->fixConditions($cond);
        $clause =& $this->$clause;
        if ($clause) {
            $clause[] = "{$andor} {$cond}";
        } else {
            $clause[] = $cond;
        }
    }

    /**
     *
     * Rebuilds intermixed condition snippets and bind values into a single
     * string, binding along the way.
     *
     * @param array $cond The intermixed condition snippets and bind values.
     *
     * @return string The rebuilt condition string.
     *
     */
    protected function fixConditions(array $cond)
    {
        $fixed = '';
        while (! empty($cond)) {
            $fixed .= $this->fixCondition($cond);
        }
        return $fixed;
    }

    /**
     *
     * Rebuilds the next condition snippets and its bind value.
     *
     * @param array $cond The intermixed condition snippets and bind values.
     *
     * @return string The rebuilt condition string.
     *
     */
    protected function fixCondition(&$cond)
    {
        $fixed = $this->quoter->quoteNamesIn(array_shift($cond));
        if (! $cond) {
            return $fixed;
        }

        $value = array_shift($cond);

        if ($value instanceof SelectInterface) {
            $fixed .= $value->getStatement();
            $this->bind_values = array_merge(
                $this->bind_values,
                $value->getBindValues()
            );
            return $fixed;
        }

        $place = $this->getSeqPlaceholder();
        $fixed .= ":{$place}";
        $this->bindValue($place, $value);
        return $fixed;
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
    protected function addClauseClosure($clause, $andor, $closure)
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

    protected function getSeqPlaceholder()
    {
        $this->seq_bind_number ++;
        return $this->seq_bind_prefix . "_{$this->seq_bind_number}_";
    }

}
