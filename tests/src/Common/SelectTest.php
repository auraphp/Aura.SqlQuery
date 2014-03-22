<?php
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\AbstractQueryTest;

class SelectTest extends AbstractQueryTest
{
    protected $query_type = 'select';

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
    }

    public function testCols()
    {
        $this->query->cols(array('t1.c1', 'c2', 'COUNT(t1.c3)'));
        $actual = $this->query->__toString();
        $expect = '
            SELECT
                <<t1>>.<<c1>>,
                c2,
                COUNT(<<t1>>.<<c3>>)
        ';
        $this->assertSameSql($expect, $actual);
    }

    public function testFrom()
    {
        $this->query->from('t1')
                     ->from('t2');

        $actual = $this->query->__toString();
        $expect = '
            SELECT
            FROM
                <<t1>>,
                <<t2>>
        ';
        $this->assertSameSql($expect, $actual);
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

    public function testFromSubSelectObject()
    {
        $sub = $this->newQuery();
        $sub->cols(array('*'))->from('t2');

        $this->query->cols(array('*'))->fromSubSelect($sub, 'a2');
        $expect = '
            SELECT
                *
            FROM
                (
                SELECT
                    *
                FROM
                    <<t2>>
                ) AS <<a2>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoin()
    {
        $this->query->from('t1');
        $this->query->join('left', 't2', 't1.id = t2.id');
        $this->query->join('inner', 't3 AS a3', 't2.id = a3.id');
        $this->query->join('natural', 't4');
        $expect = '
            SELECT
            FROM
                <<t1>>
            LEFT JOIN <<t2>> ON <<t1>>.<<id>> = <<t2>>.<<id>>
            INNER JOIN <<t3>> AS <<a3>> ON <<t2>>.<<id>> = <<a3>>.<<id>>
            NATURAL JOIN <<t4>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        // try to join without from
        $select = $this->newQuery();
        $this->setExpectedException('Aura\Sql_Query\Exception');
        $select->join('left', 't2', 't1.id = t2.id');
    }

    public function testLeftAndInnerJoin()
    {
        $this->query->from('t1');
        $this->query->leftJoin('t2', 't1.id = t2.id');
        $this->query->innerJoin('t3 AS a3', 't2.id = a3.id');
        $this->query->join('natural', 't4');
        $expect = '
            SELECT
            FROM
                <<t1>>
            LEFT JOIN <<t2>> ON <<t1>>.<<id>> = <<t2>>.<<id>>
            INNER JOIN <<t3>> AS <<a3>> ON <<t2>>.<<id>> = <<a3>>.<<id>>
            NATURAL JOIN <<t4>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        // try to join without from
        $select = $this->newQuery();
        $this->setExpectedException('Aura\Sql_Query\Exception');
        $select->leftJoin('left', 't2', 't1.id = t2.id');
    }

    public function testJoinSubSelect()
    {
        $sub1 = 'SELECT * FROM t2';
        $sub2 = 'SELECT * FROM t3';
        $this->query->from('t1');
        $this->query->joinSubSelect('left', $sub1, 'a2', 't2.c1 = a3.c1');
        $this->query->joinSubSelect('natural', $sub2, 'a3');
        $expect = '
            SELECT
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
        
        // try to join without from
        $select = $this->newQuery();
        $this->setExpectedException('Aura\Sql_Query\Exception');
        $select->joinSubSelect('left', $sub1, 'a2', 't2.c1 = a3.c1');
    }

    public function testJoinSubSelectObject()
    {
        $sub = $this->newQuery();
        $sub->cols(array('*'))->from('t2');

        $this->query->from('t1');
        $this->query->joinSubSelect('left', $sub, 'a3', 't2.c1 = a3.c1');
        $expect = '
            SELECT
            FROM
                <<t1>>
            LEFT JOIN (
                SELECT
                    *
                FROM
                    <<t2>>
            ) AS <<a3>> ON <<t2>>.<<c1>> = <<a3>>.<<c1>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testJoinOrder()
    {
        $this->query
            ->from('t1')
            ->join('inner', 't2', 't2.id = t1.id')
            ->join('left', 't3', 't3.id = t2.id')
            ->from('t4')
            ->join('inner', 't5', 't5.id = t4.id');
        $expect = '
            SELECT
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

    public function testWhere()
    {
        $this->query->where('c1 = c2')
                     ->where('c3 = ?', 'foo');
        $expect = '
            SELECT
            WHERE
                c1 = c2
                AND c3 = ?
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(1 => 'foo');
        $this->assertSame($expect, $actual);
    }

    public function testOrWhere()
    {
        $this->query->orWhere('c1 = c2')
                     ->orWhere('c3 = ?', 'foo');

        $expect = '
            SELECT
            WHERE
                c1 = c2
                OR c3 = ?
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(1 => 'foo');
        $this->assertSame($expect, $actual);
    }

    public function testGroupBy()
    {
        $this->query->groupBy(array('c1', 't2.c2'));
        $expect = '
            SELECT
            GROUP BY
                c1,
                <<t2>>.<<c2>>
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testHaving()
    {
        $this->query->having('c1 = c2')
                     ->having('c3 = ?', 'foo');
        $expect = '
            SELECT
            HAVING
                c1 = c2
                AND c3 = ?
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(1 => 'foo');
        $this->assertSame($expect, $actual);
    }

    public function testOrHaving()
    {
        $this->query->orHaving('c1 = c2')
                     ->orHaving('c3 = ?', 'foo');
        $expect = '
            SELECT
            HAVING
                c1 = c2
                OR c3 = ?
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(1 => 'foo');
        $this->assertSame($expect, $actual);
    }

    public function testOrderBy()
    {
        $this->query->orderBy(array('c1', 'UPPER(t2.c2)', ));
        $expect = '
            SELECT
            ORDER BY
                c1,
                UPPER(<<t2>>.<<c2>>)
        ';

        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testLimitOffset()
    {
        $this->query->limit(10);
        $expect = '
            SELECT
            LIMIT 10
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        $this->query->offset(40);
        $expect = '
            SELECT
            LIMIT 10 OFFSET 40
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testPage()
    {
        $this->query->page(5);
        $expect = '
            SELECT
            LIMIT 10 OFFSET 40
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testForUpdate()
    {
        $this->query->forUpdate();
        $expect = '
            SELECT
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
        $this->query->having('baz IN (?)', array('dib', 'zim', 'gir'));
        $this->query->where('foo = ?', 'bar');
        
        $expect = '
            SELECT
            WHERE
                foo = ?
            HAVING
                baz IN (?)
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
        
        $expect = array(
            1 => 'bar',
            2 => array('dib', 'zim', 'gir'),
        );
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }
}
