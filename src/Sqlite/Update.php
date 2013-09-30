<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql_Query\Sqlite;

use Aura\Sql_Query\Common;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for Sqlite UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends Common\Update
{
    use Traits\LimitOffsetTrait;
    use Traits\OrderByTrait;
    
    protected function build()
    {
        $this->stm = rtrim(parent::build()) . PHP_EOL;
        $this->stm .= $this->buildOrderBy();
        $this->stm .= $this->buildLimitOffset();
        return $this->stm . PHP_EOL;
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
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function orIgnore($enable = true)
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
