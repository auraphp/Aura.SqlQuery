<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;

/**
 *
 * An object for Sqlite DELETE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Delete extends Common\Delete implements Common\OrderByInterface, Common\LimitOffsetInterface
{
    use Common\LimitOffsetTrait;

    /**
     *
     * Adds a column order to the query.
     *
     * @param array $spec The columns and direction to order by.
     *
     * @return $this
     *
     */
    public function orderBy(array $spec)
    {
        return $this->addOrderBy($spec);
    }
}
