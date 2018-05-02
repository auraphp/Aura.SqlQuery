<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception;

/**
 *
 * An interface for WHERE clauses.
 *
 * @package Aura.SqlQuery
 *
 */
interface WhereInterface
{
    /**
     *
     * Adds a WHERE condition to the query by AND. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     *
     * @param array $bind Values to be bound to placeholders.
     *
     * @return $this
     *
     */
    public function where($cond, array $bind = []);

    /**
     *
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     *
     * @param array $bind Values to be bound to placeholders.
     *
     * @return $this
     *
     * @see where()
     *
     */
    public function orWhere($cond, array $bind = []);

    /**
     *
     * Adds a WHERE condition with a prepared statement placeholder and value to the query by AND.
     *
     * @param string $cond the first part of the WHERE condition without the placeholder. e.g. "name = " or "name IN"
     *
     * @param string $placeholder the placeholder as a string. e.g. ":NAME" or "(:NAMES)"
     *
     * @param (string|int|float|array) $value the value to be bound to the placeholder. e.g. "John" or ["John", "Eric", "Michael", "Terry"]
     *
     * @return $this
     *
     * @throws Exception
     *
     */
    public function whereBoundValue(string $cond, string $placeholder, $value);

    /**
     *
     * Adds a WHERE condition with a prepared statement placeholder and value to the query by OR.
     *
     * @param string $cond the first part of the WHERE condition without the placeholder. e.g. "name = " or "name IN"
     *
     * @param string $placeholder the placeholder as a string. e.g. ":NAME" or "(:NAMES)"
     *
     * @param (string|int|float|array) $value the value to be bound to the placeholder. e.g. "John" or ["John", "Eric", "Michael", "Terry"]
     *
     * @return $this
     *
     * @throws Exception
     *
     * @see whereBoundValue()
     *
     */
    public function orWhereBoundValue(string $cond, string $placeholder, $value);
}
