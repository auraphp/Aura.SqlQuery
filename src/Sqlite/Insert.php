<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;
use Aura\SqlQuery\Exception;

/**
 *
 * An object for Sqlite INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Insert extends Common\Insert implements Common\OnConflictUpdateInterface
{
    use OrConflictTrait;
    use Common\OnConflictUpdateTrait;

    /**
     *
     * Builds the statement.
     *
     * @return string
     *
     */
    protected function build()
    {
        $ignore = false;
        $has_or_ignore = $this->hasFlag('OR IGNORE');
        if (!empty($this->conflict_target)) {
            if ($has_or_ignore) {
                $this->setFlag('OR IGNORE', false);
                $ignore = true;
            }
            $this->assertNoOrConflictFlags();
        }

        $this->assertOneOrConflictFlag();

        $stm = parent::build();

        if ($has_or_ignore && !empty($this->conflict_target)) {
            $this->setFlag('OR IGNORE', true);
        }

        return $stm
            . $this->builder->buildOnConflict(
                $this->conflict_target,
                $this->conflict_update_values,
                $this->conflict_where,
                $ignore
            );
    }

    /**
     *
     * Asserts that no legacy SQLite OR conflict flags are set when using ON CONFLICT.
     *
     * @return void
     * @throws Exception\LogicException
     *
     */
    protected function assertNoOrConflictFlags()
    {
        $set = [];
        foreach ($this->or_conflict_flags as $flag) {
            if ($this->hasFlag($flag)) {
                $set[] = $flag;
            }
        }

        if (! empty($set)) {
            throw new Exception\LogicException(
                'Cannot combine ON CONFLICT clause with SQLite OR conflict flags: '
                . implode(' and ', $set) . '.'
            );
        }
    }

    /**
     *
     * Adds or removes OR ABORT flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orAbort($enable = true)
    {
        $this->setFlag('OR ABORT', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR FAIL flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orFail($enable = true)
    {
        $this->setFlag('OR FAIL', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR IGNORE flag.
     *
     * @deprecated use ignore instead
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orIgnore($enable = true)
    {
        $this->ignore($enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR IGNORE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function ignore($enable = true)
    {
        $this->setFlag('OR IGNORE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR REPLACE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orReplace($enable = true)
    {
        $this->setFlag('OR REPLACE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes OR ROLLBACK flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orRollback($enable = true)
    {
        $this->setFlag('OR ROLLBACK', $enable);
        return $this;
    }
}
