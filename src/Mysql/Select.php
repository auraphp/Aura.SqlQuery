<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

/**
 *
 * An object for MySQL SELECT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class Select extends Common\Select
{
    use Common\LateralJoinTrait;

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
     * @var string
     *
     * @see Common\Select::$text_pattern
     *
     */
    protected $text_pattern = "'(?:[^'\\\\]|''|\\\\.)*+'|--(?:[ \t\r\n][^\n]*|$)|#[^\n]*|\/\*.*?\*\/";

    /**
     *
     * Does this join type reject an ON clause outright?
     *
     * MySQL treats CROSS JOIN and INNER JOIN as synonyms and accepts an ON
     * clause on either, so only NATURAL is rejected here -- unlike the
     * PostgreSQL default in the trait.
     *
     * @param string $join The upper-cased join clause.
     *
     * @return bool
     *
     */
    protected function joinForbidsCondition(string $join)
    {
        return str_starts_with($join, 'NATURAL ');
    }

    /**
     *
     * Adds or removes SQL_CALC_FOUND_ROWS flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function calcFoundRows(bool $enable = true)
    {
        $this->setFlag('SQL_CALC_FOUND_ROWS', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes SQL_CACHE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function cache(bool $enable = true)
    {
        $this->setFlag('SQL_CACHE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes SQL_NO_CACHE flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function noCache(bool $enable = true)
    {
        $this->setFlag('SQL_NO_CACHE', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes STRAIGHT_JOIN flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function straightJoin(bool $enable = true)
    {
        $this->setFlag('STRAIGHT_JOIN', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes HIGH_PRIORITY flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function highPriority(bool $enable = true)
    {
        $this->setFlag('HIGH_PRIORITY', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes SQL_SMALL_RESULT flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function smallResult(bool $enable = true)
    {
        $this->setFlag('SQL_SMALL_RESULT', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes SQL_BIG_RESULT flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function bigResult(bool $enable = true)
    {
        $this->setFlag('SQL_BIG_RESULT', $enable);
        return $this;
    }

    /**
     *
     * Adds or removes SQL_BUFFER_RESULT flag.
     *
     * @param bool $enable Set or unset flag (default true).
     *
     * @return $this
     *
     */
    public function bufferResult(bool $enable = true)
    {
        $this->setFlag('SQL_BUFFER_RESULT', $enable);
        return $this;
    }
}
