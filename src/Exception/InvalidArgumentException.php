<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Exception;

use Aura\SqlQuery\Exception as SqlQueryException;

/**
 *
 * A value passed to a query builder does not match what the builder expects.
 *
 * @package Aura.SqlQuery
 *
 */
class InvalidArgumentException extends \InvalidArgumentException implements SqlQueryException
{
}
