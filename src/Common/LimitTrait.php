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
 * A trait for LIMIT clauses.
 *
 * @package Aura.SqlQuery
 *
 */
trait LimitTrait
{
    protected $limit = 0;

    /**
     *
     * Sets a limit count on the query.
     *
     * @param int $limit The number of rows to select.
     *
     * @return $this
     *
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     *
     * Returns the LIMIT value.
     *
     * @return int
     *
     */
    public function getLimit()
    {
        return $this->limit;
    }
}
