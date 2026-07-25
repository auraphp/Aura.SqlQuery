<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery;

/**
 *
 * Retained so that pre-6.x `catch (Aura\SqlQuery\Exception $e)` blocks
 * keep catching all package exceptions; to be removed in 7.x.
 *
 * @package Aura.SqlQuery
 *
 * @deprecated catch Aura\SqlQuery\ExceptionInterface instead.
 *
 */
interface Exception extends ExceptionInterface
{
}
