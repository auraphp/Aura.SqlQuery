<?php
namespace Aura\SqlQuery\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuoterTest extends TestCase
{
    protected $quoter = null;

    protected function setUp(): void
    {
        $this->quoter = new Quoter();
    }

    public function testQuoteName()
    {
        // table AS alias
        $actual = $this->quoter->quoteName('table AS alias');
        $this->assertSame('"table" AS "alias"', $actual);

        // table.col AS alias
        $actual = $this->quoter->quoteName('table.col AS alias');
        $this->assertSame('"table"."col" AS "alias"', $actual);

        // table alias
        $actual = $this->quoter->quoteName('table alias');
        $this->assertSame('"table" "alias"', $actual);

        // table.col alias
        $actual = $this->quoter->quoteName('table.col alias');
        $this->assertSame('"table"."col" "alias"', $actual);

        // plain old identifier
        $actual = $this->quoter->quoteName('table');
        $this->assertSame('"table"', $actual);

        // star
        $actual = $this->quoter->quoteName('*');
        $this->assertSame('*', $actual);

        // star dot star
        $actual = $this->quoter->quoteName('*.*');
        $this->assertSame('*.*', $actual);

        // table dot star
        $actual = $this->quoter->quoteName('table.*');
        $this->assertSame('"table".*', $actual);
    }

    public function testQuoteNamesIn()
    {
        $sql = "*, *.*, f.bar, foo.bar, CONCAT('foo.bar', \"baz.dib\") AS zim";
        $actual = $this->quoter->quoteNamesIn($sql);
        $expect = "*, *.*, \"f\".\"bar\", \"foo\".\"bar\", CONCAT('foo.bar', \"baz.dib\") AS \"zim\"";
        $this->assertSame($expect, $actual);
    }

    public function testQuoteNamesInWithCast()
    {
        // issue #157: the 'as' inside cast() must not be treated as an alias
        $sql = "cast(street_number as varchar) like :sn";
        $actual = $this->quoter->quoteNamesIn($sql);
        $this->assertSame($sql, $actual);
    }

    public function testQuoteNamesInWithCastAndAlias()
    {
        $sql = "cast(t.street_number as varchar) AS street";
        $actual = $this->quoter->quoteNamesIn($sql);
        $expect = "cast(\"t\".\"street_number\" as varchar) AS \"street\"";
        $this->assertSame($expect, $actual);
    }

    public function testQuoteNamesInWithNonWordAlias()
    {
        // aliases with non-word characters must still be quoted
        $actual = $this->quoter->quoteNamesIn('t.foo AS foo-bar');
        $this->assertSame('"t"."foo" AS "foo-bar"', $actual);

        // an alias beginning with a digit
        $actual = $this->quoter->quoteNamesIn('t.foo AS 2col');
        $this->assertSame('"t"."foo" AS "2col"', $actual);

        // a dollar-sign identifier
        $actual = $this->quoter->quoteNamesIn('t.foo AS foo$bar');
        $this->assertSame('"t"."foo" AS "foo$bar"', $actual);
    }

    public function testQuoteNamesInWithHash()
    {
        // issue #177: DB2 / IBM i allow # in identifiers, in any position
        $actual = $this->quoter->quoteNamesIn('a.g01gl# ASC');
        $this->assertSame('"a"."g01gl#" ASC', $actual);

        $actual = $this->quoter->quoteNamesIn('lib.#col');
        $this->assertSame('"lib"."#col"', $actual);

        $actual = $this->quoter->quoteNamesIn('lib#.tab = :v');
        $this->assertSame('"lib#"."tab" = :v', $actual);

        $actual = $this->quoter->quoteNamesIn('#lib.tab');
        $this->assertSame('"#lib"."tab"', $actual);

        $actual = $this->quoter->quoteNamesIn('t.foo#bar');
        $this->assertSame('"t"."foo#bar"', $actual);
    }

    /**
     * The same input, through every quoter the package ships. In the input
     * and the expectation, {p} and {s} stand for the dialect's own quote
     * prefix and suffix, so one case covers all three.
     */
    #[DataProvider('provideQuoteNamesIn')]
    public function testQuoteNamesInAcrossDialects(string $input, string $expect)
    {
        $quoters = [
            'common' => new Quoter(),
            'mysql' => new \Aura\SqlQuery\Mysql\Quoter(),
            'sqlsrv' => new \Aura\SqlQuery\Sqlsrv\Quoter(),
        ];

        foreach ($quoters as $db_type => $quoter) {
            $markers = ['{p}', '{s}'];
            $quotes = [$quoter->getQuoteNamePrefix(), $quoter->getQuoteNameSuffix()];
            $actual = $quoter->quoteNamesIn(str_replace($markers, $quotes, $input));
            $this->assertSame(
                str_replace($markers, $quotes, $expect),
                $actual,
                "quoting for {$db_type}"
            );
        }
    }

