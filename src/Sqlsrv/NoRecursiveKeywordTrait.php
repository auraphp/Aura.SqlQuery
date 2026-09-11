<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlsrv;

/**
 *
 * SQL Server infers the recursion and rejects the RECURSIVE keyword, so every
 * builder for this dialect renders a plain WITH.
 *
 * @package Aura.SqlQuery
 *
 */
trait NoRecursiveKeywordTrait
{
    /**
     *
     * SQL Server does not support the RECURSIVE keyword in CTE.
     *
     * @return bool
     *
     */
    protected function allowsRecursiveKeyword(): bool
    {
        return false;
    }
}
