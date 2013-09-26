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
namespace Aura\Sql\Query\Sqlsrv;

use Aura\Sql\Query\Traits;

/**
 *
 * An object for Sqlsrv SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractSqlsrv
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
            return $stm;
        }
        
        // limit but not offset?
        if ($this->limit && ! $this->offset) {
            // limit, but no offset, so we can use TOP
            return preg_replace(
                '/^(SELECT( DISTINCT)?)/',
                "$1 TOP $limit",
                $stm
            );
        }
        
        // both limit and offset. must have an ORDER clause to work; OFFSET is
        // a sub-clause of the ORDER clause. cannot use FETCH without OFFSET.
        return $stm . PHP_EOL
             . "OFFSET {$this->offset} ROWS "
             . "FETCH NEXT {$this->limit} ROWS ONLY" . PHP_EOL;
}
