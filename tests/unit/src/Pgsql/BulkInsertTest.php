<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

class BulkInsertTest extends InsertTest
{
    protected $query_type = 'bulkinsert';

    public function testReturning()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null)
                    ->returning(array('c1', 'c2'))
                    ->returning(array('c3'));

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>>
            (<<c1>>,<<c2>>,<<c3>>,<<c4>>,<<c5>>)
            VALUES
            (?,?,?,NOW(),NULL)
            RETURNING c1,c2,c3
        ";

        $this->assertSameSql($expect, $actual);
    }

    public function testCommon()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c3', 'NOW()')
                    ->bindValues(
                        array(
                            array("c1" => 1, "c2" => 2, "c3" => 3),
                            array("c1" => 1, "c2" => 2, "c3" => 3)
                        )
                    );

        $actual = $this->query->__toString();
        $expect = '
            INSERT INTO <<t1>>
            (<<c1>>,<<c2>>,<<c3>>)
            VALUES
            (?,?,NOW()),
            (?,?,NOW())
        ';

        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = array(1, 2, 3, 1, 2, 3);
        $this->assertSame($expect, $actual);
    }
}
