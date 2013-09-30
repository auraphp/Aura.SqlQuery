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
use Aura\Sql_Query\DeleteInterface;
use Aura\Sql_Query\Traits;

/**
 *
 * An object for DELETE queries.
 *
 * @package Aura.Sql
 *
 */
class Delete extends AbstractQuery implements DeleteInterface
{
    use Traits\WhereTrait;
    
    /**
     *
     * The table to delete from.
     *
     * @var string
     *
     */
    protected $from;

    /**
     *
     * Sets the table to delete from.
     *
     * @param string $table The table to delete from.
     *
     * @return $this
     *
     */
    public function from($table)
    {
        $this->from = $this->quoteName($table);
        return $this;
    }
    
    protected function build()
    {
        return "DELETE FROM {$this->from}" . PHP_EOL
             . $this->buildWhere()
             . PHP_EOL;
    }
}
