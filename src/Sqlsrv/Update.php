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
 * An object for MySQL UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends \Aura\Sql\Query\Update
{
    use Traits\UpdateTrait;
}
