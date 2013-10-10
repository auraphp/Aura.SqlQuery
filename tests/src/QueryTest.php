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
    
    public function testBindCondValue()
    {
        $actual = [];
        
        $expect = [];
        $this->query->bindCondValue('foo = bar', [], $actual);
        $this->assertSame($expect, $actual);
        
        $expect = [
            0 => 'foo',
        ];
        $this->query->bindCondValue('foo = ?', ['foo'], $actual);
        $this->assertSame($expect, $actual);
        
        // this is a problem, because quoting at ExtendedPdo level *does not*
        // quote sequential question marks to CSV arrays.
        $expect = [
            0 => 'foo',
            1 => ['bar', 'baz', 'dib'],
        ];
        $this->query->bindCondValue('foo IN (?)', [['bar', 'baz', 'dib']], $actual);
        $this->assertSame($expect, $actual);
        
        $expect = [
            0 => 'foo',
            1 => ['bar', 'baz', 'dib'],
            2 => 10,
            3 => 20,
        ];
        $this->query->bindCondValue('foo BETWEEN ? AND ?', [10, 20], $actual);
        $this->assertSame($expect, $actual);
    }
}
