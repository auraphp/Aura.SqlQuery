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
 * A trait to build ON CONFLICT clauses for Postgres and SQLite.
 *
 * @package Aura.SqlQuery
 *
 */
trait BuildOnConflictTrait
{
    /**
     *
     * Builds the `ON CONFLICT` clause of the statement.
     *
     * @param string|array $target The conflict target.
     * @param array $update_values The columns and values to update.
     * @param array $where Optional WHERE conditions.
     * @param bool $ignore Whether the ignore clause is enabled.
     * @return string
     * @throws Exception\LogicException
     *
     */
    public function buildOnConflict($target, $update_values, $where, $ignore)
    {
        if ($ignore && ! empty($update_values)) {
            throw new Exception\LogicException(
                'Cannot combine IGNORE / DO NOTHING with DO UPDATE SET.'
            );
        }

        if ($ignore) {
            if (empty($target)) {
                return PHP_EOL . 'ON CONFLICT DO NOTHING';
            }
            return PHP_EOL . 'ON CONFLICT ' . $target . ' DO NOTHING';
        }

        if (empty($update_values)) {
            return '';
        }

        if (empty($target)) {
            throw new Exception\LogicException(
                'Database requires a conflict target for DO UPDATE.'
            );
        }

        $update_str = $this->buildConflictUpdateValues($update_values);
        $where_str = $this->buildConflictWhere($where);

        return PHP_EOL . "ON CONFLICT {$target} DO UPDATE SET{$update_str}{$where_str}";
    }

    /**
     *
     * Builds the conflict update values.
     *
     * @param array $update_values
     * @return string
     *
     */
    protected function buildConflictUpdateValues(array $update_values)
    {
        $values = array();
        foreach ($update_values as $key => $row) {
            $values[] = $key . ' = ' . $row;
        }
        return $this->indentCsv($values);
    }

    /**
     *
     * Builds the conflict update WHERE clause.
     *
     * @param array $where
     * @return string
     *
     */
    protected function buildConflictWhere(array $where)
    {
        if (empty($where)) {
            return '';
        }
        return PHP_EOL . 'WHERE' . $this->indent($where);
    }
}
