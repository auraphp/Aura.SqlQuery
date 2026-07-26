<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

class SelectTest extends Common\SelectTest
{
    use Common\LateralJoinTestTrait;

    protected $db_type = 'pgsql';

    /**
     * Postgres rejects an ON clause on a CROSS join, so supplying a condition
     * could only ever produce a syntax error at execute time. MySQL differs;
     * see Mysql\SelectTest.
     */
    public function testLateralJoinSubSelect_crossWithConditionThrows()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('CROSS JOIN LATERAL cannot take a condition');

        $this->query->lateralJoinSubSelect(
            'cross',
            'SELECT * FROM t2',
            'a2',
            't1.c1 = a2.c1'
        );
    }
}
