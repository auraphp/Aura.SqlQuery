<?php
namespace Aura\SqlQuery\Common;

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

    public function testQuoteNamesInWithMultiWordAlias()
    {
        // legacy behavior: a multi-word alias is still quoted as a whole
        $sql = "legacy invalid as alias still works";
        $actual = $this->quoter->quoteNamesIn($sql);
        $expect = "legacy invalid AS \"alias still works\"";
        $this->assertSame($expect, $actual);
    }
}
