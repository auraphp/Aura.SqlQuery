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

use Aura\Sql_Query\Traits;
use Aura\Sql_Query\UpdateInterface;

/**
 *
 * An object for Sqlsrv UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends AbstractSqlsrv implements UpdateInterface
{
    use Traits\UpdateTrait;
}
