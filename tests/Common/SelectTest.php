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
                     ->cols(['t1.c1', 't1.c2', 't1.c3']);

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
                    ->cols(['t1.c1', 't1.c2', 't1.c3']);

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
                    ->cols(['t1.c1', 't1.c2', 't1.c3']);

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

        $this->query->cols([
            't1.c1',
            'c2' => 'a2',
            'COUNT(t1.c3)'
        ]);

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

    public function testColsWithFunctionExpression()
    {
        $this->query->cols([
            'id',
            "CONCAT(first_name, ' ', last_name) AS full_name",
        ]);

        $actual = $this->query->__toString();
        $expect = "
            SELECT
                id,
                CONCAT(first_name, ' ', last_name) AS <<full_name>>
        ";
        $this->assertSameSql($expect, $actual);
    }

    public function testFrom()
    {
        $this->query->cols(['*']);
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

    /**
     *
     * One string naming several tables is the same list as several from()
     * calls. It used to be read as "table alias" and quoted whole, giving
     * <<t1,>> <<t2>> -- an identifier no database has. See #160.
     *
     */
    public function testFromMultipleTablesInOneString()
    {
        $this->query->cols(['*']);
        $this->query->from('t1, t2');

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

    public function testFromMultipleTablesWithSpacesAndAliases()
    {
        $this->query->cols(['*']);
        $this->query->from('t1 AS a , t2 AS b');

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                *
            FROM
                <<t1>> AS <<a>>,
                <<t2>> AS <<b>>
        ';
        $this->assertSameSql($expect, $actual);
    }

    /**
     *
     * A comma inside a quoted identifier is part of the name, not a
     * separator, so it must not split the list. Quoted in this dialect's own
     * style, the name is already finished and passes through untouched.
     *
     */
    public function testFromDoesNotSplitAQuotedComma()
    {
        $name = $this->query->getQuoteNamePrefix()
              . 'odd,name'
              . $this->query->getQuoteNameSuffix();

        $this->query->cols(['*']);
        $this->query->from($name);

        $actual = $this->query->__toString();
        $expect = "
            SELECT
                *
            FROM
                {$name}
        ";
        $this->assertSameSql($expect, $actual);
    }

    /**
     *
     * A closing quote is escaped by doubling it -- `[odd]],name]` on SQL
     * Server, `"odd"",name"` on the others -- so the name here is `odd?,name`
     * and the comma is still inside it. Brackets are the case that bites: an
     * opener and closer that differ cannot close-then-reopen their way back
     * inside, the way a doubled symmetric quote accidentally does.
     *
     */
    public function testFromDoesNotSplitAnEscapedQuoteInsideAName()
    {
        $prefix = $this->query->getQuoteNamePrefix();
        $suffix = $this->query->getQuoteNameSuffix();
        $name = $prefix . 'odd' . $suffix . $suffix . ',name' . $suffix;

        $this->query->cols(['*']);
        $this->query->from($name);

        $actual = $this->query->__toString();
        $expect = "
            SELECT
                *
            FROM
                {$name}
        ";
        $this->assertSameSql($expect, $actual);
    }

    /**
     *
     * The duplicate-reference guard has to see each table in the list, or a
     * repeat slips through and the database reports it instead.
     *
     */
    public function testFromMultipleTablesRejectsADuplicate()
    {
        $this->query->cols(['*']);

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->query->from('t1, t1');
    }

    public function testFromRaw()
    {
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'FROM t1' after 'FROM t1'"
        );
        $this->query->from('t1');
    }

    public function testDuplicateFromAlias()
    {
        $this->query->cols(['*']);
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
        $this->query->cols(['*'])->fromSubSelect($sub, 'a2');
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
        $this->query->cols(['*']);
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
        $sub->cols(['*'])
            ->from('t2')
            ->where('foo = :foo', ['foo' => 'bar']);

        $this->query->cols(['*'])
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
        $this->query->from('t1');

        $this->expectException(
            'Aura\SqlQuery\Exception',
            "Cannot reference 'NATURAL JOIN t1' after 'FROM t1'"
        );
        $this->query->join('natural', 't1');
    }

    public function testJoinAndBind()
    {
        $this->query->cols(['*']);
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

        $expect = ['foo' => 'bar'];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testLeftAndInnerJoin()
    {
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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

        $expect = ['t2_id' => 'foo', 'a3_id' => 'bar'];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testJoinSubSelect()
    {
        $sub1 = 'SELECT * FROM t2';
        $sub2 = 'SELECT * FROM t3';
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $sub->cols(['*'])->from('t2')->where('foo = :foo', ['foo' => 'bar']);

        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
        $this->query->groupBy(['c1', 't2.c2']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
        $this->query->orderBy(['c1', 'UPPER(t2.c2)', ]);
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
        $this->query->cols(['*']);
        $this->query->limit(10);
        $this->query->offset(50);

        $this->assertSame(10, $this->query->getLimit());
        $this->assertSame(50, $this->query->getOffset());
    }

    public function testLimitOffset()
    {
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['*']);
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
        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union()
                     ->cols(['c2'])
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
        $this->query->cols(['c1'])
                     ->from('t1')
                     ->unionAll()
                     ->cols(['c2'])
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

    public function testUnionWithQuery()
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next);

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

    public function testUnionAllWithQuery()
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->unionAll($next);

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

    public function testUnionWithQueryChained()
    {
        $second = $this->newQuery()->cols(['c2'])->from('t2');
        $third = $this->newQuery()->cols(['c3'])->from('t3');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($second)
                     ->unionAll($third);

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
            UNION ALL
            SELECT
                c3
            FROM
                <<t3>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testUnionWithQueryThenBuildNextBranch()
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next)
                     ->union()
                     ->cols(['c3'])
                     ->from('t3');

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
            UNION
            SELECT
                c3
            FROM
                <<t3>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testUnionWithQueryTakesTheValuesItBound()
    {
        $next = $this->newQuery()
            ->cols(['c2'])
            ->from('t2')
            ->where('c2 = :baz', ['baz' => 'dib']);

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->where('c1 = :foo', ['foo' => 'bar'])
                     ->union($next);

        $expect = ['foo' => 'bar', 'baz' => 'dib'];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionWithQueryRendersItAsItWasPassed()
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])->from('t1')->union($next);

        // the branch was rendered when it was passed, so this does not
        // reach back into the union.
        $next->where('c2 = 1');

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

    public function testUnionWithQuerySharingAPlaceholder()
    {
        $next = $this->newQuery()
            ->cols(['c2'])
            ->from('t2')
            ->where('owner_id = :owner_id', ['owner_id' => 88]);

        // both branches filter on the one value, as in a union of two views
        // of the same owner
        $this->query->cols(['c1'])
                     ->from('t1')
                     ->where('owner_id = :owner_id', ['owner_id' => 88])
                     ->union($next);

        $expect = ['owner_id' => 88];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionWithQueryClaimingABoundName()
    {
        $next = $this->newQuery()
            ->cols(['c2'])
            ->from('t2')
            ->where('c2 = :foo', ['foo' => 'dib']);

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->where('c1 = :foo', ['foo' => 'bar']);

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':foo'");
        $this->query->union($next);
    }

    public function testUnionWithQueryThenMoreColumns()
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next)
                     ->cols(['c3']);

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('is the last branch');
        $this->query->__toString();
    }

    public static function provideStateAfterUnionTail()
    {
        return [
            'cols' => [function ($select) { $select->cols(['c3']); }],
            'from' => [function ($select) { $select->from('t3'); }],
            'fromRaw' => [function ($select) { $select->fromRaw('t3'); }],
            'join' => [function ($select) { $select->join('LEFT', 't3', 'c1 = c3'); }],
            'where' => [function ($select) { $select->where('c1 = 1'); }],
            'orWhere' => [function ($select) { $select->orWhere('c1 = 1'); }],
            'groupBy' => [function ($select) { $select->groupBy(['c1']); }],
            'having' => [function ($select) { $select->having('COUNT(c1) > 1'); }],
            'orHaving' => [function ($select) { $select->orHaving('COUNT(c1) > 1'); }],
            'orderBy' => [function ($select) { $select->orderBy(['c1']); }],
            'limit' => [function ($select) { $select->limit(10); }],
            'offset' => [function ($select) { $select->offset(10); }],
            'page' => [function ($select) { $select->page(2); }],
            'distinct' => [function ($select) { $select->distinct(); }],
            'forUpdate' => [function ($select) { $select->forUpdate(); }],
        ];
    }

    /**
     *
     * The supplied branch is the last one, and the SQL retained ends at it
     * with no UNION to carry on from -- so anything set on this query
     * afterwards has nowhere to render. Every part of the statement is the
     * same case as the columns: say so rather than drop it in silence.
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideStateAfterUnionTail')]
    public function testUnionWithQueryThenMoreState($add)
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next);

        $add($this->query);

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('is the last branch');
        $this->query->__toString();
    }

    /**
     *
     * Binding values against the branch already rendered is not a branch of
     * its own, and neither is reading the statement twice.
     *
     */
    /**
     *
     * A branch supplied whole holds its placeholders on the same terms as one
     * built here. Its names arrive with the query that bound them, and once a
     * further union() closes it the SQL joins the branches already retained
     * and is read for names like any other -- so a third branch asking for
     * :b with a value of its own is the collision it would be anywhere else.
     *
     */
    public function testUnionWithQueryThenAnotherBranchClaimingItsPlaceholder()
    {
        $next = $this->newQuery()
            ->cols(['c2'])
            ->from('t2')
            ->where('b = :b', ['b' => 2]);

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next)
                     ->union()
                     ->cols(['c3'])
                     ->from('t3');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':b'");
        $this->query->where('b = :b', ['b' => 999]);
    }

    public function testUnionWithQueryThenBindValues()
    {
        $next = $this->newQuery()
            ->cols(['c2'])
            ->from('t2')
            ->where('c2 = :c2', ['c2' => 'dib']);

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next);

        $this->query->bindValue('c2', 'zim');

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
            WHERE
                c2 = :c2
        ';

        $this->assertSameSql($expect, $this->query->__toString());
        $this->assertSameSql($expect, $this->query->__toString());
        $this->assertSame(['c2' => 'zim'], $this->query->getBindValues());
    }

    public function testUnionWithItself()
    {
        $this->query->cols(['c1'])->from('t1');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('Cannot union a query with itself');
        $this->query->union($this->query);
    }

    public function testUnionWithQueryThatCannotRender()
    {
        $next = $this->newQuery()->from('t2');

        $this->query->cols(['c1'])->from('t1');

        try {
            $this->query->union($next);
            $this->fail('Expected an exception for the empty branch.');
        } catch (\Aura\SqlQuery\Exception $e) {
            $this->assertStringContainsString('No columns', $e->getMessage());
        }

        // the branch this query was building was not consumed on the way out
        $expect = '
            SELECT
                c1
            FROM
                <<t1>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testResetUnionsAfterUnionWithQuery()
    {
        $next = $this->newQuery()->cols(['c2'])->from('t2');

        $this->query->cols(['c1'])
                     ->from('t1')
                     ->union($next)
                     ->resetUnions();

        // the supplied branch was union state, and went with the rest of it;
        // what is left is this query, which union() had reset.
        $this->query->cols(['c3'])->from('t3');

        $expect = '
            SELECT
                c3
            FROM
                <<t3>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testUnionWithQueryInSubSelect()
    {
        $next = $this->newQuery()
            ->cols(['amount'])
            ->from('t2')
            ->where('owner_id = :owner_id', ['owner_id' => 88]);

        $branch = $this->newQuery()
            ->cols(['amount'])
            ->from('t1')
            ->where('owner_id = :owner_id', ['owner_id' => 88]);

        $this->query
            ->cols(['SUM(amount) AS amount'])
            ->fromSubSelect($branch->union($next), 't');

        $expect = '
            SELECT
                SUM(amount) AS <<amount>>
            FROM
                (
                    SELECT
                        amount
                    FROM
                        <<t1>>
                    WHERE
                        owner_id = :owner_id
                    UNION
                    SELECT
                        amount
                    FROM
                        <<t2>>
                    WHERE
                        owner_id = :owner_id
                ) AS <<t>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $expect = ['owner_id' => 88];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testAutobind()
    {
        // do these out of order
        $this->query->having('baz IN (?, ?, ?)', ['dib', 'zim', 'gir']);
        $this->query->where('foo = :foo', ['foo' => 'bar']);
        $this->query->cols(['*']);

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

        $expect = [
            0 => 'dib',
            1 => 'zim',
            2 => 'gir',
            'foo' => 'bar',
        ];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testAddColWithAlias()
    {
        $this->query->cols([
            'foo',
            'bar',
            'table.noalias',
            'col1 as alias1',
            'col2 alias2',
            'table.proper' => 'alias_proper',
            'legacy invalid as alias still works',
            'overwrite as alias1',
        ]);

        // add separately to make sure we don't overwrite sequential keys
        $this->query->cols([
            'baz',
            'dib',
        ]);

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

    /**
     * issue #226: a space inside parentheses is not an alias separator.
     * 'COUNT(DISTINCT t1.c1)' is two space-separated tokens, but the first
     * is not a column name and the second is not an alias.
     */
    public function testAddColWithSpaceInsideParens()
    {
        $this->query->cols([
            'COUNT(DISTINCT t1.c1)',
            'COUNT(DISTINCT c2)',
            'COUNT(DISTINCT t1.c3) AS c3_count',
            // an implicit alias on a multi-word expression is passed through
            // as the caller wrote it: still valid SQL, just not quoted
            'COUNT(DISTINCT t1.c4) c4_count',
            'convert_tz(t1.open_from, t1.time_zone, @@session.time_zone) open_now',
        ]);

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                COUNT(DISTINCT <<t1>>.<<c1>>),
                COUNT(DISTINCT c2),
                COUNT(DISTINCT <<t1>>.<<c3>>) AS <<c3_count>>,
                COUNT(DISTINCT <<t1>>.<<c4>>) c4_count,
                convert_tz(<<t1>>.<<open_from>>, <<t1>>.<<time_zone>>, @@session.time_zone) open_now
        ';
        $this->assertSameSql($expect, $actual);
    }

    /**
     * A balanced call is still a column name, so the two-token alias form
     * keeps working for it.
     */
    public function testAddColWithAliasAfterBalancedParens()
    {
        $this->query->cols([
            'COUNT(*) tally',
            'COUNT(*) AS total',
            'MAX(t1.c1) hi',
        ]);

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                COUNT(*) AS <<tally>>,
                COUNT(*) AS <<total>>,
                MAX(<<t1>>.<<c1>>) AS <<hi>>
        ';
        $this->assertSameSql($expect, $actual);
    }

    /**
     * A parenthesis inside a string literal is data, not syntax, so it must
     * not make the expression look unbalanced: the alias is still an alias.
     */
    public function testAddColWithAliasAndParenInsideLiteral()
    {
        $this->query->cols([
            "CONCAT('(',t1.c1) opener",
            "CONCAT(t1.c2,')') closer",
            "CONCAT('(',t1.c3,')') AS wrapped",
            // a doubled quote is an escaped one, not the end of the literal
            "CONCAT('it''s(',t1.c4) escaped",
            // as is a backslashed one, on the dialects that escape that way.
            // the quoter's own literal scan does not follow backslash
            // escapes, so t1.c5 is left unquoted; the alias is still an alias
            "CONCAT('it\\'s(',t1.c5) backslashed",
        ]);

        $this->assertTrue($this->query->hasCol('opener'));
        $this->assertTrue($this->query->hasCol('closer'));
        $this->assertTrue($this->query->hasCol('wrapped'));
        $this->assertTrue($this->query->hasCol('escaped'));
        $this->assertTrue($this->query->hasCol('backslashed'));

        $actual = $this->query->__toString();
        $expect = "
            SELECT
                CONCAT('(',<<t1>>.<<c1>>) AS <<opener>>,
                CONCAT(<<t1>>.<<c2>>,')') AS <<closer>>,
                CONCAT('(',<<t1>>.<<c3>>,')') AS <<wrapped>>,
                CONCAT('it''s(',<<t1>>.<<c4>>) AS <<escaped>>,
                CONCAT('it\\'s(',t1.c5) AS <<backslashed>>
        ";
        $this->assertSameSql($expect, $actual);
    }

    /**
     * A closing parenthesis with nothing open before it is not a column
     * name, so the two-word form is not an alias either.
     */
    public function testAddColWithUnopenedParen()
    {
        $this->query->cols(['t1.c1) alias']);

        $this->assertFalse($this->query->hasCol('alias'));

        $actual = $this->query->__toString();
        $expect = '
            SELECT
                <<t1>>.<<c1>>) alias
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testGetCols()
    {
        $this->query->cols(['valueBar' => 'aliasFoo']);

        $cols = $this->query->getCols();

        $this->assertTrue(is_array($cols));
        $this->assertTrue(count($cols) === 1);
        $this->assertArrayHasKey('aliasFoo', $cols);
    }

    public function testRemoveColsAlias()
    {
        $this->query->cols(['valueBar' => 'aliasFoo', 'valueBaz' => 'aliasBaz']);

        $this->assertTrue($this->query->removeCol('aliasFoo'));
        $cols = $this->query->getCols();

        $this->assertTrue(is_array($cols));
        $this->assertTrue(count($cols) === 1);
        $this->assertArrayNotHasKey('aliasFoo', $cols);
    }

    public function testRemoveColsName()
    {
        $this->query->cols(['valueBar', 'valueBaz' => 'aliasBaz']);

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
            ->cols(['*'])
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
            ->cols(['*'])
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

    public function testIssue157WhereWithCast()
    {
        $this->query->cols(['street_number'])
            ->from('addresses')
            ->where('cast(street_number as varchar) like :sn', ['sn' => '10%']);

        $expect = '
            SELECT
                street_number
            FROM
                <<addresses>>
            WHERE
                cast(street_number as varchar) like :sn
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testWhereExistsSubSelect()
    {
        $sub = $this->newQuery()
            ->cols(['*'])
            ->from('orders')
            ->where('orders.user_id = users.id');

        $select = $this->newQuery()
            ->cols(['*'])
            ->from('users')
            ->where('EXISTS (:sub)', ['sub' => $sub]);

        $expect = '
            SELECT
                *
            FROM
                <<users>>
            WHERE
                EXISTS (SELECT
                *
            FROM
                <<orders>>
            WHERE
                <<orders>>.<<user_id>> = <<users>>.<<id>>)
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
            ->cols(['*'])
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
            ->cols(['*'])
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

        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $actual = $select->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionSelectCanHaveSameAliasesInDifferentSelects()
    {
        $select = $this->query
            ->cols([
                '...'
            ])
            ->from('a')
            ->join('INNER', 'c', 'a_cid = c_id')
            ->union()
            ->cols([
                '...'
            ])
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

    public function testUnionHoldsPlaceholdersFromEveryBranch()
    {
        // the third branch asks for a name the first one still spells, with a
        // value of its own: the first branch cannot be re-read, so this is the
        // collision a union of two branches already reports
        $select = $this->query
            ->cols(['c1'])
            ->from('t1')
            ->where('a = :a', ['a' => 1])
            ->union()
            ->cols(['c2'])
            ->from('t2')
            ->where('b = :b', ['b' => 2])
            ->union()
            ->cols(['c3'])
            ->from('t3');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':a'");
        $select->where('a = :a', ['a' => 999]);
    }

    /**
     *
     * Every name a branch could be reading, in the order it is written: the
     * ones the scan is meant to keep and the ones it is meant to pass over
     * alike, so that a case can say which it expects.
     *
     * @param string $cond The condition to look in.
     *
     * @return array
     *
     */
    protected function everyNameIn($cond)
    {
        preg_match_all('/:(\w+)/', $cond, $matches);
        return array_values(array_unique($matches[1]));
    }

    /**
     *
     * Asks which of the names a condition spells the union holds against a
     * later branch, by binding each one by hand and then having the next
     * branch bind it to a value of its own: a held name reports the
     * collision, a free one takes the new value.
     *
     * @param array $expect The names expected to be held, in the order they
     * appear in the condition.
     *
     * @param string $cond The condition to render into a union branch.
     *
     * @return void
     *
     */
    protected function assertNamesHeld(array $expect, $cond)
    {
        $held = [];

        foreach ($this->everyNameIn($cond) as $name) {
            $select = $this->newQuery()
                ->cols(['c1'])
                ->from('t1')
                ->where($cond, []);
            $select->bindValue($name, 'by hand');
            $select->union()->cols(['c2'])->from('t2');

            try {
                $select->where("z = :{$name}", [$name => 'of its own']);
            } catch (\Aura\SqlQuery\Exception\LogicException $e) {
                $held[] = $name;
            }
        }

        $this->assertSame($expect, $held, "condition: {$cond}");
    }

    public static function provideNamesHeldFromABranch()
    {
        return [
            'placeholder' => [['a'], 'a = :a'],
            'literal' => [[], "name = ':a'"],
            'literal either side' => [['a'], "x = 'one' AND a = :a AND y = 'two'"],
            'doubled quote inside' => [[], "note = 'it''s :a'"],
            'doubled quote before' => [['a'], "note = 'q''' AND a = :a"],
            'line comment' => [[], 'c1 > 0 -- :a'],
            'block comment' => [[], 'c1 > 0 /* :a */'],
            'comment then code' => [['a'], "c1 > 0 -- :b\nAND a = :a"],
            'apostrophe in comment' => [['a'], "c1 > 0 -- don't\nAND a = :a"],
            'cast type' => [['a'], "c1::text = :a"],
            'unclosed literal' => [['a'], "note = 'unclosed AND a = :a"],
            'doubled quote leaves it open' => [['a'], "note = ':a''"],
            'unclosed block comment' => [['a'], 'c1 > 0 /* unclosed :a'],

            // read no further than the dialects agree. A name kept here is a
            // needless collision report and nothing worse, which is why these
            // are left as they are rather than read into the pattern: every
            // reading added is a chance to swallow a name that is real.
            'block comment around a comment' => [[], 'c1 > 0 /* /* :a */ */'],
            'gap: nested block comment' => [['a'], 'c1 > 0 /* /* q */ :a */'],
        ];
    }

    /**
     *
     * What the scan reads and what it passes over, case by case. The names
     * held are the names the branch is taken to bind; the rest are free for
     * the next branch to bind as it likes.
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideNamesHeldFromABranch')]
    public function testNamesHeldFromABranch(array $expect, $cond)
    {
        $this->assertNamesHeld($expect, $cond);
    }

    /**
     *
     * A colon inside a quoted identifier is part of the name -- backticks on
     * MySQL, brackets on SQL Server, double quotes elsewhere -- and no
     * placeholder can stand there, so nothing inside one is read as a name.
     * The quoting the builder writes around every name it is given is the
     * same quoting read back here.
     *
     */
    public function testNamesHeldFromAQuotedIdentifier()
    {
        $prefix = $this->query->getQuoteNamePrefix();
        $suffix = $this->query->getQuoteNameSuffix();

        $this->assertNamesHeld([], "x = {$prefix}odd:a name{$suffix}");

        // a closing quote inside the name is written by doubling it, so the
        // name runs on rather than ending there
        $this->assertNamesHeld(
            [],
            "x = {$prefix}odd{$suffix}{$suffix}:a name{$suffix}"
        );

        // and the placeholder standing outside the name is still read
        $this->assertNamesHeld(
            ['b'],
            "x = {$prefix}odd:a name{$suffix} AND b = :b"
        );

        // an opener that never closes leaves the rest as SQL, as an unclosed
        // literal does -- including when a doubled quote is what leaves it
        // open, the name running on past the pair rather than ending at it
        $this->assertNamesHeld(['a'], "x = {$prefix}unclosed AND a = :a");
        $this->assertNamesHeld(['a'], "x = {$prefix}:a{$suffix}{$suffix}");
    }

    public function testUnionHoldsAPlaceholderFromAMiddleBranch()
    {
        // the branch in the middle is neither the newest nor the first, and
        // its name is held on the same terms as either
        $select = $this->query
            ->cols(['c1'])
            ->from('t1')
            ->where('a = :a', ['a' => 1])
            ->union()
            ->cols(['c2'])
            ->from('t2')
            ->where('b = :b', ['b' => 2])
            ->union()
            ->cols(['c3'])
            ->from('t3')
            ->where('c = :c', ['c' => 3])
            ->union()
            ->cols(['c4'])
            ->from('t4');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':b'");
        $select->where('b = :b', ['b' => 999]);
    }

    public function testUnionSharesAMiddleBranchPlaceholderOnTheSameValue()
    {
        // held is not taken: a later branch asking for the value the middle
        // branch already binds is the shared filter a union is often written
        // for
        $select = $this->query
            ->cols(['c1'])
            ->from('t1')
            ->where('a = :a', ['a' => 1])
            ->union()
            ->cols(['c2'])
            ->from('t2')
            ->where('b = :b', ['b' => 2])
            ->union()
            ->cols(['c3'])
            ->from('t3')
            ->where('b = :b', ['b' => 2]);

        $expect = ['a' => 1, 'b' => 2];
        $actual = $select->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionKeepsTheValuesEveryBranchBound()
    {
        $select = $this->query
            ->cols(['c1'])
            ->from('t1')
            ->where('a = :a', ['a' => 1])
            ->union()
            ->cols(['c2'])
            ->from('t2')
            ->where('b = :b', ['b' => 2])
            ->union()
            ->cols(['c3'])
            ->from('t3')
            ->where('c = :c', ['c' => 3]);

        $expect = ['a' => 1, 'b' => 2, 'c' => 3];
        $actual = $select->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionReadsEachBranchOnItsOwnForUnclosedQuotes()
    {
        // read end to end, the stray quote in the first branch would pair
        // with the one in the second and mask the placeholder between them
        $select = $this->query
            ->cols(['c1'])
            ->from('t1')
            ->where("note = 'unclosed", [])
            ->where('a = :a', ['a' => 1])
            ->union()
            ->cols(['c2'])
            ->from('t2')
            ->where("note = 'also unclosed", [])
            ->union()
            ->cols(['c3'])
            ->from('t3');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':a'");
        $select->where('a = :a', ['a' => 999]);
    }

    public function testResetUnion()
    {
        $select = $this->query
            ->cols([
                '...'
            ])
            ->from('a')
            ->union()
            ->cols([
                '...'
            ])
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

    public function testIssue183WhereWithParentheses()
    {
        // two OR-groups combined with AND, e.g. (a OR b) AND (c OR d)
        $select = $this->query
            ->cols(['*'])
            ->from('tests')
            ->where(function ($select) {
                $select->where('compound_group IN (:groups)')
                    ->orWhere('compound_name IN (:names)');
            })
            ->where(function ($select) {
                $select->where('species_genus IN (:genera)')
                    ->orWhere('species_name IN (:species_names)');
            });

        $expect = '
            SELECT
                *
            FROM
                <<tests>>
            WHERE
                (
                    compound_group IN (:groups)
                    OR compound_name IN (:names)
                )
                AND (
                    species_genus IN (:genera)
                    OR species_name IN (:species_names)
                )
            ';
        $actual = (string) $select->getStatement();
        $this->assertSameSql($expect, $actual);
    }

    public function testIssue183ReportedQuery()
    {
        // the query as reported on issue #183, joining two tables and
        // filtering on column names that contain a dot ('compound.group'),
        // which the caller has to quote by hand
        $q1 = $this->query->getQuoteNamePrefix();
        $q2 = $this->query->getQuoteNameSuffix();

        $select = $this->query
            ->cols(['tests.id'])
            ->from('tests')
            ->innerJoin('isolates', 'isolates.id = tests.isolate_id')
            ->where(function ($select) use ($q1, $q2) {
                $select
                    ->where("find_in_set(tests.{$q1}compound.group{$q2}, :compound_groups)")
                    ->orWhere("find_in_set(tests.{$q1}compound.name{$q2}, :compound_names)");
            })
            ->where(function ($select) use ($q1, $q2) {
                $select
                    ->where("find_in_set(isolates.{$q1}species.genus{$q2}, :species_genera)")
                    ->orWhere("find_in_set(isolates.{$q1}species.name{$q2}, :species_names)");
            });

        // the hand-quoted names survive as written; as with a string
        // literal, the table prefix beside them is left alone rather than
        // quoted
        $expect = '
            SELECT
                <<tests>>.<<id>>
            FROM
                <<tests>>
            INNER JOIN <<isolates>> ON <<isolates>>.<<id>> = <<tests>>.<<isolate_id>>
            WHERE
                (
                    find_in_set(tests.<<compound.group>>, :compound_groups)
                    OR find_in_set(tests.<<compound.name>>, :compound_names)
                )
                AND (
                    find_in_set(isolates.<<species.genus>>, :species_genera)
                    OR find_in_set(isolates.<<species.name>>, :species_names)
                )
            ';
        $actual = (string) $select->getStatement();
        $this->assertSameSql($expect, $actual);
    }

    public function testIssue226SessionVariableInCols()
    {
        // the query as reported on issue #226: the column reference is
        // quoted, the session variable beside it is not
        $select = $this->query
            ->from('customer')
            ->cols(['convert_tz(open_from, customer.time_zone, @@session.time_zone) open_now'])
            ->where('customer.id = :id');

        $expect = '
            SELECT
                convert_tz(open_from, <<customer>>.<<time_zone>>, @@session.time_zone) open_now
            FROM
                <<customer>>
            WHERE
                <<customer>>.<<id>> = :id
            ';
        $actual = (string) $select->getStatement();
        $this->assertSameSql($expect, $actual);
    }

    public function testIssue226VariableInEveryClause()
    {
        // GROUP BY, HAVING and ORDER BY quote their text through separate
        // call sites from cols() and where(); a variable survives all of them
        $select = $this->query
            ->cols(['t.a'])
            ->from('t')
            ->where('t.a = @v.x')
            ->groupBy(['@v.x', 't.a'])
            ->having('COUNT(*) > @v.n')
            ->orderBy(['@v.x DESC', 't.a']);

        $expect = '
            SELECT
                <<t>>.<<a>>
            FROM
                <<t>>
            WHERE
                <<t>>.<<a>> = @v.x
            GROUP BY
                @v.x,
                <<t>>.<<a>>
            HAVING
                COUNT(*) > @v.n
            ORDER BY
                @v.x DESC,
                <<t>>.<<a>>
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

    protected function withRecursiveKeyword()
    {
        return 'WITH RECURSIVE';
    }

    public function testWith()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->with('cte', $sub)->cols(['*'])->from('cte');
        $expect = '
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithCols()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->with('cte', $sub, ['col1'])->cols(['*'])->from('cte');
        $expect = '
            WITH <<cte>> (<<col1>>) AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithMultiple()
    {
        $sub1 = $this->newQuery()->cols(['c1'])->from('t1');
        $sub2 = $this->newQuery()->cols(['c2'])->from('t2');
        $this->query
            ->with('cte1', $sub1)
            ->with('cte2', $sub2)
            ->cols(['*'])
            ->from('cte1')
            ->join('INNER', 'cte2', 'cte1.c1 = cte2.c2');
        $expect = '
            WITH <<cte1>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            ),
            <<cte2>> AS (
                SELECT
                    c2
                FROM
                    <<t2>>
            )
            SELECT
                *
            FROM
                <<cte1>>
                INNER JOIN <<cte2>> ON <<cte1>>.<<c1>> = <<cte2>>.<<c2>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithRecursive()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->withRecursive('cte', $sub)->cols(['*'])->from('cte');

        $keyword = $this->withRecursiveKeyword();
        $expect = '
            ' . $keyword . ' <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithRecursiveAndPlainMixed()
    {
        $sub1 = $this->newQuery()->cols(['c1'])->from('t1');
        $sub2 = $this->newQuery()->cols(['c2'])->from('t2');
        $this->query
            ->with('cte1', $sub1)
            ->withRecursive('cte2', $sub2)
            ->cols(['*'])
            ->from('cte1');

        $keyword = $this->withRecursiveKeyword();
        $expect = '
            ' . $keyword . ' <<cte1>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            ),
            <<cte2>> AS (
                SELECT
                    c2
                FROM
                    <<t2>>
            )
            SELECT
                *
            FROM
                <<cte1>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithReferencingEarlierCte()
    {
        $sub1 = $this->newQuery()->cols(['c1'])->from('t1');
        $sub2 = $this->newQuery()->cols(['c2'])->from('cte1');
        $this->query
            ->with('cte1', $sub1)
            ->with('cte2', $sub2)
            ->cols(['*'])
            ->from('cte2');
        $expect = '
            WITH <<cte1>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            ),
            <<cte2>> AS (
                SELECT
                    c2
                FROM
                    <<cte1>>
            )
            SELECT
                *
            FROM
                <<cte2>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithRawStringSpec()
    {
        $this->query->with('cte', 'SELECT c1 FROM t1')->cols(['*'])->from('cte');
        $expect = '
            WITH <<cte>> AS (
                SELECT c1 FROM t1
            )
            SELECT
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithIdempotent()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->with('cte', $sub)->cols(['*'])->from('cte');
        $stmt1 = $this->query->getStatement();
        $stmt2 = $this->query->getStatement();
        $this->assertSame($stmt1, $stmt2);
    }

    public function testWithSubSelectHasUnion()
    {
        $sub1 = $this->newQuery()->cols(['c1'])->from('t1');
        $sub2 = $this->newQuery()->cols(['c2'])->from('t2');
        $sub1->union($sub2);

        $this->query->with('cte', $sub1)->cols(['*'])->from('cte');

        $expect = '
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
                UNION
                SELECT
                    c2
                FROM
                    <<t2>>
            )
            SELECT
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithEmptyName()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->expectException('Aura\SqlQuery\Exception\InvalidArgumentException');
        $this->query->with('', $sub);
    }

    public function testWithDuplicateName()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->with('cte', $sub);
        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->query->with('cte', $sub);
    }

    public function testWithSelfCte()
    {
        // the query has columns, so it would render if the guard were gone;
        // without them it throws 'No columns in the SELECT' instead, and the
        // test passes whether the guard is there or not.
        $this->query->cols(['c1'])->from('t1');

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('Cannot use a query as a CTE of itself');
        $this->query->with('cte', $this->query);
    }

    /**
     *
     * A CTE is statement-level, so it survives the reset union() performs to
     * open the next branch, and is written once above the whole union.
     *
     */
    public function testWithThenUnion()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query
            ->with('cte', $sub)
            ->cols(['*'])
            ->from('cte')
            ->union()
            ->cols(['*'])
            ->from('t2');
        $expect = '
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT
                *
            FROM
                <<cte>>
            UNION
            SELECT
                *
            FROM
                <<t2>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    /**
     *
     * A CTE added after a branch was supplied whole is not a further branch:
     * it is the clause the whole union sits under, and it has somewhere to
     * render. See assertNoBranchAfterUnionTail().
     *
     */
    public function testWithAfterSuppliedUnionTail()
    {
        $branch = $this->newQuery()->cols(['c2'])->from('t2');
        $sub = $this->newQuery()->cols(['c1'])->from('t1');

        $this->query->cols(['*'])->from('cte')->union($branch);
        $this->query->with('cte', $sub);

        $expect = '
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT
                *
            FROM
                <<cte>>
            UNION
            SELECT
                c2
            FROM
                <<t2>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testResetWith()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->with('cte', $sub)->cols(['*'])->from('cte');
        $this->query->resetWith();

        $expect = '
            SELECT
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    /**
     *
     * resetWith() clears the recursive flag along with the CTEs, so the next
     * clause built on the query is not silently made recursive -- which SQL
     * Server rejects outright and the rest read as a different statement.
     *
     */
    public function testResetWithClearsTheRecursiveFlag()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->withRecursive('cte', $sub);
        $this->query->resetWith();

        $this->query->with('plain', $sub)->cols(['*'])->from('plain');
        $expect = '
            WITH <<plain>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT
                *
            FROM
                <<plain>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    /**
     *
     * A WITH clause opens a statement, and a union branch is not one: the
     * branch would render as `UNION WITH ... SELECT`, which no dialect reads.
     *
     */
    public function testUnionBranchCannotBringItsOwnWith()
    {
        $cte = $this->newQuery()->cols(['c1'])->from('t1');
        $branch = $this->newQuery()->with('bcte', $cte)->cols(['*'])->from('bcte');

        $this->query->cols(['*'])->from('a');

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('defines its own WITH clause');
        $this->query->union($branch);
    }

    /**
     *
     * A CTE whose values collide with a clause of this query leaves nothing
     * behind: the names it bound before the collision are released with it,
     * rather than being held for a CTE the query does not have.
     *
     */
    public function testWithLeavesNoBindsBehindWhenItThrows()
    {
        $sub = $this->newQuery()
            ->cols(['c1'])
            ->from('t1')
            ->where('a = :a AND b = :b', ['a' => 1, 'b' => 2]);

        $this->query->cols(['*'])->from('t2')->where('b = :b', ['b' => 99]);

        try {
            $this->query->with('cte', $sub);
            $this->fail('Expected the colliding placeholder to be reported.');
        } catch (\Aura\SqlQuery\Exception\LogicException $e) {
            // the CTE's own :a is not left bound to a clause that never took
            $this->assertSame(['b' => 99], $this->query->getBindValues());
        }

        // and :a is free for whatever wants it next
        $this->query->having('a = :a', ['a' => 42]);
        $this->assertSame(['b' => 99, 'a' => 42], $this->query->getBindValues());
    }
}
