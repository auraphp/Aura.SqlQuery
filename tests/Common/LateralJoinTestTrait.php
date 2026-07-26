<?php
namespace Aura\SqlQuery\Common;

/**
 *
 * Shared LATERAL join assertions for the dialects that support them.
 *
 */
trait LateralJoinTestTrait
{
    public function testLateralJoinSubSelect()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');
        $this->query->lateralJoinSubSelect(
            'left',
            'SELECT * FROM t2 WHERE t2.c1 = t1.c1',
            'a2',
            't2.c1 = a2.c1'
        );
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN LATERAL (
                        SELECT * FROM t2 WHERE t2.c1 = t1.c1
                    ) <<a2>> ON <<t2>>.<<c1>> = <<a2>>.<<c1>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testLateralJoinSubSelect_selectObject()
    {
        $sub = $this->newQuery();
        $sub->cols(['*'])->from('t2')->where('t2.c2 = :v', ['v' => 9]);

        $this->query->cols(['*']);
        $this->query->from('t1');
        $this->query->lateralJoinSubSelect('inner', $sub, 'a2', 't2.c1 = a2.c1');

        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    INNER JOIN LATERAL (
                        SELECT
                            *
                        FROM
                            <<t2>>
                        WHERE
                            <<t2>>.<<c2>> = :v
                    ) <<a2>> ON <<t2>>.<<c1>> = <<a2>>.<<c1>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $this->assertSame(['v' => 9], $this->query->getBindValues());
    }

    /**
     * A LATERAL join needs an ON clause on every join type except CROSS and
     * NATURAL; without one the statement is a syntax error.
     */
    public function testLateralJoinSubSelect_noCondition()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');
        $this->query->lateralJoinSubSelect(
            'left',
            'SELECT * FROM t2 WHERE t2.c1 = t1.c1',
            'a2'
        );
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN LATERAL (
                        SELECT * FROM t2 WHERE t2.c1 = t1.c1
                    ) <<a2>> ON true
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    /**
     * CROSS JOIN LATERAL takes no ON clause at all.
     */
    public function testLateralJoinSubSelect_cross()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');
        $this->query->lateralJoinSubSelect(
            'cross',
            'SELECT * FROM t2 WHERE t2.c1 = t1.c1',
            'a2'
        );
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    CROSS JOIN LATERAL (
                        SELECT * FROM t2 WHERE t2.c1 = t1.c1
                    ) <<a2>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    /**
     * A NATURAL join derives its condition from the common column names and
     * rejects ON on every dialect, so supplying a condition could only ever
     * produce a syntax error at execute time. Fail loudly while building
     * instead.
     *
     * CROSS is not covered here because the dialects disagree: Postgres
     * rejects a condition, MySQL accepts one. See the per-dialect tests.
     */
    public function testLateralJoinSubSelect_naturalWithConditionThrows()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('NATURAL JOIN LATERAL cannot take a condition');

        $this->query->lateralJoinSubSelect(
            'natural',
            'SELECT * FROM t2',
            'a2',
            't1.c1 = a2.c1'
        );
    }

    /**
     * A rejected join must not leave a table reference behind, or a caught
     * exception would corrupt the query.
     */
    public function testLateralJoinSubSelect_rejectedJoinLeavesNoTableRef()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');

        try {
            $this->query->lateralJoinSubSelect('natural', 'SELECT * FROM t2', 'a2', 'true');
            $this->fail('expected a LogicException');
        } catch (\Aura\SqlQuery\Exception\LogicException $e) {
            // the alias is still free, so this must not throw
            $this->query->lateralJoinSubSelect('natural', 'SELECT * FROM t2', 'a2');
        }

        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    NATURAL JOIN LATERAL (
                        SELECT * FROM t2
                    ) <<a2>>
        ';
        $this->assertSameSql($expect, $this->query->__toString());
    }

    public function testLateralJoinSubSelect_duplicateRef()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');
        $this->query->lateralJoinSubSelect('left', 'SELECT * FROM t2', 'a2', 't2.c1 = a2.c1');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->query->lateralJoinSubSelect('left', 'SELECT * FROM t3', 'a2', 't3.c1 = a2.c1');
    }
}
