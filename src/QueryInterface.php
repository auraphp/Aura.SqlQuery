<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery;

/**
 *
 * Interface for query objects.
 *
 * @package Aura.SqlQuery
 *
 */
interface QueryInterface
{
    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     */
    public function __toString(): string;

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * @return string
     *
     */
    public function getStatement(): string;

    /**
     *
     * Returns the prefix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNamePrefix(): string;

    /**
     *
     * Returns the suffix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNameSuffix(): string;

    /**
     *
     * Adds values to bind into the query; merges with existing values.
     *
     * @param array<int|string, mixed> $bind_values Values to bind to the
     * query.
     *
     * @return $this
     *
     */
    public function bindValues(array $bind_values): static;

    /**
     *
     * Binds a single value to the query.
     *
     * @param int|string $name The placeholder name or number.
     *
     * @param mixed $value The value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function bindValue(int|string $name, mixed $value): static;

    /**
     *
     * Gets the values to bind into the query.
     *
     * @return array<int|string, mixed>
     *
     */
    public function getBindValues(): array;

    /**
     *
     * Reset all query flags.
     *
     * @return $this
     *
     */
    public function resetFlags(): static;
}
