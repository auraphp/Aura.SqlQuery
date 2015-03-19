<?php
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;

class BulkInsertTest extends Common\BulkInsertTest
{
    protected $db_type = 'sqlite';

    protected $expected_sql_with_flag = "
        INSERT %s INTO <<t1>>
        (<<c1>>,<<c2>>)
        SELECT ?,?
        UNION SELECT ?,?
    ";

    protected $expected_1_row_sql = '
        INSERT INTO <<t1>>
        (<<c1>>,<<c2>>)
        SELECT ?,?
    ';

    protected $expected_2_row_sql = '
        INSERT INTO <<t1>>
        (<<c1>>,<<c2>>)
        SELECT ?,?
        UNION SELECT ?,?
    ';

    protected $expected_5_row_sql = '
        INSERT INTO <<t1>>
        (<<c1>>,<<c2>>)
        SELECT ?,?
        UNION SELECT ?,?
        UNION SELECT ?,?
        UNION SELECT ?,?
        UNION SELECT ?,?
    ';

    public function testOrAbort()
    {
        $this->query->orAbort()
                    ->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->bindValue('c1', array(1, 2))
                    ->bindValue('c2', array(1, 2));

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR ABORT');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrFail()
    {
        $this->query->orFail()
                    ->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->bindValue('c1', array(1, 2))
                    ->bindValue('c2', array(1, 2));

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR FAIL');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrIgnore()
    {
        $this->query->orIgnore()
                    ->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->bindValue('c1', array(1, 2))
                    ->bindValue('c2', array(1, 2));

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR IGNORE');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrReplace()
    {
        $this->query->orReplace()
                    ->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->bindValue('c1', array(1, 2))
                    ->bindValue('c2', array(1, 2));

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR REPLACE');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrRollback()
    {
        $this->query->orRollback()
                    ->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->bindValue('c1', array(1, 2))
                    ->bindValue('c2', array(1, 2));

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR ROLLBACK');

        $this->assertSameSql($expect, $actual);
    }
}
