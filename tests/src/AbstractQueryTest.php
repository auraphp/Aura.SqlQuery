<?php
namespace Aura\Sql_Query;

abstract class AbstractQueryTest extends \PHPUnit_Framework_TestCase
{
    protected $query_type;
    
    protected $db_type = 'Common';
    
    protected $query;

    protected function setUp()
    {
        parent::setUp();
        $this->query_factory = new QueryFactory($this->db_type);
        $this->query = $this->newQuery();
    }
    
    protected function newQuery()
    {
        $method = 'new' . $this->query_type;
        return $this->query_factory->$method();
    }
    
    protected function assertSameSql($expect, $actual)
    {
        // remove leading and trailing whitespace per block and line
        $expect = trim($expect);
        $expect = preg_replace('/^[ \t]*/m', '', $expect);
        $expect = preg_replace('/[ \t]*$/m', '', $expect);
        
        // convert "<<" and ">>" to the correct identifier quotes
        $expect = str_replace('<<', $this->query->getQuoteNamePrefix(), $expect);
        $expect = str_replace('>>', $this->query->getQuoteNameSuffix(), $expect);
        
        // remove leading and trailing whitespace per block and line
        $actual = trim($actual);
        $actual = preg_replace('/^[ \t]*/m', '', $actual);
        $actual = preg_replace('/[ \t]*$/m', '', $actual);
        
        // are they the same now?
        $this->assertSame($expect, $actual);
    }
    
    protected function tearDown()
    {
        parent::tearDown();
    }
    
    public function testBindValues()
    {
        $actual = $this->query->getBindValues();
        $this->assertSame(array(), $actual);
        
        $expect = array('foo' => 'bar', 'baz' => 'dib');
        $this->query->bindValues($expect);
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
        
        $this->query->bindValues(array('zim' => 'gir'));
        $expect = array('foo' => 'bar', 'baz' => 'dib', 'zim' => 'gir');
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }
}
