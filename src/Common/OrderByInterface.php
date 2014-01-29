<?php

namespace Aura\Sql_Query\Common;

interface OrderByInterface
{
    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return $this
     *
     */
    public function orderBy(array $spec);
}
