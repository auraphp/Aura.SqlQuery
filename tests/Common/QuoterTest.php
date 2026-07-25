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
            // issue #226: the dot in a variable is a scope separator, not a
            // table/column separator
            'session variable' => ['@@session.time_zone', '@@session.time_zone'],
            'global variable' => [
                '@@global.max_connections',
                '@@global.max_connections',
            ],
            // a MySQL user variable name may itself contain dots, '$' and
            // '_', so the whole token is left alone, not just the part
            // right after the '@'
            'user variable' => ['@var.name', '@var.name'],
            'user variable with two dots' => ['@a.b.c', '@a.b.c'],
            'user variable with a dollar sign' => ['@my$var.name', '@my$var.name'],
            'variable then a real name' => [
                '@x.y = t.a',
                '@x.y = {p}t{s}.{p}a{s}',
            ],
            'real name then a variable' => [
                't.a = @x.y',
                '{p}t{s}.{p}a{s} = @x.y',
            ],
            'variable beside a real name' => [
                'convert_tz(t.open_from, t.time_zone, @@session.time_zone)',
                'convert_tz({p}t{s}.{p}open_from{s}, {p}t{s}.{p}time_zone{s}, @@session.time_zone)',
            ],
            'variable with an alias' => [
                '@@session.time_zone AS tz',
                '@@session.time_zone AS {p}tz{s}',
            ],
            'variable with no dot' => ['@@ROWCOUNT', '@@ROWCOUNT'],
            'variable inside a string literal' => [
                "t.c = '@a.b'",
                "{p}t{s}.{p}c{s} = '@a.b'",
            ],
            // PostgreSQL spells its text-search match and its JSONPath
            // predicate check '@@'. The variable pattern stops at the space,
            // so a bare '@@' is passed through as the operator it is and the
            // name after it is still quoted.
            'pgsql text search operator' => [
                't.doc @@ q.tsq',
                '{p}t{s}.{p}doc{s} @@ {p}q{s}.{p}tsq{s}',
            ],
            'pgsql jsonpath predicate operator' => [
                't.payload @@ p.expr',
                '{p}t{s}.{p}payload{s} @@ {p}p{s}.{p}expr{s}',
            ],
            'pgsql operator before a function call' => [
                't.doc @@ to_tsquery(u.cfg, :q)',
                '{p}t{s}.{p}doc{s} @@ to_tsquery({p}u{s}.{p}cfg{s}, :q)',
            ],
            'pgsql containment operator' => [
                't.payload @> u.filter',
                '{p}t{s}.{p}payload{s} @> {p}u{s}.{p}filter{s}',
            ],
            // known limitation: with no space, '@@x.y' is indistinguishable
            // from a variable named '@@x.y', so the name is left alone. Write
            // the operator with spaces around it.
            'pgsql operator with no space after it' => [
                't.doc @@x.y',
                '{p}t{s}.{p}doc{s} @@x.y',
            ],
            // the #183 and #226 rules meet: one name quoted by hand, one
            // variable, and a plain reference to quote between them
            'pre-quoted name and a variable' => [
                't.{p}a.b{s} = @v.x AND u.c = :p',
                't.{p}a.b{s} = @v.x AND {p}u{s}.{p}c{s} = :p',
            ],
            'pre-quoted name containing an at sign' => [
                '{p}@weird.col{s} = t.a',
                '{p}@weird.col{s} = {p}t{s}.{p}a{s}',
            ],
            'count distinct' => [
                'COUNT(DISTINCT t.x)',
                'COUNT(DISTINCT {p}t{s}.{p}x{s})',
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
