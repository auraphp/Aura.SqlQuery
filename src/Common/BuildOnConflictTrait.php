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
     * @param string|null $target The conflict target.
     * @param array<string, string> $update_values The columns and values
     * to update.
     * @param list<string> $where Optional WHERE conditions.
     * @param bool $ignore Whether the ignore clause is enabled.
     * @return string
     * @throws Exception\LogicException
     *
     */
    public function buildOnConflict(?string $target, array $update_values, array $where, bool $ignore): string
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
     * @param array<string, string> $update_values
     * @return string
     *
     */
    protected function buildConflictUpdateValues(array $update_values): string
    {
        $values = [];
        foreach ($update_values as $key => $row) {
            $values[] = $key . ' = ' . $row;
        }
        return $this->indentCsv($values);
    }

    /**
     *
     * Builds the conflict update WHERE clause.
     *
     * @param list<string> $where
     * @return string
     *
     */
    protected function buildConflictWhere(array $where): string
    {
        if (empty($where)) {
            return '';
        }
        return PHP_EOL . 'WHERE' . $this->indent($where);
    }
}
