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
 * Developer error, e.g. misuse of a query builder.
 *
 * @package Aura.SqlQuery
 *
 */
class LogicException extends \LogicException implements SqlQueryException
{
}
