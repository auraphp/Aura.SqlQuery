<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

/**
 *
 * A quoting mechanism for identifier names (not values).
 *
 * @package Aura.SqlQuery
 *
 */
class Quoter implements QuoterInterface
{
    /**
     *
     * The prefix to use when quoting identifier names.
     *
     * @var string
     *
     */
    protected $quote_name_prefix = '"';

    /**
     *
     * The suffix to use when quoting identifier names.
     *
     * @var string
     *
     */
    protected $quote_name_suffix = '"';

    /**
     *
     * Returns the prefix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNamePrefix()
    {
        return $this->quote_name_prefix;
    }

    /**
     *
     * Returns the suffix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNameSuffix()
    {
        return $this->quote_name_suffix;
    }

    /**
     *
     * Quotes a single identifier name (table, table alias, table column,
     * index, sequence).
     *
     * If the name contains `' AS '`, this method will separately quote the
     * parts before and after the `' AS '`.
     *
     * If the name contains a space, this method will separately quote the
     * parts before and after the space.
     *
     * If the name contains a dot, this method will separately quote the
     * parts before and after the dot.
     *
     * @param string $spec The identifier name to quote.
     *
     * @return string The quoted identifier name.
     *
     * @see replaceName()
     *
     * @see quoteNameWithSeparator()
     *
     */
    public function quoteName($spec)
    {
        $spec = trim($spec);
        $seps = array(' AS ', ' ', '.');
        foreach ($seps as $sep) {
            $pos = strripos($spec, $sep);
            if ($pos) {
                return $this->quoteNameWithSeparator($spec, $sep, $pos);
            }
        }
        return $this->replaceName($spec);
    }

    /**
     *
     * Quotes an identifier that has a separator.
     *
     * @param string $spec The identifier name to quote.
     *
     * @param string $sep The separator, typically a dot or space.
     *
     * @param int $pos The position of the separator.
     *
     * @return string The quoted identifier name.
     *
     */
    protected function quoteNameWithSeparator($spec, $sep, $pos)
    {
        $len = strlen($sep);
        $part1 = $this->quoteName(substr($spec, 0, $pos));
        $part2 = $this->replaceName(substr($spec, $pos + $len));
        return "{$part1}{$sep}{$part2}";
    }

    /**
     *
     * Quotes all fully-qualified identifier names ("table.col") in a string,
     * typically an SQL snippet for a SELECT clause.
     *
     * Does not quote identifier names that are string literals (i.e., inside
     * single or double quotes).
     *
     * Looks for a trailing ' AS alias' and quotes the alias as well.
     *
     * @param string $text The string in which to quote fully-qualified
     * identifier names to quote.
     *
     * @return string|array The string with names quoted in it.
     *
     * @see replaceNamesIn()
     *
     */
    public function quoteNamesIn($text)
    {
        $list = $this->getListForQuoteNamesIn($text);
        $last = count($list) - 1;
        $text = null;
        foreach ($list as $key => $val) {
            // skip elements 2, 5, 8, 11, etc. as artifacts of the back-
            // referenced split; these are the trailing/ending quote
            // portions, and already included in the previous element.
            // this is the same as skipping every third element from zero.
            if (($key+1) % 3) {
                $text .= $this->quoteNamesInLoop($val, $key == $last);
            }
        }
        return $text;
    }

