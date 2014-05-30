<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.SqlQuery
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery;

use Aura\SqlQuery\Common\LimitInterface;
use Aura\SqlQuery\Common\LimitOffsetInterface;

/**
 *
 * Abstract query object for Select, Insert, Update, and Delete.
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
     * Bind these values to the WHERE conditions.
     *
     * @var array
     *
     */
    protected $bind_where = array();

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
     * The number of rows to select
     *
     * @var int
     *
     */
    protected $limit = 0;

    /**
     *
     * Return rows after this offset.
     *
     * @var int
     *
     */
    protected $offset = 0;

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
     * The prefix to use when quoting identifier names.
     *
     * @var string
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
    public function __construct(Quoter $quoter)
    {
        $this->quoter = $quoter;
    }

    /**
     *
     * Returns this query object as a string.
     *
     * @return string
     *
     */
    public function __toString()
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
     * Returns an array as an indented comma-separated values string.
     *
     * @param array $list The values to convert.
     *
     * @return string
     *
     */
    protected function indentCsv(array $list)
    {
        return PHP_EOL . '    '
             . implode(',' . PHP_EOL . '    ', $list);
    }

    /**
     *
     * Returns an array as an indented string.
     *
     * @param array $list The values to convert.
     *
     * @return string
     *
     */
    protected function indent(array $list)
    {
        return PHP_EOL . '    '
             . implode(PHP_EOL . '    ', $list);
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
     * Builds the flags as a space-separated string.
     *
     * @return string
     *
     */
    protected function buildFlags()
    {
        if (! $this->flags) {
            return ''; // not applicable
        }

        return ' ' . implode(' ', array_keys($this->flags));
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
     * Reset all query flags.
     *
     * @return null
     *
     */
    protected function resetFlags()
    {
        $this->flags = array();
    }

    /**
     *
     * Adds a WHERE condition to the query by AND or OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $op   operator: 'AND' or 'OR'
     * @param string $cond The WHERE condition.
     * @param array $bind arguments to bind to placeholders
     *
     * @return $this
     */
    protected function addWhere($andor, $args)
    {
        $this->addClauseCondWithBind('where', $andor, $args);
        return $this;
    }

    protected function addClauseCondWithBind($clause, $andor, $args)
    {
        // remove the condition from the args and quote names in it
        $cond = array_shift($args);
        $cond = $this->quoter->quoteNamesIn($cond);

        // remaining args are bind values; e.g., $this->bind_where
        $bind =& $this->{"bind_{$clause}"};
        foreach ($args as $value) {
            $bind[] = $value;
        }

        // add condition to clause; $this->where
        $clause =& $this->$clause;
        if ($clause) {
            $clause[] = "$andor $cond";
        } else {
            $clause[] = $cond;
        }
    }

    /**
     *
     * Builds the `WHERE` clause of the statement.
     *
     * @return string
     *
     */
    protected function buildWhere()
    {
        if (! $this->where) {
            return ''; // not applicable
        }

        return PHP_EOL . 'WHERE' . $this->indent($this->where);
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
     *
     * Builds the `ORDER BY ...` clause of the statement.
     *
     * @return string
     *
     */
    protected function buildOrderBy()
    {
        if (! $this->order_by) {
            return ''; // not applicable
        }

        return PHP_EOL . 'ORDER BY' . $this->indentCsv($this->order_by);
    }

    /**
     *
     * Builds the `LIMIT ... OFFSET` clause of the statement.
     *
     * @return string
     *
     */
    protected function buildLimit()
    {
        $has_limit = $this instanceof LimitInterface;
        $has_offset = $this instanceof LimitOffsetInterface;

        if ($has_offset && $this->limit) {
            $clause = PHP_EOL . "LIMIT {$this->limit}";
            if ($this->offset) {
                $clause .= " OFFSET {$this->offset}";
            }
            return $clause;
        } elseif ($has_limit && $this->limit) {
            return PHP_EOL . "LIMIT {$this->limit}";
        }

        return ''; // not applicable
    }
}
