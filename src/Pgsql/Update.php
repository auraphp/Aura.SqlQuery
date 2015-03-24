<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

/**
 *
 * An object for PgSQL UPDATE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Update extends Common\Update implements Common\ReturningInterface
{
    /**
     *
     * Adds returning columns to the query.
     *
     * Multiple calls to returning() will append to the list of columns, not
     * overwrite the previous columns.
     *
     * @param array $cols The column(s) to add to the query.
     *
     * @return self
     *
     */
    public function returning(array $cols)
    {
        return $this->addReturning($cols);
    }

    /**
     * Bind a value, supporting simple array values. No escaping done, whatsoever.
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function bindValue($name, $value)
    {
        if (is_array($value)) {
            $value = '{' . implode(",", $value) . '}';
        }

        return parent::bindValue($name, $value);
    }

}
