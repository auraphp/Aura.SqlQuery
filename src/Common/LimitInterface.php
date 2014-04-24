<?php

namespace Aura\SqlQuery\Common;

interface LimitInterface
{
    /**
     *
     * Sets a limit count on the query.
     *
     * @param int $limit The number of rows to select.
     *
     * @return $this
     *
     */
    public function limit($limit);
}
