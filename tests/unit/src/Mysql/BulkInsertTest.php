<?php
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

class BulkInsertTest extends InsertTest
{
    protected $query_type = 'bulkinsert';

    protected $expected_sql_with_flag = "
        INSERT %s INTO <<t1>>
        (<<c1>>,<<c2>>,<<c3>>,<<c4>>,<<c5>>)
        VALUES
        (?,?,?,NOW(),NULL)
    ";

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
