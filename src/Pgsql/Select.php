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
namespace Aura\Sql\Query\Pgsql;

use Aura\Sql\Query\Traits;

/**
 *
 * An object for PgSQL SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractPgsql
{
    use Traits\SelectTrait;
}
