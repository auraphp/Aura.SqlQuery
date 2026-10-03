<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Mysql;

/**
 *
 * MySQL's reading of the SQL that spells no placeholder, for every MySQL
 * query: a condition is scanned for its placeholders with it, and a SELECT
 * reads its rendered UNION branches with it as well.
 *
 * @package Aura.SqlQuery
 *
 */
trait TextPatternTrait
{
    /**
     *
     * Regex alternatives matching the SQL that spells no placeholder, in the
     * forms MySQL reads them.
     *
     * Three readings differ from the standard. A backslash escapes the
     * character after it, so `'it\'s :a'` is one literal and the name inside
     * it is text rather than SQL the standard reading would leave over. A
     * hash begins a comment, which runs to the end of its line as `--` does.
     * And the dashes need whitespace after them to begin one at all, so
     * `c1 > 0--:a` is an operator and a placeholder here, where elsewhere it
     * is a comment; reading it as one would drop a name the branch binds.
     *
     * @return string
     *
     * @see \Aura\SqlQuery\AbstractQuery::getTextPattern()
     *
     */
    protected function getTextPattern(): string
    {
        return "'(?:[^'\\\\]|''|\\\\.)*+'|--(?:[ \t\r\n][^\n]*|$)|#[^\n]*|\/\*.*?\*\/";
    }
}
