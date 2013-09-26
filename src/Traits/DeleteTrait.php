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
namespace Aura\Sql\Query\Traits;

/**
 *
 * A trait for common DELETE functionality.
 *
 * @package Aura.Sql
 *
 */
trait DeleteTrait
{
    use WhereTrait;
    
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
        return "DELETE FROM {$this->from}"
             . $this->buildWhere()
             . PHP_EOL;
    }
}