    /**
     *
     * Returns a list of candidate elements for quoting.
     *
     * @param string $text The text to split into quoting candidates.
     *
     * @return array
     *
     */
    protected function getListForQuoteNamesIn($text)
    {
        // look for ', ", \', or \" in the string.
        // match closing quotes against the same number of opening quotes.
        $apos = "'";
        $quot = '"';

        // issue #183: an identifier the caller quoted by hand, because the
        // name itself contains a dot (`compound.group`), is a candidate too;
        // it is passed through verbatim rather than split on that dot.
        $prefix = preg_quote($this->quote_name_prefix, '/');
        $suffix = preg_quote($this->quote_name_suffix, '/');
        $quoted_name = "{$prefix}[^{$suffix}]*{$suffix}";

        // branch reset, so that either alternative captures the same two
        // group numbers; quoteNamesIn() skips every third element on that
        // basis
        return preg_split(
            "/(?|(($apos+|$quot+|\\$apos+|\\$quot+).*?\\2)|(($quoted_name)))/",
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
    }

    /**
     *
     * The in-loop functionality for quoting identifier names.
     *
     * @param string $val The name to be quoted.
     *
     * @param bool $is_last Is this the last loop?
     *
     * @return string The quoted name.
     *
     */
    protected function quoteNamesInLoop($val, $is_last)
    {
        if ($is_last) {
            return $this->replaceNamesAndAliasIn($val);
        }
        return $this->replaceNamesIn($val);
    }

    /**
     *
     * Replaces the names and alias in a string.
     *
     * @param string $val The name to be quoted.
     *
     * @return string The quoted name.
     *
     */
    protected function replaceNamesAndAliasIn($val)
    {
        $quoted = $this->replaceNamesIn($val);
        $pos = $this->findAliasSeparator($quoted);
        if ($pos !== false) {
            $alias = $this->replaceName(substr($quoted, $pos + 4));
            $quoted = substr($quoted, 0, $pos) . " AS $alias";
        }
        return $quoted;
    }

    /**
     *
     * Finds the position of the last ' AS ' that acts as an alias separator;
     * an 'AS' inside parentheses, e.g. cast(col as varchar), is part of an
     * expression, not an alias.
     *
     * @param string $text The string to search.
     *
     * @return int|false The position of the separator, or false if none.
     *
     */
    protected function findAliasSeparator($text)
    {
        $pos = strripos($text, ' AS ');
        while ($pos !== false) {
            $before = substr($text, 0, $pos);
            $after = substr($text, $pos + 4);
            $inside_parens = substr_count($before, '(') > substr_count($before, ')');
            $parens_after = strpbrk($after, '()') !== false;
            if (! $inside_parens && ! $parens_after) {
                return $pos;
            }
            $pos = strripos($before, ' AS ');
        }
        return false;
    }

    /**
     *
     * Quotes an identifier name (table, index, etc); ignores empty values and
     * values of '*'.
     *
     * @param string $name The identifier name to quote.
     *
     * @return string The quoted identifier name.
     *
     * @see quoteName()
     *
     */
    protected function replaceName($name)
    {
        $name = trim($name);
        if ($name == '*') {
            return $name;
        }

        return $this->quote_name_prefix
             . $name
             . $this->quote_name_suffix;
    }

    /**
     *
     * Is this text a single identifier that the caller already quoted?
     *
     * @param string $text The text to test.
     *
     * @return bool
     *
     */
    protected function isQuotedName($text)
    {
        $len = strlen($this->quote_name_prefix) + strlen($this->quote_name_suffix);
        return strlen($text) >= $len
            && str_starts_with($text, $this->quote_name_prefix)
            && str_ends_with($text, $this->quote_name_suffix);
    }

    /**
     *
     * Quotes all fully-qualified identifier names ("table.col") in a string.
     *
     * @param string $text The string in which to quote fully-qualified
     * identifier names to quote.
     *
     * @return string|array The string with names quoted in it.
     *
     * @see quoteNamesIn()
     *
     */
    protected function replaceNamesIn($text)
    {
        $is_string_literal = strpos($text, "'") !== false
                        || strpos($text, '"') !== false;
        if ($is_string_literal) {
            return $text;
        }

        // issue #183: leave an already-quoted identifier as the caller wrote
        // it. getListForQuoteNamesIn() splits those out on their own, so the
        // test is that this element *is* one, not merely that it contains a
        // quote character: an unbalanced quote is not an identifier, and is
        // quoted the way it always was.
        if ($this->isQuotedName($text)) {
            return $text;
        }

        // issue #177: '#' is a legal identifier character on DB2 / IBM i,
        // in any position; lookarounds instead of \b, because there is no
        // word boundary next to a '#'
        $word = "[a-z_#][a-z0-9_#]*";

        $find = "/(?<![\\w#])($word)\\.($word)(?![\\w#])/i";

        $repl = $this->quote_name_prefix
              . '$1'
              . $this->quote_name_suffix
              . '.'
              . $this->quote_name_prefix
              . '$2'
              . $this->quote_name_suffix
              ;

        $text = preg_replace($find, $repl, $text);

        return $text;
    }

}
