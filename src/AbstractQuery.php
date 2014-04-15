<?php
/**
 * 
 * This file is part of Aura for PHP.
 * 
 * @package Aura.Sql_Query
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Sql_Query;

use Aura\Sql_Query\Common\LimitInterface;
use Aura\Sql_Query\Common\LimitOffsetInterface;

/**
 * 
 * Abstract query object for Select, Insert, Update, and Delete.
 * 
 * @package Aura.Sql_Query
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
     * Column values for INSERT or UPDATE queries; the key is the column name and the
     * value is the column value.
     *
     * @param array
     *
     */
    protected $col_values;

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
     * The columns to be returned.
     *
     * @var array
     *
     */
    protected $returning = array();

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
     * Gets the values to bind to placeholders, including any 'where' values
     * (needed for INSERT and UPDATE).
     *
     * @return array
     *
     */
    public function getBindValues()
    {
        $bind_values = $this->bind_values;
        $i = 1;
        foreach ($this->bind_where as $val) {
            $bind_values[$i] = $val;
            $i ++;
        }
        return $bind_values;
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
     * Sets one column value placeholder; if an optional second parameter is
     * passed, that value is bound to the placeholder.
     *
     * @param string $col The column name.
     *
     * @param mixed $val Optional: a value to bind to the placeholder.
     *
     * @return $this
     *
     */
    protected function addCol($col)
    {
        $key = $this->quoter->quoteName($col);
        $this->col_values[$key] = ":$col";
        $args = func_get_args();
        if (count($args) > 1) {
            $this->bindValue($col, $args[1]);
        }
        return $this;
    }

    /**
     *
     * Sets multiple column value placeholders. If an element is a key-value
     * pair, the key is treated as the column name and the value is bound to
     * that column.
     *
     * @param array $cols A list of column names, optionally as key-value
     * pairs where the key is a column name and the value is a bind value for
     * that column.
     *
     * @return $this
     *
     */
    protected function addCols(array $cols)
    {
        foreach ($cols as $key => $val) {
            if (is_int($key)) {
                // integer key means the value is the column name
                $this->addCol($val);
            } else {
                // the key is the column name and the value is a value to
                // be bound to that column
                $this->addCol($key, $val);
            }
        }
        return $this;
    }

    /**
     *
     * Sets a column value directly; the value will not be escaped, although
     * fully-qualified identifiers in the value will be quoted.
     *
     * @param string $col The column name.
     *
     * @param string $value The column value expression.
     *
     * @return $this
     *
     */
    protected function setCol($col, $value)
    {
        if ($value === null) {
            $value = 'NULL';
        }

        $key = $this->quoter->quoteName($col);
        $value = $this->quoter->quoteNamesIn($value);
        $this->col_values[$key] = $value;
        return $this;
    }

    /**
     *
     * Builds the inserted columns and values of the statement.
     *
     * @return string
     *
     */
    protected function buildValuesForInsert()
    {
        return ' ('
            . $this->indentCsv(array_keys($this->col_values))
            . PHP_EOL . ') VALUES ('
            . $this->indentCsv(array_values($this->col_values))
            . PHP_EOL . ')';
    }

    /**
     *
     * Builds the updated columns and values of the statement.
     *
     * @return string
     *
     */
    protected function buildValuesForUpdate()
    {
        $values = array();
        foreach ($this->col_values as $col => $value) {
            $values[] = "{$col} = {$value}";
        }
        return PHP_EOL . 'SET' . $this->indentCsv($values);
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

    /**
     *
     * Adds returning columns to the query.
     *
     * Multiple calls to returning() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array $cols The column(s) to add to the query.
     *
     * @return $this
     *
     */
    protected function addReturning(array $cols)
    {
        foreach ($cols as $col) {
            $this->returning[] = $this->quoter->quoteNamesIn($col);
        }
        return $this;
    }

    /**
     *
     * Builds the `RETURNING` clause of the statement.
     *
     * @return string
     *
     */
    protected function buildReturning()
    {
        if (! $this->returning) {
            return ''; // not applicable
        }

        return PHP_EOL . 'RETURNING' . $this->indentCsv($this->returning);
    }
}
