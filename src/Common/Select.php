<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\AbstractQuery;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractQuery implements SelectInterface
{
    use Traits\LimitOffsetTrait;
    use Traits\OrderByTrait;
    use Traits\WhereTrait;

    // the statement being built
    protected $stm;
    
    /**
     *
     * An array of union SELECT statements.
     *
     * @var array
     *
     */
    protected $union = [];

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
    protected $cols = [];

    /**
     *
     * Select from these tables.
     *
     * @var array
     *
     */
    protected $from = [];

    /**
     *
     * Use these joins.
     *
     * @var array
     *
     */
    protected $join = [];

    /**
     *
     * GROUP BY these columns.
     *
     * @var array
     *
     */
    protected $group_by = [];

    /**
     *
     * The list of HAVING conditions.
     *
     * @var array
     *
     */
    protected $having = [];

    protected $bind_having = [];
    
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
     * @return $this
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
     * @return $this
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
     * @return $this
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
     * @param array $cols The column(s) to add to the query.
     *
     * @return $this
     *
     */
    public function cols(array $cols)
    {
        foreach ($cols as $col) {
            $this->cols[] = $this->quoteNamesIn($col);
        }
        return $this;
    }

    /**
     *
     * Adds a FROM table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @return $this
     *
     */
    public function from($spec)
    {
        $this->from[] = [
            $this->quoteName($spec)
        ];
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
     * @return $this
     *
     */
    public function fromSubSelect($spec, $name)
    {
        $spec = ltrim(preg_replace('/^/m', '        ', (string) $spec));
        $this->from[] = [
            "("
            . PHP_EOL . '        ' . $spec . PHP_EOL
            . "    ) AS " . $this->quoteName($name)
        ];
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
     * @return $this
     *
     */
    public function join($join, $spec, $cond = null)
    {
        $join = strtoupper(ltrim("$join JOIN"));
        $spec = $this->quoteName($spec);
        if ($cond) {
            $cond = $this->quoteNamesIn($cond);
            $joinStatement = "$join $spec ON $cond";
        } else {
            $joinStatement = "$join $spec";
        }

        $this->join[] = $joinStatement;

        //connect to latest from statement
        $fromCount = count($this->from);
        if ($fromCount) {
            $this->from[$fromCount - 1][] = $joinStatement;
        }

        return $this;
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
     * @return $this
     *
     */
    public function joinSubSelect($join, $spec, $name, $cond = null)
    {
        $join = strtoupper(ltrim("$join JOIN"));
        $spec = PHP_EOL . '    '
              . ltrim(preg_replace('/^/m', '    ', (string) $spec))
              . PHP_EOL;
        $name = $this->quoteName($name);
        if ($cond) {
            $cond = $this->quoteNamesIn($cond);
            $joinStatement = "$join ($spec) AS $name ON $cond";
        } else {
            $joinStatement = "$join ($spec) AS $name";
        }

        $this->join[] = $joinStatement;

        //connect to latest from statement
        $fromCount = count($this->from);
        if ($fromCount) {
            $this->from[$fromCount - 1][] = $joinStatement;
        }

        return $this;
    }

    /**
     *
     * Adds grouping to the query.
     *
     * @param array $spec The column(s) to group by.
     *
     * @return $this
     *
     */
    public function groupBy(array $spec)
    {
        foreach ($spec as $col) {
            $this->group_by[] = $this->quoteNamesIn($col);
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
     * @return $this
     *
     */
    public function having($cond)
    {
        // quote names in the condition
        $cond = $this->quoteNamesIn($cond);
        
        // bind values to the condition
        $bind = func_get_args();
        array_shift($bind);
        foreach ($bind as $value) {
            $this->bind_having[] = $value;
        }

        if ($this->having) {
            $this->having[] = "AND $cond";
        } else {
            $this->having[] = $cond;
        }

        // done
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
     * @return $this
     *
     * @see having()
     *
     */
    public function orHaving($cond)
    {
        // quote names in the condition
        $cond = $this->quoteNamesIn($cond);
        
        // bind values to the condition
        $bind = func_get_args();
        array_shift($bind);
        foreach ($bind as $value) {
            $this->bind_having[] = $value;
        }

        if ($this->having) {
            $this->having[] = "OR $cond";
        } else {
            $this->having[] = $cond;
        }

        // done
        return $this;
    }

    /**
     *
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     *
     * @return $this
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
     * @return $this
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
     * @return $this
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
        $this->cols       = [];
        $this->from       = [];
        $this->join       = [];
        $this->where      = [];
        $this->group_by   = [];
        $this->having     = [];
        $this->order_by   = [];
        $this->limit      = 0;
        $this->offset     = 0;
        $this->for_update = false;
    }
    
    protected function build()
    {
        $this->stm = 'SELECT';
        $this->buildFlags();
        $this->buildCols();
        $this->buildFrom();
        $this->buildJoin();
        $this->buildWhere();
        $this->buildGroupBy();
        $this->buildHaving();
        $this->buildOrderBy();
        $this->buildLimit();
        $this->buildForUpdate();
        return $this->stm;
    }
    
    protected function buildCols()
    {
        if ($this->cols) {
            $this->stm .= $this->indentCsv($this->cols);
            return;
        }
    }
    
    protected function buildFrom()
    {
        if (count($this->from)) {
            $preparedFrom = [];
            foreach ($this->from as $from) {
                $preparedFrom[] = implode(PHP_EOL, $from);
            }
            $this->stm .= PHP_EOL . 'FROM' . $this->indentCsv($preparedFrom);
        }
    }
    
    protected function buildJoin()
    {
        if (count($this->from) === 0 && count($this->join) > 0) {
            foreach ($this->join as $join) {
                $this->stm .= PHP_EOL . $join;
            }
        }
    }
    
    protected function buildGroupBy()
    {
        if ($this->group_by) {
            $this->stm .= PHP_EOL . 'GROUP BY' . $this->indentCsv($this->group_by);
        }
    }
    
    protected function buildHaving()
    {
        if ($this->having) {
            $this->stm .= PHP_EOL . 'HAVING' . $this->indent($this->having);
        }
    }
    
    protected function buildForUpdate()
    {
        if ($this->for_update) {
            $this->stm .= PHP_EOL . 'FOR UPDATE';
        }
    }
}
