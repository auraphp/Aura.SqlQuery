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
 * A query method was called that the underlying database does not support.
 *
 * @package Aura.SqlQuery
 *
 */
class BadMethodCallException extends \BadMethodCallException implements SqlQueryException
{
}
