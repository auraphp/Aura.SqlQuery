<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery;

use Aura\SqlQuery\Common\SubselectInterface;
use Aura\SqlQuery\Common\QuoterInterface;

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
     * Constructor.
     *
     * @param Quoter $quoter A helper for quoting identifier names.
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
        $cond = $this->quoter->quoteNamesIn($cond);
        $cond = $this->fixCondWithBind($cond, $bind);

        $clause =& $this->$clause;
        if ($clause) {
            $clause[] = "$andor $cond";
        } else {
            $clause[] = $cond;
        }

    }

    protected function fixCondWithBind($cond, array $bind)
    {
        $selects = [];

        foreach ($bind as $key => $val) {
            if ($val instanceof SubselectInterface) {
                $selects[":{$key}"] = $val;
            } else {
                $this->bindValue($key, $val);
            }
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
}
