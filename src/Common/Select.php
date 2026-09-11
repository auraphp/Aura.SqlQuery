<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQuery;
use Aura\SqlQuery\Exception\LogicException;

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
     * A builder for the query.
     *
     * @var SelectBuilder
     *
     */
    protected $builder;

    use WhereTrait;
    use TableListTrait;
    use LimitOffsetTrait { limit as setLimit; offset as setOffset; }
    use WithTrait;

    /**
     *
     * An array of union SELECT statements.
     *
     * @var list<string>
     *
     */
    protected $union = [];

    /**
     *
     * Regex alternatives matching the SQL that spells no placeholder: string
     * literals and comments, in the forms this dialect reads them.
     *
     * A quote inside a literal is written by doubling it, and a comment runs
     * to the end of its line or to the close of its block. Dialects that read
     * more than this -- MySQL, whose backslash escapes the quote after it and
     * whose hash begins a comment -- say so by overriding this; the reading
     * here is the standard one, so a backslash is an ordinary character and a
     * literal ends at the next lone quote whatever precedes it.
     *
     * @var string
     *
     * @see resetAfterRendering()
     *
     */
    protected $text_pattern = "'(?:[^']|'')*+'|--[^\n]*|\/\*.*?\*\/";

    /**
     *
     * The last branch of a union when it was supplied as a query of its own,
     * already rendered; null when this query is building that branch itself.
     *
     * @var string|null
     *
     */
    protected $union_tail = null;

    /**
     *
     * Is this a SELECT FOR UPDATE?
     *
     * @var bool
     *
     */
    protected $for_update = false;

    /**
     *
     * The columns to be selected.
     *
     * @var array<int|string, string>
     *
     */
    protected $cols = [];

    /**
     *
     * Select from these tables; includes JOIN clauses.
     *
     * @var list<list<string>>
     *
     */
    protected $from = [];

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
     * Tracks which JOIN clauses are attached to which FROM tables.
     *
     * @var array<int, list<string>>
     *
     */
    protected $join = [];

    /**
     *
     * GROUP BY these columns.
     *
     * @var list<string>
     *
     */
    protected $group_by = [];

    /**
     *
     * The list of HAVING conditions.
     *
     * @var list<string>
     *
     */
    protected $having = [];

    /**
     *
     * The page number to select.
     *
     * @var int
     *
     */
    protected $page = 0;

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
     * Tracks table references to avoid duplicate identifiers.
     *
     * @var array<string, string>
     *
     */
    protected $table_refs = [];

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * @return string An SQL statement string.
     *
     */
    public function getStatement()
    {
        // the WITH clause belongs to the statement, not to a branch of it:
        // it is written once, at the top, and every branch of the union
        // below may name the CTEs it defines. It is prefixed here rather
        // than in build() for that reason, and because build() is what the
        // dialects post-process -- SQL Server injects its TOP by matching
        // SELECT at the head of the string a branch renders to.
        $with = $this->builder->buildWith($this->with, $this->with_recursive);

        $union = '';
        if (! empty($this->union)) {
            $union = implode(PHP_EOL, $this->union) . PHP_EOL;
        }

        if ($this->union_tail !== null) {
            $this->assertNoBranchAfterUnionTail();
            return $with . $union . $this->union_tail;
        }

        return $with . $union . $this->build();
    }

    /**
     *
     * Reports properties set on this query after a branch was supplied whole,
     * which the union has no place to render.
     *
     * The supplied branch is the last one, and anything set afterwards can
     * only mean a further branch -- one this query cannot render, because the
     * SQL already retained ends at that branch with no UNION to carry on from.
     * Whether that next branch is UNION or UNION ALL is the caller's to say,
     * so the fix is to say it, and the alternative is to drop a WHERE, a JOIN,
     * or a LIMIT from the statement without a word.
     *
     * What counts as "set afterwards" is asked of reset(), which is what
     * union() itself leaves this query in and so is the one description of an
     * empty branch: every property it clears is compared against a copy that
     * has just been through it. Naming the clauses here instead would leave
     * the next one added to the statement unguarded, which is how columns came
     * to be the only property this checked.
     *
     * The bound values are not among them, and are left where they are:
     * they belong to the union rather than to the branch being built -- that
     * is what lets the rendered SQL keep its placeholders -- so binding a
     * fresh value for the branch already rendered is no more a new branch
     * than reading the statement twice is. A clause that claims a name is
     * caught as the clause it is.
     *
     * @return void
     *
     * @throws LogicException when this query holds a branch of its own.
     *
     */
    protected function assertNoBranchAfterUnionTail()
    {
        $empty = clone $this;
        $empty->reset();

        foreach (get_object_vars($empty) as $key => $value) {
            if ($this->$key === $value) {
                continue;
            }

            throw new LogicException(
                'The query passed to union() is the last branch; '
                . 'call union() or unionAll() again to add another after it.'
            );
        }
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
    public function setPaging(int $paging)
    {
        $this->paging = $paging;
        if ($this->page) {
            $this->setPagingLimitOffset();
        }
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
     * Makes the select FOR UPDATE (or not).
     *
     * @param bool $enable Whether or not the SELECT is FOR UPDATE (default
     * true).
     *
     * @return $this
     *
     */
    public function forUpdate(bool $enable = true)
    {
        $this->for_update = $enable;
        return $this;
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
    public function distinct(bool $enable = true)
    {
        $this->setFlag('DISTINCT', $enable);
        return $this;
    }

    /**
     *
     * Is the select DISTINCT?
     *
     * @return bool
     *
     */
    public function isDistinct()
    {
        return $this->hasFlag('DISTINCT');
    }

    /**
     *
     * Adds columns to the query.
     *
     * Multiple calls to cols() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array<int|string, string> $cols The column(s) to add to the
     * query. The elements can be any mix of these:
     * `array("col", "col AS alias", "col" => "alias")`
     *
     * @return $this
     *
     */
    public function cols(array $cols)
    {
        foreach ($cols as $key => $val) {
            $this->addCol($key, $val);
        }
        return $this;
    }

    /**
     *
     * Adds a column and alias to the columns to be selected.
     *
     * @param mixed $key If an integer, ignored. Otherwise, the column to be
     * added.
     *
     * @param mixed $val If $key was an integer, the column to be added;
     * otherwise, the column alias.
     *
     * @return void
     *
     */
    protected function addCol(mixed $key, mixed $val)
    {
        if (is_string($key)) {
            // [col => alias]
            $this->cols[$val] = $key;
        } else {
            $this->addColWithAlias($val);
        }
    }

    /**
     *
     * Adds a column with an alias to the columns to be selected.
     *
     * @param string $spec The column specification: "col alias",
     * "col AS alias", or something else entirely.
     *
     * @return void
     *
     */
    protected function addColWithAlias(string $spec)
    {
        $parts = explode(' ', $spec);
        $count = count($parts);
        if ($count == 2 && $this->isCompleteExpr($parts[0])) {
            // "col alias"
            $this->cols[$parts[1]] = $parts[0];
        } elseif ($count == 3 && strtoupper($parts[1]) == 'AS' && $this->isCompleteExpr($parts[0])) {
            // "col AS alias"
            $this->cols[$parts[2]] = $parts[0];
        } else {
            // no recognized alias
            $this->cols[] = $spec;
        }
    }

    /**
     *
     * Is this a whole column expression, rather than the head of one that a
     * space inside parentheses has cut in two?
     *
     * issue #226: 'COUNT(DISTINCT t.c)' is two space-separated words, but
     * 'COUNT(DISTINCT' is not a column name and 't.c)' is not an alias.
     *
     * Parentheses inside a string literal are data, not syntax, so the scan
     * skips over literals: "CONCAT('(',t.c) alias" is complete, and its
     * alias is still an alias.
     *
     * @param string $expr The leading word of a column specification.
     *
     * @return bool
     *
     */
    protected function isCompleteExpr(string $expr)
    {
        $depth = 0;
        $quote = null;
        $len = strlen($expr);

        for ($i = 0; $i < $len; $i ++) {
            $char = $expr[$i];

            if ($quote !== null) {
                // a backslash escapes the next character on MySQL; on
                // PostgreSQL, with standard_conforming_strings on, it does
                // not. Either way an unterminated literal returns false and
                // the caller keeps the spec whole, so the cost of guessing
                // wrong is a missed alias, never malformed SQL.
                if ($char === '\\' && isset($expr[$i + 1])) {
                    $i ++;
                    continue;
                }

                // a doubled quote inside a literal is an escaped one, not
                // the end of the literal
                if ($char === $quote && isset($expr[$i + 1]) && $expr[$i + 1] === $quote) {
                    $i ++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                $depth ++;
            } elseif ($char === ')') {
                $depth --;
                // a close with nothing open before it is not a column name
                if ($depth < 0) {
                    return false;
                }
            }
        }

        return $depth === 0 && $quote === null;
    }

    /**
     *
     * Remove a column via its alias.
     *
     * @param string $alias The column to remove
     *
     * @return bool
     *
     */
    public function removeCol(string $alias)
    {
        if (isset($this->cols[$alias])) {
            unset($this->cols[$alias]);

            return true;
        }

        $index = array_search($alias, $this->cols);
        if ($index !== false) {
            unset($this->cols[$index]);
            return true;
        }

        return false;
    }

    /**
     *
     * Has the column or alias been added to the query?
     *
     * @param string $alias The column or alias to look for
     *
     * @return bool
     *
     */
    public function hasCol(string $alias)
    {
        return isset($this->cols[$alias]) || array_search($alias, $this->cols) !== false;
    }

    /**
     *
     * Does the query have any columns in it?
     *
     * @return bool
     *
     */
    public function hasCols()
    {
        return (bool) $this->cols;
    }

    /**
     *
     * Returns a list of columns.
     *
     * @return array<int|string, string>
     *
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     *
     * Tracks table references.
     *
     * @param string $type FROM, JOIN, etc.
     *
     * @param string $spec The table and alias name.
     *
     * @return void
     *
     * @throws LogicException when the reference has already been used.
     *
     */
    protected function addTableRef(string $type, string $spec)
    {
        $name = $spec;

        $pos = strripos($name, ' AS ');
        if ($pos !== false) {
            $name = trim(substr($name, $pos + 4));
        }

        if (isset($this->table_refs[$name])) {
            $used = $this->table_refs[$name];
            throw new LogicException("Cannot reference '$type $spec' after '$used'");
        }

        $this->table_refs[$name] = "$type $spec";
    }

    /**
     *
     * Adds a FROM element to the query; quotes the table name automatically.
     *
     * Several tables may be named at once, comma-separated, which is the
     * same list as calling this once per table -- each is quoted and
     * reference-checked on its own. Passing the list as one string used to
     * quote it whole, giving `"foo," "bar"`; see #160.
     *
     * @param string $spec The table specification; "foo", "foo AS bar", or
     * a comma-separated list of either.
     *
     * @return $this
     *
     */
    public function from(string $spec)
    {
        $names = $this->splitNamesList($spec);

        // an empty spec is nobody's list; leave it to quoteName() as before
        if (empty($names)) {
            $names = [$spec];
        }

        foreach ($names as $name) {
            $this->addTableRef('FROM', $name);
            $this->addFrom($this->quoter->quoteName($name));
        }

        return $this;
    }

    /**
     *
     * Adds a raw unquoted FROM element to the query; useful for adding FROM
     * elements that are functions.
     *
     * @param string $spec The table specification, e.g. "function_name()".
     *
     * @return $this
     *
     */
    public function fromRaw(string $spec)
    {
        $this->addTableRef('FROM', $spec);
        return $this->addFrom($spec);
    }

    /**
     *
     * Adds to the $from property and increments the key count.
     *
     * @param string $spec The table specification.
     *
     * @return $this
     *
     */
    protected function addFrom(string $spec)
    {
        $this->from[] = [$spec];
        $this->from_key ++;
        return $this;
    }

    /**
     *
     * Adds an aliased sub-select to the query.
     *
     * @param string|SelectInterface $spec If a Select object, use as the sub-select;
     * if a string, the sub-select string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @return $this
     *
     */
    public function fromSubSelect(string|SelectInterface $spec, string $name)
    {
        $this->addTableRef('FROM (SELECT ...) AS', $name);
        $spec = $this->subSelect($spec, '        ');
        $name = $this->quoter->quoteName($name);
        return $this->addFrom("({$spec}    ) AS $name");
    }

    /**
     *
     * Adds a JOIN table and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string|null $cond Join on this condition.
     *
     * @param array<int|string, mixed> $bind Values to bind to
     * ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws LogicException
     *
     */
    public function join(string $join, string $spec, ?string $cond = null, array $bind = [])
    {
        $join = strtoupper(ltrim("$join JOIN"));
        $this->addTableRef($join, $spec);

        $spec = $this->quoter->quoteName($spec);
        $cond = $this->fixJoinCondition($cond, $bind);
        return $this->addJoin(rtrim("$join $spec $cond"));
    }

    /**
     *
     * Fixes a JOIN condition to quote names in the condition and prefix it
     * with a condition type ('ON' is the default and 'USING' is recognized).
     *
     * @param string|null $cond Join on this condition.
     *
     * @param array<int|string, mixed> $bind Values to bind to
     * ?-placeholders in the condition.
     *
     * @return string
     *
     */
    protected function fixJoinCondition(?string $cond, array $bind)
    {
        if (! $cond) {
            return '';
        }

        $cond = $this->quoter->quoteNamesIn($cond);
        $cond = $this->rebuildCondAndBindValues($cond, $bind, 'join');

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
     * @param string|null $cond Join on this condition.
     *
     * @param array<int|string, mixed> $bind Values to bind to
     * ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws LogicException
     *
     */
    public function innerJoin(string $spec, ?string $cond = null, array $bind = [])
    {
        return $this->join('INNER', $spec, $cond, $bind);
    }

    /**
     *
     * Adds a LEFT JOIN table and columns to the query.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @param string|null $cond Join on this condition.
     *
     * @param array<int|string, mixed> $bind Values to bind to
     * ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws LogicException
     *
     */
    public function leftJoin(string $spec, ?string $cond = null, array $bind = [])
    {
        return $this->join('LEFT', $spec, $cond, $bind);
    }

    /**
     *
     * Adds a JOIN to an aliased subselect and columns to the query.
     *
     * @param string $join The join type: inner, left, natural, etc.
     *
     * @param string|SelectInterface $spec If a Select
     * object, use as the sub-select; if a string, the sub-select
     * command string.
     *
     * @param string $name The alias name for the sub-select.
     *
     * @param string|null $cond Join on this condition.
     *
     * @param array<int|string, mixed> $bind Values to bind to
     * ?-placeholders in the condition.
     *
     * @return $this
     *
     * @throws LogicException
     *
     */
    public function joinSubSelect(string $join, string|SelectInterface $spec, string $name, ?string $cond = null, array $bind = [])
    {
        $join = strtoupper(ltrim("$join JOIN"));
        $this->addTableRef("$join (SELECT ...) AS", $name);

        $spec = $this->subSelect($spec, '            ', 'join');
        $name = $this->quoter->quoteName($name);
        $cond = $this->fixJoinCondition($cond, $bind);

        $text = rtrim("$join ($spec        ) AS $name $cond");
        return $this->addJoin('        ' . $text);
    }

    /**
     *
     * Adds the JOIN to the right place, given whether or not a FROM has been
     * specified yet.
     *
     * @param string $spec The JOIN clause.
     *
     * @return $this
     *
     */
    protected function addJoin(string $spec)
    {
        $from_key = ($this->from_key == -1) ? 0 : $this->from_key;
        $this->join[$from_key][] = $spec;
        return $this;
    }

    /**
     *
     * Adds grouping to the query.
     *
     * @param array<array-key, string> $spec The column(s) to group by.
     *
     * @return $this
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
     * Adds a HAVING condition to the query by AND.
     *
     * @param string|\Closure $cond The HAVING condition.
     *
     * @param array<int|string, mixed> $bind arguments to bind to
     * placeholders
     *
     * @return $this
     *
     */
    public function having(string|\Closure $cond, array $bind = [])
    {
        $this->addClauseCondWithBind('having', 'AND', $cond, $bind);
        return $this;
    }

    /**
     *
     * Adds a HAVING condition to the query by OR.
     *
     * @param string|\Closure $cond The HAVING condition.
     *
     * @param array<int|string, mixed> $bind arguments to bind to
     * placeholders
     *
     * @return $this
     *
     * @see having()
     *
     */
    public function orHaving(string|\Closure $cond, array $bind = [])
    {
        $this->addClauseCondWithBind('having', 'OR', $cond, $bind);
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
    public function page(int $page)
    {
        $this->page = $page;
        $this->setPagingLimitOffset();
        return $this;
    }

    /**
     *
     * Updates the limit and offset values when changing pagination.
     *
     * @return void
     *
     */
    protected function setPagingLimitOffset()
    {
        $this->setLimit(0);
        $this->setOffset(0);
        if ($this->page) {
            $this->setLimit($this->paging);
            $this->setOffset($this->paging * ($this->page - 1));
        }
    }

    /**
     *
     * Returns the page number being selected.
     *
     * @return int
     *
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     *
     * Takes the current select properties and retains them, then sets
     * UNION for the next set of properties.
     *
     * @param SelectInterface|null $select The next branch as a query of its
     * own; when omitted, this query is reset to build that branch itself.
     *
     * @return $this
     *
     * @throws LogicException when handed this very query, or a branch
     * defining a WITH clause of its own.
     *
     */
    public function union(?SelectInterface $select = null)
    {
        return $this->addUnion('UNION', $select);
    }

    /**
     *
     * Takes the current select properties and retains them, then sets
     * UNION ALL for the next set of properties.
     *
     * @param SelectInterface|null $select The next branch as a query of its
     * own; when omitted, this query is reset to build that branch itself.
     *
     * @return $this
     *
     * @throws LogicException when handed this very query, or a branch
     * defining a WITH clause of its own.
     *
     */
    public function unionAll(?SelectInterface $select = null)
    {
        return $this->addUnion('UNION ALL', $select);
    }

    /**
     *
     * Retains the branch being built and opens the next one, either as a
     * query supplied whole or as this query reset to build it.
     *
     * A supplied branch is rendered on the spot rather than kept as an
     * object. It is a second query with a life of its own, and holding it
     * would have edits made to it after this call reach back into a union it
     * was only ever added to once; the SQL is what was asked for.
     *
     * It is rendered before this query changes so that a branch that cannot
     * render -- one with no columns yet -- leaves this query as it was, rather
     * than having consumed and reset the branch it was building on the way to
     * throwing.
     *
     * @param string $type 'UNION' or 'UNION ALL'.
     *
     * @param SelectInterface|null $select The next branch, or null to build
     * it on this query.
     *
     * @return $this
     *
     * @throws LogicException when handed this very query, or a branch
     * defining a WITH clause of its own: the clause opens a statement and
     * there is none here for it to open, so the CTE goes on the query the
     * union belongs to.
     *
     */
    protected function addUnion(string $type, ?SelectInterface $select)
    {
        // a query cannot be a branch of itself: it is being rendered into the
        // union at the moment it would have to stand apart from it, and each
        // reading of what that means -- the branch before this call, or the
        // whole union so far -- is a different statement. Say so rather than
        // pick one; a clone is what this asks for.
        if ($select === $this) {
            throw new LogicException(
                'Cannot union a query with itself; pass a clone of it instead.'
            );
        }

        // a branch cannot bring a WITH clause of its own: the clause opens a
        // statement and there is no statement here for it to open, so it
        // would render as `UNION WITH ... SELECT`, which no dialect reads.
        // The CTE goes on the query the union belongs to, where every branch
        // may name it.
        if ($select instanceof WithInterface && $select->hasWith()) {
            throw new LogicException(
                'The query passed to union() defines its own WITH clause; '
                . 'define the CTE on the query the union belongs to instead.'
            );
        }

        $branch = $select === null ? null : $select->getStatement();

        // the branch being closed is the supplied one when there is one, and
        // otherwise whatever this query has been building.
        $this->union[] = $this->unionTailOrBuild() . PHP_EOL . $type;
        $this->union_tail = null;
        $this->resetAfterRendering();

        if ($branch !== null) {
            $this->union_tail = $branch;
            $this->bindValuesFromSelect($select, 'union');
        }

        return $this;
    }

    /**
     *
     * Returns the branch this query currently ends with: one supplied whole,
     * or the properties being built.
     *
     * @return string
     *
     */
    protected function unionTailOrBuild()
    {
        return $this->union_tail === null
            ? $this->build()
            : $this->union_tail;
    }

    /**
     *
     * Clears the current select properties after its SQL has been rendered
     * and retained, keeping the placeholder names that SQL still binds.
     *
     * A clause reset frees the names it claimed, which is right while the
     * query is still being built. It is wrong here: union() has already
     * turned the current branch into SQL, placeholders and all, and that SQL
     * keeps binding those names. Left free, the next branch could claim :id
     * for a different value and overwrite the one the rendered branch needs,
     * with nothing to report the clash. The names pass to the union itself
     * rather than staying with their clause, so that a resetWhere() in the
     * next branch cannot free them either.
     *
     * The names come from the rendered SQL rather than from the query parts
     * that bound them. A hand-bound value has no claimant -- that is what lets
     * it overwrite -- but the rendered SQL can just as well be written around
     * it, as `where('id = :id')` with the value supplied by bindValue(), and a
     * later clause binding :id would overwrite what that SQL needs. Scanning
     * the SQL catches that name along with every other one it spells.
     *
     * It also stops short of the names that SQL does *not* spell. A clause
     * reset frees a name but keeps its value, so a placeholder dropped before
     * the union is still bound while appearing nowhere in the branch: nothing
     * there can bind it, the union has no claim to stake, and the next branch
     * is free to use the name for a value of its own.
     *
     * A positional placeholder is the one name the scan cannot find, since it
     * keeps its `?` in the statement and is bound by number. Those are held on
     * the strength of being bound at all -- the alternative is to release a
     * name the rendered SQL is certainly using.
     *
     * Every branch retained so far is scanned, not just the one this call
     * rendered. The claims are rebuilt from nothing each time, so reading only
     * the newest branch would hand back every name the earlier ones spell, and
     * a third branch could then bind :a to a value of its own while the first
     * branch's SQL still reads `a = :a` -- the silent overwrite this whole
     * method exists to prevent, arriving one branch later.
     *
     * @return void
     *
     */
    protected function resetAfterRendering()
    {
        // A CTE belongs to the statement rather than to the branch, so its
        // claims outlive the branch this call renders, and they are not
        // among what the scan below can find: a CTE's SQL is written at the
        // top of the statement, not into $this->union. Rebuilt from nothing,
        // every name a CTE binds would be handed back, and the next branch
        // could then bind one to a value of its own with nothing to report
        // the clash -- the CTE running against the wrong data, which is the
        // silent overwrite this method exists to prevent.
        //
        // A CTE holding a name as the second claimant counts the same. The
        // shared list is cleared below, so a CTE sharing a name with the
        // union -- both wanting the one tenant id -- would otherwise have
        // nothing left recording its claim at all.
        $with_names = array_keys($this->bind_sources, 'with', true);
        foreach ($this->bind_shared as $shared_name => $shared_source) {
            if ($shared_source === 'with') {
                $with_names[] = $shared_name;
            }
        }

        $this->reset();

        // a name inside a string literal, a comment, or a quoted identifier
        // is text, not a placeholder: `where("name = ':a'")` binds nothing,
        // and holding :a on the strength of it would report a collision
        // against a name the next branch is free to bind. The dialect's own
        // alternatives run before the capture and consume those regions, so
        // that only the names outside them are captured.
        //
        // Everything doubtful falls the same way: keep the text as SQL. A
        // name kept by mistake costs a needless collision report, where a
        // name lost inside a region wrongly read as text would let a later
        // branch bind it and silently overwrite what the rendered SQL needs.
        // An unterminated literal or comment therefore matches nothing and is
        // read straight through, the possessive quantifier holding the
        // literal to that reading instead of backtracking onto a shorter one.
        // Each branch is read on its own for the same reason, so that a stray
        // quote in one cannot pair with a quote in the next and swallow the
        // placeholders between them.
        $find = "/{$this->getQuotedNamePattern()}|{$this->text_pattern}"
              . "|(?<!:):(\w+)/s";
        $spelled = [];
        foreach ($this->union as $branch) {
            preg_match_all($find, $branch, $matches);
            // 'strlen' as a callable string is the idiom for dropping the
            // empty captures the alternation leaves behind, and reads better
            // than the closure that would satisfy the callable(string): bool
            // PHPStan wants; keeping it costs this one line.
            /** @phpstan-ignore argument.type */
            $spelled += array_flip(array_filter($matches[1], 'strlen'));
        }

        $this->bind_sources = [];
        foreach (array_keys($this->bind_values) as $name) {
            if (isset($spelled[$name]) || ctype_digit((string) $name)) {
                $this->bind_sources[$name] = 'union';
            }
        }

        // every name now belongs to the union or to a CTE, including any a
        // clause of the branch just rendered was sharing: that clause is SQL
        // now, so there is no live claimant left to hand a name back to.
        $this->bind_shared = [];

        // put the CTEs' claims back after the union's. A name only a CTE
        // binds passes to it outright; one the union spells as well is held
        // by both, and recording the CTE as the second claimant is what lets
        // either reset hand the name to the other. Overwriting the union's
        // claim instead would leave resetWith() freeing a name the retained
        // branch still binds -- the same silent overwrite, the other way
        // round.
        foreach ($with_names as $name) {
            if (isset($this->bind_sources[$name])) {
                $this->bind_shared[$name] = 'with';
                continue;
            }

            $this->bind_sources[$name] = 'with';
        }
    }

    /**
     *
     * A regex alternative matching a quoted identifier, in the quoting this
     * dialect writes: backticks on MySQL, brackets on SQL Server, double
     * quotes elsewhere.
     *
     * A colon inside one is part of the name and no placeholder can stand
     * there, so a scan for placeholder names must read past it. The quoting
     * comes from the quoter rather than being spelled here, so that what is
     * read back is what the builder wrote; the closing quote doubled is how a
     * name containing one is written, and the name runs on past it.
     *
     * @return string
     *
     * @see resetAfterRendering()
     *
     */
    protected function getQuotedNamePattern()
    {
        $prefix = preg_quote($this->getQuoteNamePrefix(), '/');
        $suffix = preg_quote($this->getQuoteNameSuffix(), '/');

        return "{$prefix}(?:[^{$suffix}]|{$suffix}{$suffix})*+{$suffix}";
    }

    /**
     *
     * Clears the current select properties; generally used after adding a
     * union.
     *
     * @return void
     *
     */
    public function reset()
    {
        $this->resetFlags();
        $this->resetCols();
        $this->resetTables();
        $this->resetWhere();
        $this->resetGroupBy();
        $this->resetHaving();
        $this->resetOrderBy();
        $this->limit(0);
        $this->offset(0);
        $this->page(0);
        $this->forUpdate(false);
    }

    /**
     *
     * Resets the columns on the SELECT.
     *
     * @return $this
     *
     */
    public function resetCols()
    {
        $this->cols = [];
        return $this;
    }

    /**
     *
     * Resets the FROM and JOIN clauses on the SELECT.
     *
     * @return $this
     *
     */
    public function resetTables()
    {
        $this->from = [];
        $this->from_key = -1;
        $this->join = [];
        $this->table_refs = [];
        $this->removeBindSources('join');
        $this->removeBindSources('table');
        return $this;
    }

    /**
     *
     * Resets the WHERE clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetWhere()
    {
        $this->where = [];
        $this->removeBindSources('where');
        return $this;
    }

    /**
     *
     * Resets the GROUP BY clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetGroupBy()
    {
        $this->group_by = [];
        return $this;
    }

    /**
     *
     * Resets the HAVING clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetHaving()
    {
        $this->having = [];
        $this->removeBindSources('having');
        return $this;
    }

    /**
     *
     * Resets the ORDER BY clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetOrderBy()
    {
        $this->order_by = [];
        return $this;
    }

    /**
     *
     * Resets the UNION and UNION ALL clauses on the SELECT.
     *
     * @return $this
     *
     */
    public function resetUnions()
    {
        $this->union = [];
        $this->union_tail = null;

        // the rendered branches are gone, so nothing binds their placeholders
        // any more: release the names they were holding.
        $this->removeBindSources('union');
        return $this;
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
        $cols = [];
        foreach ($this->cols as $key => $val) {
            if (is_int($key)) {
                $cols[] = $this->quoter->quoteNamesIn($val);
            } else {
                $cols[] = $this->quoter->quoteNamesIn("$val AS $key");
            }
        }

        return 'SELECT'
            . $this->builder->buildFlags($this->flags)
            . $this->builder->buildCols($cols)
            . $this->builder->buildFrom($this->from, $this->join)
            . $this->builder->buildWhere($this->where)
            . $this->builder->buildGroupBy($this->group_by)
            . $this->builder->buildHaving($this->having)
            . $this->builder->buildOrderBy($this->order_by)
            . $this->builder->buildLimitOffset($this->limit, $this->offset)
            . $this->builder->buildForUpdate($this->for_update);
    }

    /**
     *
     * Sets a limit count on the query.
     *
     * @param int $limit The number of rows to select.
     *
     * @return $this
     *
     */
    public function limit(int $limit)
    {
        $this->setLimit($limit);
        if ($this->page) {
            $this->page = 0;
            $this->setOffset(0);
        }
        return $this;
    }

    /**
     *
     * Sets a limit offset on the query.
     *
     * @param int $offset Start returning after this many rows.
     *
     * @return $this
     *
     */
    public function offset(int $offset)
    {
        $this->setOffset($offset);
        if ($this->page) {
            $this->page = 0;
            $this->setLimit(0);
        }
        return $this;
    }

    /**
     *
     * Adds a column order to the query.
     *
     * @param array<array-key, string> $spec The columns and direction to
     * order by.
     *
     * @return $this
     *
     */
    public function orderBy(array $spec)
    {
        return $this->addOrderBy($spec);
    }
}
