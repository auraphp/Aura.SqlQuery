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
 * A trait for common INSERT functionality.
 *
 * @package Aura.Sql
 *
 */
trait InsertTrait
{
    use ValuesTrait;

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
        $this->into = $this->quoteName($into);
        return $this;
    }

    protected function build()
    {
        return "INSERT INTO {$this->into}"
             . $this->buildValuesForInsert()
             . PHP_EOL;
    }
}
