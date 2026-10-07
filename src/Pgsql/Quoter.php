<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

/**
 *
 * Quote for PostgreSQL.
 *
 * @package Aura.SqlQuery
 *
 */
class Quoter extends Common\Quoter
{
    /**
     *
     * Adds PostgreSQL's dollar-quoted string, `$$...$$` or `$tag$...$tag$`,
     * to the literals that quoteNamesIn() leaves as written (issue #274). It
     * ends only at the same tag, and may span lines. A `$` that follows an
     * identifier character is part of that identifier, not an opening tag.
     *
     * @return string
     *
     */
    protected function getTextPatternForQuoteNamesIn(): string
    {
        return '((?<![\w$])(\$(?:[A-Za-z_]\w*)?\$)(?s:.*?)\2)|'
            . parent::getTextPatternForQuoteNamesIn();
    }
}
