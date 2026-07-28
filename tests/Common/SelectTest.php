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

    public function testColsWithFunctionExpression()
    {
        $this->query->cols(array(
            'id',
            "CONCAT(first_name, ' ', last_name) AS full_name",
        ));

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

    /**
     *
     * One string naming several tables is the same list as several from()
     * calls. It used to be read as "table alias" and quoted whole, giving
     * <<t1,>> <<t2>> -- an identifier no database has. See #160.
     *
     */
    public function testFromMultipleTablesInOneString()
    {
        $this->query->cols(array('*'));
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
        $this->query->cols(array('*'));
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

        $this->query->cols(array('*'));
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

        $this->query->cols(array('*'));
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
        $this->query->cols(array('*'));

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->query->from('t1, t1');
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

    public function testUnionWithQuery()
    {
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))
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
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))
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
        $second = $this->newQuery()->cols(array('c2'))->from('t2');
        $third = $this->newQuery()->cols(array('c3'))->from('t3');

        $this->query->cols(array('c1'))
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
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->union($next)
                     ->union()
                     ->cols(array('c3'))
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
            ->cols(array('c2'))
            ->from('t2')
            ->where('c2 = :baz', array('baz' => 'dib'));

        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->where('c1 = :foo', array('foo' => 'bar'))
                     ->union($next);

        $expect = array('foo' => 'bar', 'baz' => 'dib');
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionWithQueryRendersItAsItWasPassed()
    {
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))->from('t1')->union($next);

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
            ->cols(array('c2'))
            ->from('t2')
            ->where('owner_id = :owner_id', array('owner_id' => 88));

        // both branches filter on the one value, as in a union of two views
        // of the same owner
        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->where('owner_id = :owner_id', array('owner_id' => 88))
                     ->union($next);

        $expect = array('owner_id' => 88);
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionWithQueryClaimingABoundName()
    {
        $next = $this->newQuery()
            ->cols(array('c2'))
            ->from('t2')
            ->where('c2 = :foo', array('foo' => 'dib'));

        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->where('c1 = :foo', array('foo' => 'bar'));

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':foo'");
        $this->query->union($next);
    }

    public function testUnionWithQueryThenMoreColumns()
    {
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->union($next)
                     ->cols(array('c3'));

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('is the last branch');
        $this->query->__toString();
    }

    public static function provideStateAfterUnionTail()
    {
        return array(
            'cols' => array(function ($select) { $select->cols(array('c3')); }),
            'from' => array(function ($select) { $select->from('t3'); }),
            'fromRaw' => array(function ($select) { $select->fromRaw('t3'); }),
            'join' => array(function ($select) { $select->join('LEFT', 't3', 'c1 = c3'); }),
            'where' => array(function ($select) { $select->where('c1 = 1'); }),
            'orWhere' => array(function ($select) { $select->orWhere('c1 = 1'); }),
            'groupBy' => array(function ($select) { $select->groupBy(array('c1')); }),
            'having' => array(function ($select) { $select->having('COUNT(c1) > 1'); }),
            'orHaving' => array(function ($select) { $select->orHaving('COUNT(c1) > 1'); }),
            'orderBy' => array(function ($select) { $select->orderBy(array('c1')); }),
            'limit' => array(function ($select) { $select->limit(10); }),
            'offset' => array(function ($select) { $select->offset(10); }),
            'page' => array(function ($select) { $select->page(2); }),
            'distinct' => array(function ($select) { $select->distinct(); }),
            'forUpdate' => array(function ($select) { $select->forUpdate(); }),
        );
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
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))
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
            ->cols(array('c2'))
            ->from('t2')
            ->where('b = :b', array('b' => 2));

        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->union($next)
                     ->union()
                     ->cols(array('c3'))
                     ->from('t3');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':b'");
        $this->query->where('b = :b', array('b' => 999));
    }

    public function testUnionWithQueryThenBindValues()
    {
        $next = $this->newQuery()
            ->cols(array('c2'))
            ->from('t2')
            ->where('c2 = :c2', array('c2' => 'dib'));

        $this->query->cols(array('c1'))
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
        $this->assertSame(array('c2' => 'zim'), $this->query->getBindValues());
    }

    public function testUnionWithItself()
    {
        $this->query->cols(array('c1'))->from('t1');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('Cannot union a query with itself');
        $this->query->union($this->query);
    }

    public function testUnionWithQueryThatCannotRender()
    {
        $next = $this->newQuery()->from('t2');

        $this->query->cols(array('c1'))->from('t1');

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
        $next = $this->newQuery()->cols(array('c2'))->from('t2');

        $this->query->cols(array('c1'))
                     ->from('t1')
                     ->union($next)
                     ->resetUnions();

        // the supplied branch was union state, and went with the rest of it;
        // what is left is this query, which union() had reset.
        $this->query->cols(array('c3'))->from('t3');

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
            ->cols(array('amount'))
            ->from('t2')
            ->where('owner_id = :owner_id', array('owner_id' => 88));

        $branch = $this->newQuery()
            ->cols(array('amount'))
            ->from('t1')
            ->where('owner_id = :owner_id', array('owner_id' => 88));

        $this->query
            ->cols(array('SUM(amount) AS amount'))
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

        $expect = array('owner_id' => 88);
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
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

    /**
     * issue #226: a space inside parentheses is not an alias separator.
     * 'COUNT(DISTINCT t1.c1)' is two space-separated tokens, but the first
     * is not a column name and the second is not an alias.
     */
    public function testAddColWithSpaceInsideParens()
    {
        $this->query->cols(array(
            'COUNT(DISTINCT t1.c1)',
            'COUNT(DISTINCT c2)',
            'COUNT(DISTINCT t1.c3) AS c3_count',
            // an implicit alias on a multi-word expression is passed through
            // as the caller wrote it: still valid SQL, just not quoted
            'COUNT(DISTINCT t1.c4) c4_count',
            'convert_tz(t1.open_from, t1.time_zone, @@session.time_zone) open_now',
        ));

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
        $this->query->cols(array(
            'COUNT(*) tally',
            'COUNT(*) AS total',
            'MAX(t1.c1) hi',
        ));

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
        $this->query->cols(array(
            "CONCAT('(',t1.c1) opener",
            "CONCAT(t1.c2,')') closer",
            "CONCAT('(',t1.c3,')') AS wrapped",
            // a doubled quote is an escaped one, not the end of the literal
            "CONCAT('it''s(',t1.c4) escaped",
            // as is a backslashed one, on the dialects that escape that way.
            // the quoter's own literal scan does not follow backslash
            // escapes, so t1.c5 is left unquoted; the alias is still an alias
            "CONCAT('it\\'s(',t1.c5) backslashed",
        ));

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
        $this->query->cols(array('t1.c1) alias'));

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

    public function testIssue157WhereWithCast()
    {
        $this->query->cols(array('street_number'))
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
            ->cols(array('*'))
            ->from('orders')
            ->where('orders.user_id = users.id');

        $select = $this->newQuery()
            ->cols(array('*'))
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

    public function testUnionHoldsPlaceholdersFromEveryBranch()
    {
        // the third branch asks for a name the first one still spells, with a
        // value of its own: the first branch cannot be re-read, so this is the
        // collision a union of two branches already reports
        $select = $this->query
            ->cols(array('c1'))
            ->from('t1')
            ->where('a = :a', array('a' => 1))
            ->union()
            ->cols(array('c2'))
            ->from('t2')
            ->where('b = :b', array('b' => 2))
            ->union()
            ->cols(array('c3'))
            ->from('t3');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':a'");
        $select->where('a = :a', array('a' => 999));
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
        $held = array();

        foreach ($this->everyNameIn($cond) as $name) {
            $select = $this->newQuery()
                ->cols(array('c1'))
                ->from('t1')
                ->where($cond, array());
            $select->bindValue($name, 'by hand');
            $select->union()->cols(array('c2'))->from('t2');

            try {
                $select->where("z = :{$name}", array($name => 'of its own'));
            } catch (\Aura\SqlQuery\Exception\LogicException $e) {
                $held[] = $name;
            }
        }

        $this->assertSame($expect, $held, "condition: {$cond}");
    }

    public static function provideNamesHeldFromABranch()
    {
        return array(
            'placeholder' => array(array('a'), 'a = :a'),
            'literal' => array(array(), "name = ':a'"),
            'literal either side' => array(array('a'), "x = 'one' AND a = :a AND y = 'two'"),
            'doubled quote inside' => array(array(), "note = 'it''s :a'"),
            'doubled quote before' => array(array('a'), "note = 'q''' AND a = :a"),
            'line comment' => array(array(), 'c1 > 0 -- :a'),
            'block comment' => array(array(), 'c1 > 0 /* :a */'),
            'comment then code' => array(array('a'), "c1 > 0 -- :b\nAND a = :a"),
            'apostrophe in comment' => array(array('a'), "c1 > 0 -- don't\nAND a = :a"),
            'cast type' => array(array('a'), "c1::text = :a"),
            'unclosed literal' => array(array('a'), "note = 'unclosed AND a = :a"),
            'doubled quote leaves it open' => array(array('a'), "note = ':a''"),
            'unclosed block comment' => array(array('a'), 'c1 > 0 /* unclosed :a'),

            // read no further than the dialects agree. A name kept here is a
            // needless collision report and nothing worse, which is why these
            // are left as they are rather than read into the pattern: every
            // reading added is a chance to swallow a name that is real.
            'block comment around a comment' => array(array(), 'c1 > 0 /* /* :a */ */'),
            'gap: nested block comment' => array(array('a'), 'c1 > 0 /* /* q */ :a */'),
        );
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

        $this->assertNamesHeld(array(), "x = {$prefix}odd:a name{$suffix}");

        // a closing quote inside the name is written by doubling it, so the
        // name runs on rather than ending there
        $this->assertNamesHeld(
            array(),
            "x = {$prefix}odd{$suffix}{$suffix}:a name{$suffix}"
        );

        // and the placeholder standing outside the name is still read
        $this->assertNamesHeld(
            array('b'),
            "x = {$prefix}odd:a name{$suffix} AND b = :b"
        );

        // an opener that never closes leaves the rest as SQL, as an unclosed
        // literal does -- including when a doubled quote is what leaves it
        // open, the name running on past the pair rather than ending at it
        $this->assertNamesHeld(array('a'), "x = {$prefix}unclosed AND a = :a");
        $this->assertNamesHeld(array('a'), "x = {$prefix}:a{$suffix}{$suffix}");
    }

    public function testUnionHoldsAPlaceholderFromAMiddleBranch()
    {
        // the branch in the middle is neither the newest nor the first, and
        // its name is held on the same terms as either
        $select = $this->query
            ->cols(array('c1'))
            ->from('t1')
            ->where('a = :a', array('a' => 1))
            ->union()
            ->cols(array('c2'))
            ->from('t2')
            ->where('b = :b', array('b' => 2))
            ->union()
            ->cols(array('c3'))
            ->from('t3')
            ->where('c = :c', array('c' => 3))
            ->union()
            ->cols(array('c4'))
            ->from('t4');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':b'");
        $select->where('b = :b', array('b' => 999));
    }

    public function testUnionSharesAMiddleBranchPlaceholderOnTheSameValue()
    {
        // held is not taken: a later branch asking for the value the middle
        // branch already binds is the shared filter a union is often written
        // for
        $select = $this->query
            ->cols(array('c1'))
            ->from('t1')
            ->where('a = :a', array('a' => 1))
            ->union()
            ->cols(array('c2'))
            ->from('t2')
            ->where('b = :b', array('b' => 2))
            ->union()
            ->cols(array('c3'))
            ->from('t3')
            ->where('b = :b', array('b' => 2));

        $expect = array('a' => 1, 'b' => 2);
        $actual = $select->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionKeepsTheValuesEveryBranchBound()
    {
        $select = $this->query
            ->cols(array('c1'))
            ->from('t1')
            ->where('a = :a', array('a' => 1))
            ->union()
            ->cols(array('c2'))
            ->from('t2')
            ->where('b = :b', array('b' => 2))
            ->union()
            ->cols(array('c3'))
            ->from('t3')
            ->where('c = :c', array('c' => 3));

        $expect = array('a' => 1, 'b' => 2, 'c' => 3);
        $actual = $select->getBindValues();
        $this->assertSame($expect, $actual);
    }

    public function testUnionReadsEachBranchOnItsOwnForUnclosedQuotes()
    {
        // read end to end, the stray quote in the first branch would pair
        // with the one in the second and mask the placeholder between them
        $select = $this->query
            ->cols(array('c1'))
            ->from('t1')
            ->where("note = 'unclosed", array())
            ->where('a = :a', array('a' => 1))
            ->union()
            ->cols(array('c2'))
            ->from('t2')
            ->where("note = 'also unclosed", array())
            ->union()
            ->cols(array('c3'))
            ->from('t3');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage("The placeholder ':a'");
        $select->where('a = :a', array('a' => 999));
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
}
