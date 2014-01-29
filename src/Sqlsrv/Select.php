<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql_Query
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql_Query\Sqlsrv;

use Aura\Sql_Query\Common;

/**
 *
 * An object for Sqlsrv SELECT queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Select extends Common\Select
{
    /**
     * 
     * Builds the limit/offset equivalent portions of the statement.
     * 
     * @return null
     * 
     */
    protected function buildLimit()
    {
        // neither limit nor offset?
        if (! $this->limit && ! $this->offset) {
            // no changes
            return;
        }
        
        // limit but no offset?
        if ($this->limit && ! $this->offset) {
            // use TOP in place
            $this->stm = preg_replace(
                '/^(SELECT( DISTINCT)?)/',
                "$1 TOP {$this->limit}",
                $this->stm
            );
            return;
        }
        
        // both limit and offset. must have an ORDER clause to work; OFFSET is
        // a sub-clause of the ORDER clause. cannot use FETCH without OFFSET.
        $this->stm .= PHP_EOL . "OFFSET {$this->offset} ROWS "
                    . "FETCH NEXT {$this->limit} ROWS ONLY";
    }
}
