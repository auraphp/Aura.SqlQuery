<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
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
    public function __toString();

    /**
     *
     * Returns the prefix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNamePrefix();

    /**
     *
     * Returns the suffix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNameSuffix();

    /**
     *
     * Adds values to bind into the query; merges with existing values.
     *
     * @param array $bind_values Values to bind to the query.
     *
     * @return self
     *
     */
    public function bindValues(array $bind_values);

    /**
     *
     * Binds a single value to the query.
     *
     * @param string $name The placeholder name or number.
     *
     * @param mixed $value The value to bind to the placeholder.
     *
     * @return self
     *
     */
    public function bindValue($name, $value);

    /**
     *
     * Gets the values to bind into the query.
     *
     * @return array
     *
     */
    public function getBindValues();
}
