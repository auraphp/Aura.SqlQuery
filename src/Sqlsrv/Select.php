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
namespace Aura\Sql_Query\Sqlsrv;

use Aura\Sql_Query\Common;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for Sqlsrv SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends Common\Select
{
    protected $stm;
    
    protected function build()
    {
        // build the first part of the statement
        $this->stm = 'SELECT'
                   . $this->buildFlags() . PHP_EOL
                   . $this->buildCols()
                   . $this->buildFrom()
                   . $this->buildJoin()
                   . $this->buildWhere()
                   . $this->buildGroupBy()
                   . $this->buildHaving()
                   . $this->buildOrderBy();
        
        // split because we need to modify the statement at this point
        $this->buildLimitOffset();
        
        // continue building
        return $this->stm
             . $this->buildForUpdate()
             . PHP_EOL;
    }
    
    protected function buildLimitOffset()
    {
        // neither limit nor offset?
        if (! $this->limit && ! $this->offset) {
            // no changes
            return;
        }
        
        // limit but no offset?
        if ($this->limit && ! $this->offset) {
            // use TOP
            $this->stm = preg_replace(
                '/^(SELECT( DISTINCT)?)/',
                "$1 TOP {$this->limit}",
                $this->stm
            );
            return;
        }
        
        // both limit and offset. must have an ORDER clause to work; OFFSET is
        // a sub-clause of the ORDER clause. cannot use FETCH without OFFSET.
        $this->stm .= PHP_EOL
                    . "OFFSET {$this->offset} ROWS "
                    . "FETCH NEXT {$this->limit} ROWS ONLY" . PHP_EOL;
    }
}
