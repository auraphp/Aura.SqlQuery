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
use Aura\Sql_Query\InsertInterface;

/**
 *
 * An object for Sqlsrv INSERT queries.
 *
 * @package Aura.Sql
 *
 */
class Insert extends AbstractQuery implements InsertInterface
{
    use Traits\InsertTrait;
}
