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

use Aura\Sql_Query\AbstractQuery;
use Aura\Sql_Query\Traits;
use Aura\Sql_Query\SelectInterface;

/**
 *
 * An object for Sqlsrv SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractQuery implements SelectInterface
{
    use Traits\SelectTrait;

    protected function build()
    {
        // build the first part of the string
        $stm = 'SELECT'
             . $this->buildFlags() . PHP_EOL
             . $this->buildCols()
             . $this->buildFrom()
             . $this->buildJoin()
             . $this->buildWhere()
             . $this->buildGroupBy()
             . $this->buildHaving()
             . $this->buildOrderBy();
        
        // split because we need to modify the string at this point
        $stm = $this->buildLimitOffset($stm);
        
        // continue building
        return $stm
             . $this->buildForUpdate()
             . PHP_EOL;
    }
    
    protected function buildLimitOffset($stm)
    {
        // neither limit nor offset?
        if (! $this->limit && ! $this->offset) {
            // no changes
            return $stm;
        }
        
        // limit but no offset?
        if ($this->limit && ! $this->offset) {
            // use TOP
            return preg_replace(
                '/^(SELECT( DISTINCT)?)/',
                "$1 TOP {$this->limit}",
                $stm
            );
        }
        
        // both limit and offset. must have an ORDER clause to work; OFFSET is
        // a sub-clause of the ORDER clause. cannot use FETCH without OFFSET.
        return $stm . PHP_EOL
             . "OFFSET {$this->offset} ROWS "
             . "FETCH NEXT {$this->limit} ROWS ONLY" . PHP_EOL;
    }
}
