<?php
namespace Aura\Sql_Query;

class QueryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // use double-quotes for identifier quoting
        $this->query = new MockQuery('"', '"');
    }
    
    public function testQuoteName()
    {
        // table AS alias
        $actual = $this->query->quoteName('table AS alias');
        $this->assertSame('"table" AS "alias"', $actual);
        
        // table.col AS alias
        $actual = $this->query->quoteName('table.col AS alias');
        $this->assertSame('"table"."col" AS "alias"', $actual);
        
        // table alias
        $actual = $this->query->quoteName('table alias');
        $this->assertSame('"table" "alias"', $actual);
        
        // table.col alias
        $actual = $this->query->quoteName('table.col alias');
        $this->assertSame('"table"."col" "alias"', $actual);
        
        // plain old identifier
        $actual = $this->query->quoteName('table');
        $this->assertSame('"table"', $actual);
        
        // star
        $actual = $this->query->quoteName('*');
        $this->assertSame('*', $actual);
        
        // star dot star
        $actual = $this->query->quoteName('*.*');
        $this->assertSame('*.*', $actual);
    }
    
    public function testQuoteNamesIn()
    {
        $sql = "*, *.*, foo.bar, CONCAT('foo.bar', \"baz.dib\") AS zim";
        $actual = $this->query->quoteNamesIn($sql);
        $expect = "*, *.*, \"foo\".\"bar\", CONCAT('foo.bar', \"baz.dib\") AS \"zim\"";
        $this->assertSame($expect, $actual);
    }
}
