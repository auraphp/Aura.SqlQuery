<?php
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQueryTest;

class UpdateTest extends AbstractQueryTest
{
    protected $query_type = 'update';

    public function testCommon()
    {
        $this->query->table('t1')
                    ->cols(array('c1', 'c2'))
                    ->col('c3')
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir');

        $actual = $this->query->__toString();
        $expect = "
            UPDATE <<t1>>
            SET
                <<c1>> = :c1,
                <<c2>> = :c2,
                <<c3>> = :c3,
                <<c4>> = NULL,
                <<c5>> = NOW()
            WHERE
                foo = :_1_
                AND baz = :_2_
                OR zim = gir
        ";

        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = array(
            '_1_' => 'bar',
            '_2_' => 'dib',
        );
        $this->assertSame($expect, $actual);
    }

    /**
     * @dataProvider clearSqlPartsProvider
     */
    public function testClearSqlParts($part, $method, $value, $partValue, $clearedValue)
    {

        if($value instanceof \ArrayObject)
        {
            call_user_func_array(array($this->query, $method), $value->getArrayCopy());
        }
        else
        {
            $this->query->$method($value);
        }
        if($partValue === true)
        {
            // only check it has a value (may differ depending on Select implementation)
            $this->assertAttributeNotEmpty($part, $this->query);
        }
        else {
            $this->assertAttributeEquals($partValue, $part, $this->query);
        }
        $this->query->clear($part);
        $this->assertAttributeEquals($clearedValue, $part, $this->query);

    }

    /**
     * Data provider for method testClearSqlParts
     *
     * @return array
     */
    public function clearSqlPartsProvider()
    {
        return array(
            array('where', 'where', 'x = y', array('x = y'), array()),
            array('table', 'table', 'table_name', true, null),
            array('col_values', 'set', new \ArrayObject(array('column', 'value')), true, null),
        );
    }
}
