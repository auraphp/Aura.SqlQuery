<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.SqlQuery
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;

/**
 *
 * An object for bulk Sqlite INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class BulkInsert extends Common\BulkInsert
{

    /**
     * Adds or removes OR ABORT flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     */
    public function orAbort($enable = true)
    {
        $this->setFlag('OR ABORT', $enable);
        return $this;
    }

    /**
     * Adds or removes OR FAIL flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     */
    public function orFail($enable = true)
    {
        $this->setFlag('OR FAIL', $enable);
        return $this;
    }

    /**
     * Adds or removes OR IGNORE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     */
    public function orIgnore($enable = true)
    {
        $this->setFlag('OR IGNORE', $enable);
        return $this;
    }

    /**
     * Adds or removes OR REPLACE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     */
    public function orReplace($enable = true)
    {
        $this->setFlag('OR REPLACE', $enable);
        return $this;
    }

    /**
     * Adds or removes OR ROLLBACK flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     */
    public function orRollback($enable = true)
    {
        $this->setFlag('OR ROLLBACK', $enable);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildValuesForInsert()
    {
        $rows = $this->rows > 0 ? $this->rows : count($this->bind_values);
        $rows = $rows == 0 ? 1 : $rows;

        $placeholder = $this->indentCsv(array_values($this->col_values));
        $keys = $this->indentCsv(array_keys($this->col_values));

        $return = PHP_EOL
            . "(" . $keys . ")" . PHP_EOL
            . ltrim(str_repeat("UNION SELECT " . $placeholder . "" . PHP_EOL, $rows), "UNION ");

        return $return;
    }
}
