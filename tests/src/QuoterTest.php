<?php
namespace Aura\SqlQuery;

class QuoterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // use double-quotes for identifier quoting
        $this->quoter = new Quoter('`', '`');
    }

    public function testQuoteName()
    {
        // table AS alias
        $actual = $this->quoter->quoteName('table AS alias');
        $this->assertSame('`table` AS `alias`', $actual);

        // table.col AS alias
        $actual = $this->quoter->quoteName('table.col AS alias');
        $this->assertSame('`table`.`col` AS `alias`', $actual);

        // table alias
        $actual = $this->quoter->quoteName('table alias');
        $this->assertSame('`table` `alias`', $actual);

        // table.col alias
        $actual = $this->quoter->quoteName('table.col alias');
        $this->assertSame('`table`.`col` `alias`', $actual);

        // plain old identifier
        $actual = $this->quoter->quoteName('table');
        $this->assertSame('`table`', $actual);

        // star
        $actual = $this->quoter->quoteName('*');
        $this->assertSame('*', $actual);

        // star dot star
        $actual = $this->quoter->quoteName('*.*');
        $this->assertSame('*.*', $actual);
    }

    public function testQuoteNamesIn()
    {
        $sql = "*, *.*, foo.bar, CONCAT('foo.bar', \"baz.dib\") AS zim";
        $actual = $this->quoter->quoteNamesIn($sql);
        $expect = "*, *.*, `foo`.`bar`, CONCAT('foo.bar', \"baz.dib\") AS `zim`";
        $this->assertSame($expect, $actual);
    }

    // no not quote with trailing parentheses
    public function testIssue24()
    {
        $actual = $this->quoter->quoteName('foo()');
        $this->assertSame('foo()', $actual);

        $actual = $this->quoter->quoteName('foo(bar)');
        $this->assertSame('foo(bar)', $actual);

        $actual = $this->quoter->quoteName('foo.bar()');
        $this->assertSame('`foo`.bar()', $actual);

        $actual = $this->quoter->quoteName('foo().bar');
        $this->assertSame('foo().`bar`', $actual);

        $sql = "schema.foo(), schema.foo('foo.bar', baz.dib, zim)";
        $actual = $this->quoter->quoteNamesIn($sql);
        $expect = "`schema`.foo(), `schema`.foo('foo.bar', `baz`.`dib`, zim)";
        $this->assertSame($expect, $actual);
    }
}
