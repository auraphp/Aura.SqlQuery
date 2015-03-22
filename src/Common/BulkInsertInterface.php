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
namespace Aura\SqlQuery\Common;

/**
 *
 * An interface for bulk INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
interface BulkInsertInterface extends InsertInterface
{
    /**
     * Sets the amount of rows for this insert
     *
     * @param int $rows The amount of rows to insert
     *
     * @return $this
     */
    public function rows($rows);
}
