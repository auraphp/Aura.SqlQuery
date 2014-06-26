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
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQuery;
use Aura\SqlQuery\Exception;

/**
 *
 * An object for SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Select extends AbstractQuery implements SelectInterface
{
    /**
     *
     * An array of union SELECT statements.
     *
     * @var array
     *
     */
    protected $union = array();

    /**
     *
     * Is this a SELECT FOR UPDATE?
     *
     * @var
     *
     */
    protected $for_update = false;

    /**
     *
     * The columns to be selected.
     *
     * @var array
     *
     */
    protected $cols = array();

    /**
     *
     * Select from these tables; includes JOIN clauses.
     *
     * @var array
     *
     */
    protected $from = array();

    /**
     *
     * The current key in the `$from` array.
     *
     * @var int
     *
     */
    protected $from_key = -1;

    /**
     *
     * GROUP BY these columns.
     *
     * @var array
     *
     */
    protected $group_by = array();

    /**
     *
     * The list of HAVING conditions.
     *
     * @var array
     *
     */
    protected $having = array();

    /**
     *
     * Bind values in the HAVING clause.
     *
     * @var array
     *
     */
    protected $bind_having = array();

    /**
     *
     * The number of rows per page.
     *
     * @var int
     *
     */
    protected $paging = 10;

    /**
     *
     * Returns this object as an SQL statement string.
     *
     * @return string An SQL statement string.
     *
     */
    public function __toString()
    {
        $union = '';
        if ($this->union) {
            $union = implode(PHP_EOL, $this->union) . PHP_EOL;
        }
        return $union . $this->build();
    }

    /**
     *
     * Sets the number of rows per page.
     *
     * @param int $paging The number of rows to page at.
     *
     * @return self
     *
     */
    public function setPaging($paging)
    {
        $this->paging = (int) $paging;
        return $this;
    }

    /**
     *
     * Gets the number of rows per page.
     *
     * @return int The number of rows per page.
     *
     */
    public function getPaging()
    {
        return $this->paging;
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
        $bind_values = $this->bind_values;
        $i = 1;
        foreach ($this->bind_where as $val) {
            $bind_values[$i] = $val;
            $i ++;
        }
        foreach ($this->bind_having as $val) {
            $bind_values[$i] = $val;
            $i ++;
        }
        return $bind_values;
    }

    /**
     *
     * Makes the select FOR UPDATE (or not).
     *
     * @param bool $enable Whether or not the SELECT is FOR UPDATE (default
     * true).
     *
     * @return self
     *
     */
    public function forUpdate($enable = true)
    {
        $this->for_update = (bool) $enable;
    }

    /**
     *
     * Makes the select DISTINCT (or not).
     *
     * @param bool $enable Whether or not the SELECT is DISTINCT (default
     * true).
     *
     * @return self
     *
     */
    public function distinct($enable = true)
    {
        $this->setFlag('DISTINCT', $enable);
        return $this;
    }

    /**
     *
     * Adds columns to the query.
     *
     * Multiple calls to cols() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array $cols The column(s) to add to the query. The elements can be
     * any mix of these: `array("col", "col AS alias", "col" => "alias")`
     *
     * @return self
     *
     */
    public function cols(array $cols)
    {
        foreach ($cols as $key => $val) {
            if (is_int($key)) {
                list($key, $val) = $this->createAlias($key, $val);
            }
            $this->addCol($key, $val);
        }
        return $this;
    }
    
    /**
     *
     * Break down an AS string in to a column and alias
     *
     * @param int $key the original index position
     *
     * @param string $val the column identifer to try and break down
     * 
     * @return array either col => alias or $col
     */
    protected function createAlias($key, $val)
    {
        $test = explode(' ', $val);
        
        // well need at least three parts: 1. col, 2. AS, 3. the alias
        if (count($test) < 3) {
            return [$key, $val];
        }
        
        $alias  = array_pop($test);
        $as     = array_pop($test);

        return strtolower($as) == 'as'
            ? [$alias, implode(' ', $test)] : [$key, $val];
    }

    /**
     *
     * Adds a column and alias to the columsn to be selected.
     *
     * @param mixed string | int $key  If string, the column alias
     *
     * @param mixed $val column to add
     *
     */
    protected function addCol($key, $val)
    {
        $this->cols[$key] = $val;
    }

    /**
     *
     * Adds a FROM table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @return self
     *
     */
    public function from($spec)
    {
        $this->from[] = array($this->quoter->quoteName($spec));
        $this->from_key ++;
        return $this;
    }

    /**
     *
     * Adds an aliased sub-select to the query.
     *
     * @param string|Select $spec If a Select object, use as the sub-select;
     * if a string, the sub-select string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @return self
     *
     */
    public function fromSubSelect($spec, $name)
    {
        $spec = ltrim(preg_replace('/^/m', '        ', (string) $spec));
        $this->from[] = array(
            "("
            . PHP_EOL . '        ' . $spec . PHP_EOL
            . "    ) AS " . $this->quoter->quoteName($name)
        );
        $this->from_key ++;
        return $this;
    }

    /**
     *
     * Adds a JOIN table and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string $cond Join on this condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function join($join, $spec, $cond = null)
    {
        if (! $this->from) {
            throw new Exception('Cannot join() without from() first.');
        }

        $join = strtoupper(ltrim("$join JOIN"));
        $spec = $this->quoter->quoteName($spec);
        $cond = $this->fixJoinCondition($cond);
        $this->from[$this->from_key][] = rtrim("$join $spec $cond");
        return $this;
    }

    /**
     *
     * Fixes a JOIN condition to quote names in the condition and prefix it
     * with a condition type ('ON' is the default and 'USING' is recognized).
     *
     * @param string $cond Join on this condition.
     *
     * @return string
     *
     */
    protected function fixJoinCondition($cond)
    {
        if (! $cond) {
            return;
        }

        $cond = $this->quoter->quoteNamesIn($cond);

        if (strtoupper(substr(ltrim($cond), 0, 3)) == 'ON ') {
            return $cond;
        }

        if (strtoupper(substr(ltrim($cond), 0, 6)) == 'USING ') {
            return $cond;
        }

        return 'ON ' . $cond;
    }

    /**
     *
     * Adds a INNER JOIN table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string $cond Join on this condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function innerJoin($spec, $cond = null)
    {
        return $this->join('INNER', $spec, $cond);
    }

    /**
     *
     * Adds a LEFT JOIN table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string $cond Join on this condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function leftJoin($spec, $cond = null)
    {
        return $this->join('LEFT', $spec, $cond);
    }

    /**
     *
     * Adds a JOIN to an aliased subselect and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string|Select $spec If a Select
     * object, use as the sub-select; if a string, the sub-select
     * command string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @param string $cond Join on this condition.
     *
     * @return self
     *
     * @throws Exception
     *
     */
    public function joinSubSelect($join, $spec, $name, $cond = null)
    {
        if (! $this->from) {
            throw new Exception('Cannot join() without from() first.');
        }

        $join = strtoupper(ltrim("$join JOIN"));
        $spec = PHP_EOL . '    '
              . ltrim(preg_replace('/^/m', '    ', (string) $spec))
              . PHP_EOL;
        $name = $this->quoter->quoteName($name);

        $cond = $this->fixJoinCondition($cond);
        $this->from[$this->from_key][] = rtrim("$join ($spec) AS $name $cond");
        return $this;
    }

    /**
     *
     * Adds grouping to the query.
     *
     * @param array $spec The column(s) to group by.
     *
     * @return self
     *
     */
    public function groupBy(array $spec)
    {
        foreach ($spec as $col) {
            $this->group_by[] = $this->quoter->quoteNamesIn($col);
        }
        return $this;
    }

    /**
     *
     * Adds a HAVING condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The HAVING condition.
     *
     * @return self
     *
     */
    public function having($cond)
    {
        $this->addClauseCondWithBind('having', 'AND', func_get_args());
        return $this;
    }

    /**
     *
     * Adds a HAVING condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The HAVING condition.
     *
     * @return self
     *
     * @see having()
     *
     */
    public function orHaving($cond)
    {
        $this->addClauseCondWithBind('having', 'OR', func_get_args());
        return $this;
    }

    /**
     *
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     *
     * @return self
     *
     */
    public function page($page)
    {
        // reset the count and offset
        $this->limit  = 0;
        $this->offset = 0;

        // determine the count and offset from the page number
        $page = (int) $page;
        if ($page > 0) {
            $this->limit  = $this->paging;
            $this->offset = $this->paging * ($page - 1);
        }

        // done
        return $this;
    }

    /**
     *
     * Takes the current select properties and retains them, then sets
     * UNION for the next set of properties.
     *
     * @return self
     *
     */
    public function union()
    {
        $this->union[] = $this->build() . PHP_EOL . 'UNION';
        $this->reset();
        return $this;
    }

    /**
     *
     * Takes the current select properties and retains them, then sets
     * UNION ALL for the next set of properties.
     *
     * @return self
     *
     */
    public function unionAll()
    {
        $this->union[] = $this->build() . PHP_EOL . 'UNION ALL';
        $this->reset();
        return $this;
    }

    /**
     *
     * Clears the current select properties; generally used after adding a
     * union.
     *
     * @return null
     *
     */
    protected function reset()
    {
        $this->resetFlags();
        $this->cols       = array();
        $this->from       = array();
        $this->from_key   = -1;
        $this->where      = array();
        $this->group_by   = array();
        $this->having     = array();
        $this->order_by   = array();
        $this->limit      = 0;
        $this->offset     = 0;
        $this->for_update = false;
    }

    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     */
    protected function build()
    {
        return 'SELECT'
            . $this->buildFlags()
            . $this->buildCols()
            . $this->buildFrom() // includes JOIN
            . $this->buildWhere()
            . $this->buildGroupBy()
            . $this->buildHaving()
            . $this->buildOrderBy()
            . $this->buildLimit()
            . $this->buildForUpdate();
    }

    /**
     *
     * Builds the columns clause.
     *
     * @return string
     *
     * @throws Exception when there are no columns in the SELECT.
     *
     */
    protected function buildCols()
    {
        if (! $this->cols) {
            throw new Exception('No columns in the SELECT.');
        }
       
        foreach ($this->cols as $key => $val) {
            if (is_int($key)) {
                $cols[] = $this->quoter->quoteNamesIn($val);
            } else {
                $cols[] = $this->quoter->quoteNamesIn("$key AS $val");
            }
        }
        return $this->indentCsv($cols);
    }

    /**
     *
     * Builds the FROM clause.
     *
     * @return string
     *
     */
    protected function buildFrom()
    {
        if (! $this->from) {
            return ''; // not applicable
        }

        $refs = array();
        foreach ($this->from as $from) {
            $refs[] = implode(PHP_EOL, $from);
        }
        return PHP_EOL . 'FROM' . $this->indentCsv($refs);
    }

    /**
     *
     * Builds the GROUP BY clause.
     *
     * @return string
     *
     */
    protected function buildGroupBy()
    {
        if (! $this->group_by) {
            return ''; // not applicable
        }

        return PHP_EOL . 'GROUP BY' . $this->indentCsv($this->group_by);
    }

    /**
     *
     * Builds the HAVING clause.
     *
     * @return string
     *
     */
    protected function buildHaving()
    {
        if (! $this->having) {
            return ''; // not applicable
        }

        return PHP_EOL . 'HAVING' . $this->indent($this->having);
    }

    /**
     *
     * Builds the FOR UPDATE clause.
     *
     * @return string
     *
     */
    protected function buildForUpdate()
    {
        if (! $this->for_update) {
            return ''; // not applicable
        }

        return PHP_EOL . 'FOR UPDATE';
    }

    /**
     *
     * Adds a WHERE condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return self
     *
     */
    public function where($cond)
    {
        $this->addWhere('AND', func_get_args());
        return $this;
    }

    /**
     *
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     * @param mixed ...$bind arguments to bind to placeholders
     *
     * @return self
     *
     * @see where()
     *
     */
    public function orWhere($cond)
    {
        $this->addWhere('OR', func_get_args());
        return $this;
    }

    /**
     *
     * Sets a limit count on the query.
     *
     * @param int $limit The number of rows to select.
     *
     * @return self
     *
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     *
     * Sets a limit offset on the query.
     *
     * @param int $offset Start returning after this many rows.
     *
     * @return self
     *
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return self
     *
     */
    public function orderBy(array $spec)
    {
        return $this->addOrderBy($spec);
    }
}
