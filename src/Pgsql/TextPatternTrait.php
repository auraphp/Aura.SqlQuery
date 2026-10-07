<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Pgsql;

/**
 *
 * PostgreSQL's reading of the SQL that spells no placeholder, for every
 * PostgreSQL query: a condition is scanned for its placeholders with it, and
 * a SELECT reads its rendered UNION branches with it as well.
 *
 * @package Aura.SqlQuery
 *
 */
trait TextPatternTrait
{
    /**
     *
     * Regex alternatives matching the SQL that spells no placeholder, in the
     * forms PostgreSQL reads them: the standard ones, plus two literals only
     * PostgreSQL has. An escape string, `E'...'`, takes a backslash as an
     * escape, so `E'it\'s ?'` is one literal. A dollar-quoted string,
     * `$$...$$` or `$tag$...$tag$`, ends only at the same tag, with nothing
     * escaped inside it; a `$` that follows an identifier character is part
     * of that identifier and opens none (#276). A `?` or a `:name` in either
     * is text.
     *
     * The tag is captured even when empty, so that the closing tag always has
     * a group to match against.
     *
     * @return string
     *
     * @see \Aura\SqlQuery\AbstractQuery::getTextPattern()
     *
     */
    protected function getTextPattern(): string
    {
        return "(?<!\\w)[Ee]'(?:[^'\\\\]|\\\\.|'')*+'"
            . '|(?<![\w$])\$(?<dollar_tag>(?:[A-Za-z_]\w*)?)\$.*?\$\k<dollar_tag>\$|'
            . parent::getTextPattern();
    }
}
