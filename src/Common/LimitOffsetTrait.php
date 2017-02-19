<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Common;

/**
 *
 * An interface for LIMIT...OFFSET clauses.
 *
 * @package Aura.SqlQuery
 *
 */
trait LimitOffsetTrait
{
    use LimitTrait;

    private $offset = 0;

    /**
     *
     * Sets a limit offset on the query.
     *
     * @param int $offset Start returning after this many rows.
     *
     * @return $this
     *
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    /**
     *
     * Returns the OFFSET value.
     *
     * @return int
     *
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     *
     * Builds the `LIMIT ... OFFSET` clause of the statement.
     *
     * @return string
     *
     */
    protected function buildLimit()
    {
        $clause = '';

        $limit = $this->getLimit();
        if (!empty($limit)) {
            $clause .= "LIMIT {$limit}";
        }

        if (!empty($this->offset)) {
            $clause .= " OFFSET {$this->offset}";
        }

        if (!empty($clause)) {
            $clause = PHP_EOL . trim($clause);
        }

        return $clause;
    }
}
