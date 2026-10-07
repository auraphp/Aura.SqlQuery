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
     * identifier character is part of that identifier, not an opening tag;
     * PostgreSQL reads bytes, and any byte from 0x80 up is an identifier
     * character, so `é$$b` is one name. The pattern reads bytes too, without
     * the u modifier, so text that is not valid UTF-8 is still quoted.
     *
     * @return string
     *
     */
    protected function getTextPatternForQuoteNamesIn(): string
    {
        return '((?<![\w$\x80-\xFF])(\$(?:[A-Za-z_]\w*)?\$)(?s:.*?)\2)|'
            . parent::getTextPatternForQuoteNamesIn();
    }
}
