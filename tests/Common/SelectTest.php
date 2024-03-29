<?php
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQueryTest;

class SelectTest extends AbstractQueryTest
{
    protected $query_type = 'select';

    public function testExceptionWithNoCols()
    {
        $this->query->from('t1');
        $this->expectException('Aura\SqlQuery\Exception');
        $this->query->__toString();

    }

    public function testSetAndGetPaging()
    {
        $expect = 88;
        $this->query->setPaging($expect);
        $actual = $this->query->getPaging();
        $this->assertSame($expect, $actual);
    }

    public function testDistinct()
    {
        $this->query->distinct()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = '
            SELECT DISTINCT
                <<t1>>.<<c1>>,
                <<t1>>.<<c2>>,
                <<t1>>.<<c3>>
            FROM
                <<t1>>
        ';
        $this->assertSameSql($expect, $actual);
        $this->assertTrue($this->query->isDistinct());
    }

    public function testDuplicateFlag()
    {
        $this->query->distinct()
                    ->distinct()
                    ->from('t1')
                    ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = '
            SELECT DISTINCT
                <<t1>>.<<c1>>,
                <<t1>>.<<c2>>,
                <<t1>>.<<c3>>
            FROM
                <<t1>>
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testFlagUnset()
    {
        $this->query->distinct()
                    ->distinct(false)
                    ->from('t1')
                    ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = '
            SELECT
                <<t1>>.<<c1>>,
                <<t1>>.<<c2>>,
                <<t1>>.<<c3>>
            FROM
                <<t1>>
        ';
        $this->assertSameSql($expect, $actual);
        $this->assertFalse($this->query->isDistinct());
    }

    public function testCols()
    {
        $this->assertFalse($this->query->hasCols());

        $this->query->cols(array(
            't1.c1',
            'c2' => 'a2',
            'COUNT(t1.c3)'
        ));

        $this->assertTrue($this->query->hasCols());
        $this->assertTrue($this->query->hasCol('t1.c1'));
        $this->assertTrue($this->query->hasCol('c2'));
        $this->assertTrue($this->query->hasCol('a2'));
        $this->assertFalse($this->query->hasCol('no_such_column'));

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                <<t1>>.<<c1>>,
                c2 AS <<a2>>,
                COUNT(<<t1>>.<<c3>>)
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testFrom()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1')
                    ->from('t2');

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                *
            FROM
                <<t1>>,
                <<t2>>
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testFromRaw()
    {
        $this->query->cols(array('*'));
        $this->query->fromRaw('t1')
                    ->fromRaw('t2');

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                *
            FROM
                t1,
                t2
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testDuplicateFromTable()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'FROM t1' after 'FROM t1'"
        );
        $this->query->from('t1');
    }


    public function testDuplicateFromAlias()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'FROM t2 AS t1' after 'FROM t1'"
        );
        $this->query->from('t2 AS t1');
    }

    public function testFromSubSelect()
    {
        $sub = 'SELECT * FROM t2';
        $this->query->cols(array('*'))->fromSubSelect($sub, 'a2');
        $expect = '
            SELECT
                *
            FROM
                (
                    SELECT * FROM t2
                ) AS <<a2>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testDuplicateSubSelectTableRef()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'FROM (SELECT ...) AS t1' after 'FROM t1'"
        );

        $sub = 'SELECT * FROM t2';
        $this->query->fromSubSelect($sub, 't1');
    }

    public function testFromSubSelectObject()
    {
        $sub = $this->newQuery();
        $sub->cols(array('*'))
            ->from('t2')
            ->where('foo = :foo', ['foo' => 'bar']);

        $this->query->cols(array('*'))
            ->fromSubSelect($sub, 'a2')
            ->where('a2.baz = :baz', ['baz' => 'dib']);

        $expect = '
            SELECT
                *
            FROM
                (
                    SELECT
                        *
                    FROM
                        <<t2>>
                    WHERE
                        foo = :foo
                ) AS <<a2>>
            WHERE
                <<a2>>.<<baz>> = :baz
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoin()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');
        $this->query->join('left', 't2', 't1.id = t2.id');
        $this->query->join('inner', 't3 AS a3', 't2.id = a3.id');
        $this->query->from('t4');
        $this->query->join('natural', 't5');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN <<t2>> ON <<t1>>.<<id>> = <<t2>>.<<id>>
                    INNER JOIN <<t3>> AS <<a3>> ON <<t2>>.<<id>> = <<a3>>.<<id>>,
                <<t4>>
                    NATURAL JOIN <<t5>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoinBeforeFrom()
    {
        $this->query->cols(array('*'));
        $this->query->join('left', 't2', 't1.id = t2.id');
        $this->query->join('inner', 't3 AS a3', 't2.id = a3.id');
        $this->query->from('t1');
        $this->query->from('t4');
        $this->query->join('natural', 't5');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN <<t2>> ON <<t1>>.<<id>> = <<t2>>.<<id>>
                    INNER JOIN <<t3>> AS <<a3>> ON <<t2>>.<<id>> = <<a3>>.<<id>>,
                <<t4>>
                    NATURAL JOIN <<t5>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testDuplicateJoinRef()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'NATURAL JOIN t1' after 'FROM t1'"
        );
        $this->query->join('natural', 't1');
    }

    public function testJoinAndBind()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');
        $this->query->join(
            'left',
            't2',
            't1.id = t2.id AND t1.foo = :foo',
            ['foo' => 'bar']
        );

        $expect = '
            SELECT
                *
            FROM
                <<t1>>
            LEFT JOIN <<t2>> ON <<t1>>.<<id>> = <<t2>>.<<id>> AND <<t1>>.<<foo>> = :foo
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $expect = array('foo' => 'bar');
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testLeftAndInnerJoin()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');
        $this->query->leftJoin('t2', 't1.id = t2.id');
        $this->query->innerJoin('t3 AS a3', 't2.id = a3.id');
        $this->query->join('natural', 't4');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN <<t2>> ON <<t1>>.<<id>> = <<t2>>.<<id>>
                    INNER JOIN <<t3>> AS <<a3>> ON <<t2>>.<<id>> = <<a3>>.<<id>>
                    NATURAL JOIN <<t4>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testLeftAndInnerJoinWithBind()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');
        $this->query->leftJoin('t2', 't2.id = :t2_id', ['t2_id' => 'foo']);
        $this->query->innerJoin('t3 AS a3', 'a3.id = :a3_id', ['a3_id' => 'bar']);
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
            LEFT JOIN <<t2>> ON <<t2>>.<<id>> = :t2_id
            INNER JOIN <<t3>> AS <<a3>> ON <<a3>>.<<id>> = :a3_id
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $expect = array('t2_id' => 'foo', 'a3_id' => 'bar');
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testJoinSubSelect()
    {
        $sub1 = 'SELECT * FROM t2';
        $sub2 = 'SELECT * FROM t3';
        $this->query->cols(array('*'));
        $this->query->from('t1');
        $this->query->joinSubSelect('left', $sub1, 'a2', 't2.c1 = a3.c1');
        $this->query->joinSubSelect('natural', $sub2, 'a3');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN (
                        SELECT * FROM t2
                    ) AS <<a2>> ON <<t2>>.<<c1>> = <<a3>>.<<c1>>
                    NATURAL JOIN (
                        SELECT * FROM t3
                    ) AS <<a3>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoinSubSelectBeforeFrom()
    {
        $sub1 = 'SELECT * FROM t2';
        $sub2 = 'SELECT * FROM t3';
        $this->query->cols(array('*'));
        $this->query->joinSubSelect('left', $sub1, 'a2', 't2.c1 = a3.c1');
        $this->query->joinSubSelect('natural', $sub2, 'a3');
        $this->query->from('t1');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN (
                        SELECT * FROM t2
                    ) AS <<a2>> ON <<t2>>.<<c1>> = <<a3>>.<<c1>>
                    NATURAL JOIN (
                        SELECT * FROM t3
                    ) AS <<a3>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testDuplicateJoinSubSelectRef()
    {
        $this->query->cols(array('*'));
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'NATURAL JOIN (SELECT ...) AS t1' after 'FROM t1'"
        );

        $sub2 = 'SELECT * FROM t3';
        $this->query->joinSubSelect('natural', $sub2, 't1');
    }

    public function testJoinSubSelectObject()
    {
        $sub = $this->newQuery();
        $sub->cols(array('*'))->from('t2')->where('foo = :foo', ['foo' => 'bar']);

        $this->query->cols(array('*'));
        $this->query->from('t1');
        $this->query->joinSubSelect('left', $sub, 'a3', 't2.c1 = a3.c1');
        $this->query->where('baz = :baz', ['baz' => 'dib']);

        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    LEFT JOIN (
                        SELECT
                            *
                        FROM
                            <<t2>>
                        WHERE
                            foo = :foo
                    ) AS <<a3>> ON <<t2>>.<<c1>> = <<a3>>.<<c1>>
            WHERE
                baz = :baz
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoinOrder()
    {
        $this->query->cols(array('*'));
        $this->query
            ->from('t1')
            ->join('inner', 't2', 't2.id = t1.id')
            ->join('left', 't3', 't3.id = t2.id')
            ->from('t4')
            ->join('inner', 't5', 't5.id = t4.id');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    INNER JOIN <<t2>> ON <<t2>>.<<id>> = <<t1>>.<<id>>
                    LEFT JOIN <<t3>> ON <<t3>>.<<id>> = <<t2>>.<<id>>,
                        <<t4>>
                    INNER JOIN <<t5>> ON <<t5>>.<<id>> = <<t4>>.<<id>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoinOnAndUsing()
    {
        $this->query->cols(array('*'));
        $this->query
            ->from('t1')
            ->join('inner', 't2', 'ON t2.id = t1.id')
            ->join('left', 't3', 'USING (id)');
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    INNER JOIN <<t2>> ON <<t2>>.<<id>> = <<t1>>.<<id>>
                    LEFT JOIN <<t3>> USING (id)
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testWhere()
    {
        $this->query->cols(array('*'));
        $this->query->where('c1 = c2')
                     ->where('c3 = :c3', ['c3' => 'foo']);
        $expect = '
            SELECT
                *
            WHERE
                c1 = c2
                AND c3 = :c3
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = ['c3' => 'foo'];
        $this->assertSame($expect, $actual);
    }

    public function testWhereWithInlineArray()
    {
        $this->query->cols(array('*'));
        $this->query->where('c1 = c2')
            ->where('c3 = :c3 AND c4 IN (:c4) AND c5 = :c5', ['c3' => 'foo', 'c4' => [3, 2, 1], 'c5' => 'bar'])
            ->where('c6 = ? AND c7 IN (?)', ['foo1', [6, 5, 4]]);
        $expect = '
            SELECT
                *
            WHERE
                c1 = c2
                AND c3 = :c3 AND c4 IN (:__1__, :__2__, :__3__) AND c5 = :c5
                AND c6 = ? AND c7 IN (:__4__, :__5__, :__6__)
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'c3' => 'foo',
            '__1__' => 3,
            '__2__' => 2,
            '__3__' => 1,
            'c5' => 'bar',
            0 => 'foo1',
            '__4__' => 6,
            '__5__' => 5,
            '__6__' => 4
        ];
        $this->assertSame($expect, $actual);
    }

    public function testOrWhere()
    {
        $this->query->cols(array('*'));
        $this->query->orWhere('c1 = c2')
                     ->orWhere('c3 = :c3', ['c3' => 'foo']);

        $expect = '
            SELECT
                *
            WHERE
                c1 = c2
                OR c3 = :c3
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = ['c3' => 'foo'];
        $this->assertSame($expect, $actual);
    }

    public function testGroupBy()
    {
        $this->query->cols(array('*'));
        $this->query->groupBy(array('c1', 't2.c2'));
        $expect = '
            SELECT
                *
            GROUP BY
                c1,
                <<t2>>.<<c2>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testHaving()
    {
        $this->query->cols(array('*'));
        $this->query->having('c1 = c2')
                     ->having('c3 = :c3', ['c3' => 'foo']);
        $expect = '
            SELECT
                *
            HAVING
                c1 = c2
                AND c3 = :c3
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = ['c3' => 'foo'];
        $this->assertSame($expect, $actual);
    }

    public function testOrHaving()
    {
        $this->query->cols(array('*'));
        $this->query->orHaving('c1 = c2')
                     ->orHaving('c3 = :c3', ['c3' => 'foo']);
        $expect = '
            SELECT
                *
            HAVING
                c1 = c2
                OR c3 = :c3
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = ['c3' => 'foo'];
        $this->assertSame($expect, $actual);
    }

    public function testOrderBy()
    {
        $this->query->cols(array('*'));
        $this->query->orderBy(array('c1', 'UPPER(t2.c2)', ));
        $expect = '
            SELECT
                *
            ORDER BY
                c1,
                UPPER(<<t2>>.<<c2>>)
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testGetterOnLimitAndOffset()
    {
        $this->query->cols(array('*'));
        $this->query->limit(10);
        $this->query->offset(50);

        $this->assertSame(10, $this->query->getLimit());
        $this->assertSame(50, $this->query->getOffset());
    }

    public function testLimitOffset()
    {
        $this->query->cols(array('*'));
        $this->query->limit(10);
        $expect = '
            SELECT
                *
            LIMIT 10
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $this->query->offset(40);
        $expect = '
            SELECT
                *
            LIMIT 10 OFFSET 40
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testPage()
    {
        $this->query->cols(array('*'));
        $this->query->page(5);
        $expect = '
            SELECT
                *
            LIMIT 10 OFFSET 40
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testForUpdate()
    {
        $this->query->cols(array('*'));
        $this->query->forUpdate();
        $expect = '
            SELECT
                *
            FOR UPDATE
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testUnion()
    {
        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->union()
                     ->cols(array('c2'))
                     ->from('t2');
        $expect = '
            SELECT
                c1
            FROM
                <<t1>>
            UNION
            SELECT
                c2
            FROM
                <<t2>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testUnionAll()
    {
        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->unionAll()
                     ->cols(array('c2'))
                     ->from('t2');
        $expect = '
            SELECT
                c1
            FROM
                <<t1>>
            UNION ALL
            SELECT
                c2
            FROM
                <<t2>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testAutobind()
    {
        // do these out of order
        $this->query->having('baz IN (?, ?, ?)', ['dib', 'zim', 'gir']);
        $this->query->where('foo = :foo', ['foo' => 'bar']);
        $this->query->cols(array('*'));

        $expect = '
            SELECT
                *
            WHERE
                foo = :foo
            HAVING
                baz IN (?, ?, ?)
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $expect = array(
            0 => 'dib',
            1 => 'zim',
            2 => 'gir',
            'foo' => 'bar',
        );
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testAddColWithAlias()
    {
        $this->query->cols(array(
            'foo',
            'bar',
            'table.noalias',
            'col1 as alias1',
            'col2 alias2',
            'table.proper' => 'alias_proper',
            'legacy invalid as alias still works',
            'overwrite as alias1',
        ));

        // add separately to make sure we don't overwrite sequential keys
        $this->query->cols(array(
            'baz',
            'dib',
        ));

        $actual = $this->query->__toString();

        $expect = '
            SELECT
                foo,
                bar,
                <<table>>.<<noalias>>,
                overwrite AS <<alias1>>,
                col2 AS <<alias2>>,
                <<table>>.<<proper>> AS <<alias_proper>>,
                legacy invalid AS <<alias still works>>,
                baz,
                dib
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testGetCols()
    {
        $this->query->cols(array('valueBar' => 'aliasFoo'));

        $cols = $this->query->getCols();

        $this->assertTrue(is_array($cols));
        $this->assertTrue(count($cols) === 1);
        $this->assertArrayHasKey('aliasFoo', $cols);
    }

    public function testRemoveColsAlias()
    {
        $this->query->cols(array('valueBar' => 'aliasFoo', 'valueBaz' => 'aliasBaz'));

        $this->assertTrue($this->query->removeCol('aliasFoo'));
        $cols = $this->query->getCols();

        $this->assertTrue(is_array($cols));
        $this->assertTrue(count($cols) === 1);
        $this->assertArrayNotHasKey('aliasFoo', $cols);
    }

    public function testRemoveColsName()
    {
        $this->query->cols(array('valueBar', 'valueBaz' => 'aliasBaz'));

        $this->assertTrue($this->query->removeCol('valueBar'));
        $cols = $this->query->getCols();

        $this->assertTrue(is_array($cols));
        $this->assertTrue(count($cols) === 1);
        $this->assertNotContains('valueBar', $cols);
    }

    public function testRemoveColsNotFound()
    {
        $this->assertFalse($this->query->removeCol('valueBar'));
    }

    public function testIssue47()
    {
        // sub select
        $sub = $this->newQuery()
            ->cols(array('*'))
            ->from('table1 AS t1');
        $expect = '
            SELECT
                *
            FROM
                <<table1>> AS <<t1>>
        ';
        $actual = $sub->__toString();
        $this->assertSameSql($expect, $actual);

        // main select
        $select = $this->newQuery()
            ->cols(array('*'))
            ->from('table2 AS t2')
            ->where("field IN (:field)", ['field' => $sub]);

        $expect = '
            SELECT
                *
            FROM
                <<table2>> AS <<t2>>
            WHERE
                field IN (SELECT
                *
            FROM
                <<table1>> AS <<t1>>)
        ';
        $actual = $select->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testIssue49()
    {
        $this->assertSame(0, $this->query->getPage());
        $this->assertSame(10, $this->query->getPaging());
        $this->assertSame(0, $this->query->getLimit());
        $this->assertSame(0, $this->query->getOffset());

        $this->query->page(3);
        $this->assertSame(3, $this->query->getPage());
        $this->assertSame(10, $this->query->getPaging());
        $this->assertSame(10, $this->query->getLimit());
        $this->assertSame(20, $this->query->getOffset());

        $this->query->limit(10);
        $this->assertSame(0, $this->query->getPage());
        $this->assertSame(10, $this->query->getPaging());
        $this->assertSame(10, $this->query->getLimit());
        $this->assertSame(0, $this->query->getOffset());

        $this->query->page(3);
        $this->query->setPaging(50);
        $this->assertSame(3, $this->query->getPage());
        $this->assertSame(50, $this->query->getPaging());
        $this->assertSame(50, $this->query->getLimit());
        $this->assertSame(100, $this->query->getOffset());

        $this->query->offset(10);
        $this->assertSame(0, $this->query->getPage());
        $this->assertSame(50, $this->query->getPaging());
        $this->assertSame(0, $this->query->getLimit());
        $this->assertSame(10, $this->query->getOffset());
    }

    public function testWhereSubSelectImportsBoundValues()
    {
        // sub select
        $sub = $this->newQuery()
            ->cols(array('*'))
            ->from('table1 AS t1')
            ->where('t1.foo = :foo', ['foo' => 'bar']);

        $expect = '
            SELECT
                *
            FROM
                <<table1>> AS <<t1>>
            WHERE
                <<t1>>.<<foo>> = :foo
        ';
        $actual = $sub->getStatement();
        $this->assertSameSql($expect, $actual);

        // main select
        $select = $this->newQuery()
            ->cols(array('*'))
            ->from('table2 AS t2')
            ->where("field IN (:field)", ['field' => $sub])
            ->where("t2.baz = :baz", ['baz' => 'dib']);

        $expect = '
            SELECT
                *
            FROM
                <<table2>> AS <<t2>>
            WHERE
                field IN (SELECT
                        *
                    FROM
                        <<table1>> AS <<t1>>
                    WHERE
                        <<t1>>.<<foo>> = :foo)
            AND <<t2>>.<<baz>> = :baz
        ';

        // B.b.: The _2_2_ means "2nd query, 2nd sequential bound value". It's
        // the 2nd bound value because the 1st one is imported fromt the 1st
        // query (the subselect).

        $actual = $select->getStatement();
        $this->assertSameSql($expect, $actual);

        $expect = array(
            'foo' => 'bar',
            'baz' => 'dib',
        );
        $actual = $select->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionSelectCanHaveSameAliasesInDifferentSelects()
    {
        $select = $this->query
            ->cols(array(
                '...'
            ))
            ->from('a')
            ->join('INNER', 'c', 'a_cid = c_id')
            ->union()
            ->cols(array(
                '...'
            ))
            ->from('b')
            ->join('INNER', 'c', 'b_cid = c_id');

        $expected = 'SELECT
                    ...
                    FROM
                    <<a>>
                    INNER JOIN <<c>> ON a_cid = c_id
                    UNION
                    SELECT
                    ...
                    FROM
                    <<b>>
                    INNER JOIN <<c>> ON b_cid = c_id';

        $actual = (string) $select->getStatement();
        $this->assertSameSql($expected, $actual);
    }

    public function testResetUnion()
    {
        $select = $this->query
            ->cols(array(
                '...'
            ))
            ->from('a')
            ->union()
            ->cols(array(
                '...'
            ))
            ->from('b');

        // should remove all prior queries and just leave the last.
        $select->resetUnions();
        $expected = 'SELECT
                    ...
                    FROM
                    <<b>>';

        $actual = (string) $select->getStatement();
        $this->assertSameSql($expected, $actual);
    }

    public function testWhereClosure()
    {
        $select = $this->query
            ->cols(['foo', 'bar'])
            ->from('baz')
            ->where(function ($select) {
                $select->where('foo > 1')
                    ->where('bar > 1');
            })->orWhere(function ($select) {
                $select->where('foo < 1')
                    ->where('bar < 1');
            })->where(function ($select) {
                // do nothing
            });

        $expect = '
            SELECT
                foo,
                bar
            FROM
                <<baz>>
            WHERE
                (
                    foo > 1
                    AND bar > 1
                )
                OR (
                    foo < 1
                    AND bar < 1
                )
            ';
        $actual = (string) $select->getStatement();
        $this->assertSameSql($expect, $actual);
    }

    public function testHavingClosure()
    {
        $select = $this->query
            ->cols(['foo', 'bar'])
            ->from('baz')
            ->having(function ($select) {
                $select->having('foo > 1')
                    ->having('bar > 1');
            })->orHaving(function ($select) {
                $select->having('foo < 1')
                    ->having('bar < 1');
            })->having(function ($select) {
                // do nothing
            });

        $expect = '
            SELECT
                foo,
                bar
            FROM
                <<baz>>
            HAVING
                (
                    foo > 1
                    AND bar > 1
                )
                OR (
                    foo < 1
                    AND bar < 1
                )
            ';
        $actual = (string) $select->getStatement();
        $this->assertSameSql($expect, $actual);
    }
}
