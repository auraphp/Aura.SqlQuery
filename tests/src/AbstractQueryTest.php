<?php
namespace Aura\Sql\Query;

use Aura\Sql\Query\QueryFactory;

abstract class AbstractQueryTest extends \PHPUnit_Framework_TestCase
{
    protected $query_type;
    
    protected $db_type;
    
    protected $query;

    protected function setUp()
    {
        parent::setUp();
        $query_factory = new QueryFactory;
        $this->query = $query_factory->newInstance(
            $this->query_type,
            $this->db_type
        );
    }
    
    protected function assertSameSql($expect, $actual)
    {
        // remove leading and trailing whitespace per block and line
        $expect = trim($expect);
        $expect = preg_replace('/^\s*/m', '', $expect);
        $expect = preg_replace('/\s*$/m', '', $expect);
        
        // convert "<<" and ">>" to the correct identifier quotes
        $expect = str_replace('<<', $this->query->getQuoteNamePrefix());
        $expect = str_replace('>>', $this->query->getQuoteNameSuffix());
        
        // remove leading and trailing whitespace per block and line
        $actual = trim($actual);
        $actual = preg_replace('/^\s*/m', '', $actual);
        $actual = preg_replace('/\s*$/m', '', $actual);
        
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
        $this->assertSame([], $actual);
        
        $expect = ['foo' => 'bar', 'baz' => 'dib'];
        $this->query->bindValues($expect);
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
        
        $this->query->bindValues(['zim' => 'gir']);
        $expect = ['foo' => 'bar', 'baz' => 'dib', 'zim' => 'gir'];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }
}
