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
    protected $values;

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
    protected $quote_name_prefix = '"';

    /**
     * 
     * The suffix to use when quoting identifier names.
     * 
     * @var string
     * 
     */
    protected $quote_name_suffix = '"';

    /**
     * 
     * Constructor.
     * 
     * @param string $quote_name_prefix The prefix to use when quoting
     * identifier names.
     * 
     * @param string $quote_name_suffix The suffix to use when quoting
     * identifier names.
     *
     */
    public function __construct($quote_name_prefix, $quote_name_suffix)
    {
        $this->quote_name_prefix = $quote_name_prefix;
        $this->quote_name_suffix = $quote_name_suffix;
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
        return $this->quote_name_prefix;
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
        return $this->quote_name_suffix;
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
     * @return null
     * 
     */
    public function bindValues(array $bind_values)
    {
        // array_merge() renumbers integer keys, which is bad for
        // question-mark placeholders
        foreach ($bind_values as $key => $val) {
            $this->bindValue($key, $val);
        }
    }

    /**
     * 
     * Binds a single value to the query.
     * 
     * @param string $name The placeholder name or number.
     * 
     * @param mixed $value The value to bind to the placeholder.
     * 
     * @return null
     * 
     */
    public function bindValue($name, $value)
    {
        $this->bind_values[$name] = $value;
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
     * Quotes a single identifier name (table, table alias, table column, 
     * index, sequence).
     * 
     * If the name contains `' AS '`, this method will separately quote the
     * parts before and after the `' AS '`.
     * 
     * If the name contains a space, this method will separately quote the
     * parts before and after the space.
     * 
     * If the name contains a dot, this method will separately quote the
     * parts before and after the dot.
     * 
     * @param string $spec The identifier name to quote.
     * 
     * @return string|array The quoted identifier name.
     * 
     * @see replaceName()
     * 
     */
    protected function quoteName($spec)
    {
        // remove extraneous spaces
        $spec = trim($spec);

        // `original` AS `alias` ... note the 'rr' in strripos
        $pos = strripos($spec, ' AS ');
        if ($pos) {
            // recurse to allow for "table.col"
            $orig  = $this->quoteName(substr($spec, 0, $pos));
            // use as-is
            $alias = $this->replaceName(substr($spec, $pos + 4));
            // done
            return "$orig AS $alias";
        }

        // `original` `alias`
        $pos = strrpos($spec, ' ');
        if ($pos) {
            // recurse to allow for "table.col"
            $orig = $this->quoteName(substr($spec, 0, $pos));
            // use as-is
            $alias = $this->replaceName(substr($spec, $pos + 1));
            // done
            return "$orig $alias";
        }

        // `table`.`column`
        $pos = strrpos($spec, '.');
        if ($pos) {
            // use both as-is
            $table = $this->replaceName(substr($spec, 0, $pos));
            $col   = $this->replaceName(substr($spec, $pos + 1));
            return "$table.$col";
        }

        // `name`
        return $this->replaceName($spec);
    }

    /**
     * 
     * Quotes all fully-qualified identifier names ("table.col") in a string,
     * typically an SQL snippet for a SELECT clause.
     * 
     * Does not quote identifier names that are string literals (i.e., inside
     * single or double quotes).
     * 
     * Looks for a trailing ' AS alias' and quotes the alias as well.
     * 
     * @param string $text The string in which to quote fully-qualified
     * identifier names to quote.
     * 
     * @return string|array The string with names quoted in it.
     * 
     * @see replaceNamesIn()
     * 
     */
    protected function quoteNamesIn($text)
    {
        // single and double quotes
        $apos = "'";
        $quot = '"';

        // look for ', ", \', or \" in the string.
        // match closing quotes against the same number of opening quotes.
        $list = preg_split(
            "/(($apos+|$quot+|\\$apos+|\\$quot+).*?\\2)/",
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        // concat the pieces back together, quoting names as we go.
        $text = null;
        $last = count($list) - 1;
        foreach ($list as $key => $val) {

            // skip elements 2, 5, 8, 11, etc. as artifacts of the back-
            // referenced split; these are the trailing/ending quote
            // portions, and already included in the previous element.
            // this is the same as every third element from zero.
            if (($key+1) % 3 == 0) {
                continue;
            }

            // is there an apos or quot anywhere in the part?
            $is_string = strpos($val, $apos) !== false ||
                         strpos($val, $quot) !== false;

            if ($is_string) {
                // string literal
                $text .= $val;
            } else {
                // sql language.
                // look for an AS alias if this is the last element.
                if ($key == $last) {
                    // note the 'rr' in strripos
                    $pos = strripos($val, ' AS ');
                    if ($pos) {
                        // quote the alias name directly
                        $alias = $this->replaceName(substr($val, $pos + 4));
                        $val = substr($val, 0, $pos) . " AS $alias";
                    }
                }

                // now quote names in the language.
                $text .= $this->replaceNamesIn($val);
            }
        }

        // done!
        return $text;
    }

    /**
     * 
     * Quotes an identifier name (table, index, etc); ignores empty values and
     * values of '*'.
     * 
     * @param string $name The identifier name to quote.
     * 
     * @return string The quoted identifier name.
     * 
     * @see quoteName()
     * 
     */
    protected function replaceName($name)
    {
        $name = trim($name);
        if ($name == '*') {
            return $name;
        } else {
            return $this->quote_name_prefix
                 . $name
                 . $this->quote_name_suffix;
        }
    }

    /**
     * 
     * Quotes all fully-qualified identifier names ("table.col") in a string.
     * 
     * @param string $text The string in which to quote fully-qualified
     * identifier names to quote.
     * 
     * @return string|array The string with names quoted in it.
     * 
     * @see quoteNamesIn()
     * 
     */
    protected function replaceNamesIn($text)
    {
        $word = "[a-z_][a-z0-9_]+";

        $find = "/(\\b)($word)\\.($word)(\\b)/i";

        $repl = '$1'
              . $this->quote_name_prefix
              . '$2'
              . $this->quote_name_suffix
              . '.'
              . $this->quote_name_prefix
              . '$3'
              . $this->quote_name_suffix
              . '$4'
              ;

        $text = preg_replace($find, $repl, $text);

        return $text;
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
    protected function addWhere($cond, $op, array $bind)
    {
        // quote names in the condition
        $cond = $this->quoteNamesIn($cond);

        // bind values to the condition
        foreach ($bind as $value) {
            $this->bind_where[] = $value;
        }

        if ($this->where) {
            $this->where[] = "$op $cond";
        } else {
            $this->where[] = $cond;
        }

        // done
        return $this;
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
        $key = $this->quoteName($col);
        $this->values[$key] = ":$col";
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

        $key = $this->quoteName($col);
        $value = $this->quoteNamesIn($value);
        $this->values[$key] = $value;
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
            . $this->indentCsv(array_keys($this->values))
            . PHP_EOL . ') VALUES ('
            . $this->indentCsv(array_values($this->values))
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
        foreach ($this->values as $col => $value) {
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
            $this->order_by[] = $this->quoteNamesIn($col);
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
            $this->returning[] = $this->quoteNamesIn($col);
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
