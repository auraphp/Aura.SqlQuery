<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\QueryInterface;

/**
 *
 * An interface for SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
interface SelectInterface extends QueryInterface, WhereInterface, OrderByInterface, LimitOffsetInterface, WithInterface
{
    /**
     *
     * Sets the number of rows per page.
     *
     * @param int $paging The number of rows to page at.
     *
     * @return $this
     *
     */
    public function setPaging(int $paging): static;

    /**
     *
     * Gets the number of rows per page.
     *
     * @return int The number of rows per page.
     *
     */
    public function getPaging(): int;

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
    public function forUpdate(bool $enable = true): static;

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
    public function distinct(bool $enable = true): static;

    /**
     *
     * Is the select DISTINCT?
     *
     * @return bool
     *
     */
    public function isDistinct(): bool;

    /**
     *
     * Adds columns to the query.
     *
     * Multiple calls to cols() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array<int|string, string> $cols The column(s) to add to the
     * query.
     *
     * @return $this
     *
     */
    public function cols(array $cols): static;

    /**
     *
     * Remove a column via its alias.
     *
     * @param string $alias The column to remove
     *
     * @return bool
     *
     */
    public function removeCol(string $alias): bool;

    /**
     *
     * Has the column or alias been added to the query?
     *
     * @param string $alias The column or alias to look for
     *
     * @return bool
     *
     */
    public function hasCol(string $alias): bool;

    /**
     *
     * Does the query have any columns in it?
     *
     * @return bool
     *
     */
    public function hasCols(): bool;

    /**
     *
     * Returns a list of columns.
     *
     * @return array<int|string, string>
     *
     */
    public function getCols(): array;

    /**
     *
     * Adds a FROM element to the query; quotes the table name automatically.
     *
     * @param string $spec The table specification; "foo" or "foo AS bar".
     *
     * @return $this
     *
     */
    public function from(string $spec): static;

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
    public function fromRaw(string $spec): static;

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
    public function fromSubSelect(string|SelectInterface $spec, string $name): static;

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
     * @return $this
     *
     */
    public function join(string $join, string $spec, ?string $cond = null): static;

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
     * @throws \Aura\SqlQuery\Exception\LogicException
     *
     */
    public function innerJoin(string $spec, ?string $cond = null, array $bind = []): static;

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
     * @throws \Aura\SqlQuery\Exception\LogicException
     *
     */
    public function leftJoin(string $spec, ?string $cond = null, array $bind = []): static;

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
     * @return $this
     *
     */
    public function joinSubSelect(string $join, string|SelectInterface $spec, string $name, ?string $cond = null): static;

    /**
     *
     * Adds grouping to the query.
     *
     * @param array<array-key, string> $spec The column(s) to group by.
     *
     * @return $this
     *
     */
    public function groupBy(array $spec): static;

    /**
     *
     * Adds a HAVING condition to the query by AND.
     *
     * @param string|\Closure $cond The HAVING condition.
     *
     * @param array<int|string, mixed> $bind Values to be bound to
     * placeholders.
     *
     * @return $this
     *
     */
    public function having(string|\Closure $cond, array $bind = []): static;

    /**
     *
     * Adds a HAVING condition to the query by OR.
     *
     * @param string|\Closure $cond The HAVING condition.
     *
     * @param array<int|string, mixed> $bind Values to be bound to
     * placeholders.
     *
     * @return $this
     *
     * @see having()
     *
     */
    public function orHaving(string|\Closure $cond, array $bind = []): static;

    /**
     *
     * Sets the limit and count by page number.
     *
     * @param int $page Limit results to this page number.
     *
     * @return $this
     *
     */
    public function page(int $page): static;

    /**
     *
     * Returns the page number being selected.
     *
     * @return int
     *
     */
    public function getPage(): int;

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
     * @throws \Aura\SqlQuery\Exception\LogicException when handed this very
     * query, or a branch defining a WITH clause of its own.
     *
     */
    public function union(?SelectInterface $select = null): static;

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
     * @throws \Aura\SqlQuery\Exception\LogicException when handed this very
     * query, or a branch defining a WITH clause of its own.
     *
     */
    public function unionAll(?SelectInterface $select = null): static;

    /**
     *
     * Clears the current select properties, usually called after a union.
     * You may need to call resetUnions() if you have used one
     *
     * @return void
     *
     */
    public function reset(): void;

    /**
     *
     * Resets the columns on the SELECT.
     *
     * @return $this
     *
     */
    public function resetCols(): static;

    /**
     *
     * Resets the FROM and JOIN clauses on the SELECT.
     *
     * @return $this
     *
     */
    public function resetTables(): static;

    /**
     *
     * Resets the WHERE clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetWhere(): static;

    /**
     *
     * Resets the GROUP BY clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetGroupBy(): static;

    /**
     *
     * Resets the HAVING clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetHaving(): static;

    /**
     *
     * Resets the ORDER BY clause on the SELECT.
     *
     * @return $this
     *
     */
    public function resetOrderBy(): static;

    /**
     *
     * Resets the UNION and UNION ALL clauses on the SELECT.
     *
     * @return $this
     *
     */
    public function resetUnions(): static;
}
