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
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\AbstractQuery;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for INSERT queries.
 *
 * @package Aura.Sql
 *
 */
class Insert extends AbstractQuery implements InsertInterface
{
    use Traits\ValuesTrait;

    /**
     *
     * The table to insert into.
     *
     * @var string
     *
     */
    protected $into;

    /**
     *
     * Sets the table to insert into.
     *
     * @param string $into The table to insert into.
     *
     * @return $this
     *
     */
    public function into($into)
    {
        // don't quote yet, we might need it for getLastInsertIdName()
        $this->into = $into;
        return $this;
    }

    protected function build()
    {
        $this->stm = 'INSERT';
        $this->buildFlags();
        $this->buildInto();
        $this->buildValuesForInsert();
        return $this->stm;
    }
    
    protected function buildInto()
    {
        $this->stm .= " INTO " . $this->quoteName($this->into);
    }
    
    public function getLastInsertIdName($col)
    {
        return null;
    }
}
