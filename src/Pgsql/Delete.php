<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

/**
 *
 * An object for PgSQL UPDATE queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Delete extends Common\Delete implements ReturningInterface
{
    use ReturningTrait;

    protected function build()
    {
        return parent::build()
            . $this->builder->buildReturning($this->returning);
    }
}
