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
 * Common code for WHERE clauses.
 *
 * @package Aura.SqlQuery
 *
 */
trait WhereTrait
{
    /**
     *
     * Adds a WHERE condition to the query by AND.
     *
     * @param string $cond The WHERE condition.
     *
     * @param array $bind Values to be bound to placeholders
     *
     * @return $this
     *
     */
    public function where($cond, array $bind = [])
    {
        $this->addClauseCondWithBind('where', 'AND', $cond, $bind);
        return $this;
    }

    /**
     *
     * Adds a WHERE condition to the query by OR. If the condition has
     * ?-placeholders, additional arguments to the method will be bound to
     * those placeholders sequentially.
     *
     * @param string $cond The WHERE condition.
     *
     * @param array $bind Values to be bound to placeholders
     *
     * @return $this
     *
     * @see where()
     *
     */
    public function orWhere($cond, array $bind = [])
    {
        $this->addClauseCondWithBind('where', 'OR', $cond, $bind);
        return $this;
    }

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
    public function whereBoundValue($cond, $placeholder, $value)
    {
        $name = $this->extractNameOrThrow($placeholder);
        $this->addClauseCondWithBind('where', 'AND', $cond.$placeholder, [ $name => $value ] );
        return $this;
    }

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
    public function orWhereBoundValue($cond, $placeholder, $value)
    {
        $name = $this->extractNameOrThrow($placeholder);
        $this->addClauseCondWithBind('where', 'OR', $cond.$placeholder, [ $name => $value ] );
        return $this;
    }

    /**
     * Extract the name of PDO placeholders (e.g. "P")  of the form ":P" for simple values and "(:P)" for array values.
     *
     * @param string $placeholder the placeholder specification
     *
     * @return the placeholder name as string
     *
     * @throws Exception
     *
     */
    protected static function extractNameOrThrow($placeholder) {
        $name = preg_replace( '/^\(?:([^\)]+)\)?$/', '\1', $placeholder);
        // XXX add type checks
        if (strlen($name)===strlen($placeholder)) {
            throw new Exception("Bad placeholder \"$name\"");
        }
        return $name;
    }
}
