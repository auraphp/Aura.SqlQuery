<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

/**
 *
 * SELECT builder for MySQL.
 *
 * @package Aura.SqlQuery
 *
 */
class SelectBuilder extends Common\SelectBuilder
{
    /**
     *
     * MySQL has no OFFSET without LIMIT; its manual gives the largest
     * unsigned BIGINT as the way to say "all the remaining rows".
     *
     */
    protected const ?string NO_LIMIT = '18446744073709551615';
}
