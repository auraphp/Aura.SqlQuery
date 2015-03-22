<?php
namespace Aura\SqlQuery\Common;

class BulkInsertTest extends InsertTest
{
    protected $query_type = 'bulkinsert';

    protected $expected_1_row_sql = '
        INSERT INTO <<t1>>
        (<<c1>>,<<c2>>)
        VALUES
        (?,?)
    ';

    protected $expected_2_row_sql = '
        INSERT INTO <<t1>>
        (<<c1>>,<<c2>>)
        VALUES
        (?,?),
        (?,?)
    ';

    protected $expected_5_row_sql = '
        INSERT INTO <<t1>>
        (<<c1>>,<<c2>>)
        VALUES
        (?,?),
        (?,?),
        (?,?),
        (?,?),
        (?,?)
    ';

    public function testCommon()
    {
        $this
            ->query
            ->into('t1')
            ->cols(array('c1', 'c2'))
            ->rows(2);

        $actual = $this->query->__toString();
        $this->assertSameSql($this->expected_2_row_sql, $actual);
    }

    public function testBindValues()
    {
        parent::testBindValues();

        $values = array(
            array(
                'c1' => 'c1r1',
                'c2' => 'c2r1'
            ),
            array(
                'c1' => 'c1r2',
                'c2' => 'c2r2'
            )
        );

        $this
            ->query
            ->into('t1')
            ->cols(array('c1', 'c2'))
            ->bindValues($values);

        $this->assertSame(array('c1r1', 'c2r1', 'c1r2', 'c2r2'), $this->query->getBindValues());
    }

    public function testBindValue()
    {
        parent::testBindValue();

        $this
            ->query
            ->into('t1')
            ->cols(array('c1', 'c2'))
            ->bindValue('c1', array('c1r1', 'c1r2'))
            ->bindValue('c2', array('c2r1', 'c2r2'));

        $this->assertSame(array('c1r1', 'c2r1', 'c1r2', 'c2r2'), $this->query->getBindValues());
    }

    public function testRows()
    {
        $this
            ->query
            ->into('t1')
            ->cols(array('c1', 'c2'))
            ->bindValue('c1', array('c1r1', 'c1r2'))
            ->bindValue('c2', array('c2r1', 'c2r2'));

        $actual = $this->query->__toString();

        // Ensure the row count is automatically set by bindValue(s) if
        // not set by roww()
        $this->assertSameSql($this->expected_2_row_sql, $actual);

        $actual = $this->query->rows(5)->__toString();
        $expect = '
            INSERT INTO <<t1>>
            (<<c1>>,<<c2>>)
            VALUES
            (?,?),
            (?,?),
            (?,?),
            (?,?),
            (?,?)
        ';

        // Ensure rows() override the bindValue row count.
        $this->assertSameSql($this->expected_5_row_sql, $actual);
        $this->assertTrue(count($this->query->getBindValues()) == 4);
    }

    public function testNoValuesOrRows()
    {
        $this
            ->query
            ->into('t1')
            ->cols(array('c1', 'c2'));

        $actual = $this->query->__toString();
        $this->assertSameSql($this->expected_1_row_sql, $actual);
    }
}
