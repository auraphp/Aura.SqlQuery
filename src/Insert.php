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
namespace Aura\Sql\Query;

use Aura\Sql\Query\Traits;

/**
 *
 * An object for INSERT queries.
 *
 * @package Aura.Sql
 *
 */
class Insert extends AbstractQuery
{
    use Traits\InsertTrait;
}
