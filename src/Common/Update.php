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
use Aura\Sql_Query\UpdateInterface;

/**
 *
 * An object for UPDATE queries.
 *
 * @package Aura.Sql
 *
 */
class Update extends AbstractQuery implements UpdateInterface
{
    use Traits\UpdateTrait;
}
