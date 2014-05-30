<?php
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQueryTest;

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
        $this->query->into('table');
        $expect = null;
        $actual = $this->query->getLastInsertIdName('col');
        $this->assertSame($expect, $actual);
    }

    public function testSetLastInsertIdNames()
    {
        $this->query->setLastInsertIdNames(array(
            'table.col' => 'table_col_alternative_name',
        ));

        $this->query->into('table');
        $expect = 'table_col_alternative_name';
        $actual = $this->query->getLastInsertIdName('col');
        $this->assertSame($expect, $actual);
    }

    public function testBindValues()
    {
        $this->assertInstanceOf('\Aura\SqlQuery\AbstractQuery', $this->query->bindValues(array('bar', 'bar value')));
    }

    public function testBindValue()
    {
        $this->assertInstanceOf('\Aura\SqlQuery\AbstractQuery', $this->query->bindValue('bar', 'bar value'));
    }
}
