<?php
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\AbstractQueryTest;

class InsertTest extends AbstractQueryTest
{
    protected $query_type = 'insert';
    
    public function testCommon()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->col('c3')
                    ->set('c4', 'NOW()')
                    ->set('c5', null)
                    ->cols(array('cx' => 'cx_value'));
        
        $actual = $this->query->__toString();
        $expect = '
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>,
                <<c3>>,
                <<c4>>,
                <<c5>>,
                <<cx>>
            ) VALUES (
                :c1,
                :c2,
                :c3,
                NOW(),
                NULL,
                :cx
            )
        ';
        
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array('cx' => 'cx_value');
        $this->assertSame($expect, $actual);
    }
    
    public function testGetLastInsertIdName()
    {
        $this->assertNull($this->query->getLastInsertIdName('no matter'));
    }
}
