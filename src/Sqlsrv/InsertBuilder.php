<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

/**
 *
 * An object for Sqlsrv INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class InsertBuilder extends Common\InsertBuilder
{
    use NoRecursiveKeywordTrait;
}