    public static function provideQuoteNamesIn(): array
    {
        return [
            'star' => ['*', '*'],
            'star dot star' => ['*.*', '*.*'],
            'table dot star' => ['t.*', 't.*'],
            'qualified name' => ['foo.bar', '{p}foo{s}.{p}bar{s}'],
            'name with alias' => ['foo.bar AS zim', '{p}foo{s}.{p}bar{s} AS {p}zim{s}'],
            'alias beginning with a digit' => [
                't.foo AS 2col',
                '{p}t{s}.{p}foo{s} AS {p}2col{s}',
            ],
            'dollar sign in alias' => [
                't.foo AS foo$bar',
                '{p}t{s}.{p}foo{s} AS {p}foo$bar{s}',
            ],
            // issue #177: '#' is an identifier character on DB2 / IBM i
            'hash at end' => ['a.g01gl# ASC', '{p}a{s}.{p}g01gl#{s} ASC'],
            'hash at start of column' => ['lib.#col', '{p}lib{s}.{p}#col{s}'],
            'hash at start of table' => ['#lib.tab', '{p}#lib{s}.{p}tab{s}'],
            'hash inside a name' => ['t.foo#bar', '{p}t{s}.{p}foo#bar{s}'],
            'aggregate' => ['COUNT(*) > :least', 'COUNT(*) > :least'],
            // issue #157: the 'AS' inside cast() is not an alias
            'cast' => [
                'CAST(t.salary AS CHAR) AS salary_text',
                'CAST({p}t{s}.{p}salary{s} AS CHAR) AS {p}salary_text{s}',
            ],
            'function call' => [
                'find_in_set(t.col, :p)',
                'find_in_set({p}t{s}.{p}col{s}, :p)',
            ],
            'comparison of two names' => [
                't.a = u.b',
                '{p}t{s}.{p}a{s} = {p}u{s}.{p}b{s}',
            ],
            'placeholder list' => ['t.a IN (:list)', '{p}t{s}.{p}a{s} IN (:list)'],
            'between' => [
                't.a BETWEEN :lo AND :hi',
                '{p}t{s}.{p}a{s} BETWEEN :lo AND :hi',
            ],
            'single quoted literal' => ["t.c = 'a.b'", "{p}t{s}.{p}c{s} = 'a.b'"],
            'escaped single quote' => ["t.c = 'it''s'", "{p}t{s}.{p}c{s} = 'it''s'"],
            'literal beside a name' => [
                "t.c LIKE '%.%' AND t.d = u.e",
                "{p}t{s}.{p}c{s} LIKE '%.%' AND {p}t{s}.{p}d{s} = {p}u{s}.{p}e{s}",
            ],
            // the brackets are inside a literal, so even SQL Server, which
            // quotes with brackets, must leave them alone
            'bracket class inside a literal' => [
                "t.c LIKE '[a-c]%'",
                "{p}t{s}.{p}c{s} LIKE '[a-c]%'",
            ],
            'multi-word alias' => [
                'legacy invalid as alias still works',
                'legacy invalid AS {p}alias still works{s}',
            ],
            'empty string' => ['', ''],
            'only a space' => [' ', ' '],
            // issue #183: a name the caller quoted by hand, because the name
            // itself contains a dot, is passed through as written
            'pre-quoted dotted name' => [
                'find_in_set(tests.{p}compound.group{s}, :g)',
                'find_in_set(tests.{p}compound.group{s}, :g)',
            ],
            'two pre-quoted dotted names' => [
                't.{p}a.b{s} = u.{p}c.d{s}',
                't.{p}a.b{s} = u.{p}c.d{s}',
            ],
            'pre-quoted name beside a literal' => [
                "t.{p}a.b{s} = 'x.y' AND u.c = v.d",
                "t.{p}a.b{s} = 'x.y' AND {p}u{s}.{p}c{s} = {p}v{s}.{p}d{s}",
            ],
            'pre-quoted name without a dot' => ['t.{p}a{s}', 't.{p}a{s}'],
            'already quoted output is left alone' => [
                '{p}t{s}.{p}c{s}',
                '{p}t{s}.{p}c{s}',
            ],
        ];
    }

    public function testQuoteNamesInWithMultiWordAlias()
    {
        // legacy behavior: a multi-word alias is still quoted as a whole
        $sql = "legacy invalid as alias still works";
        $actual = $this->quoter->quoteNamesIn($sql);
        $expect = "legacy invalid AS \"alias still works\"";
        $this->assertSame($expect, $actual);
    }
}
