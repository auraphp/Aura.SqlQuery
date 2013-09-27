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
namespace Aura\Sql_Query;

use Aura\Sql_Query\Traits;

/**
 *
 * An object for SELECT queries.
 *
 * @package Aura.Sql
 *
 */
class Select extends AbstractQuery
{
    use Traits\SelectTrait;
}
