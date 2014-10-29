<?php
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'mysql';

    protected $expected_sql_with_flag = "
        INSERT %s INTO <<t1>> (
            <<c1>>,
            <<c2>>,
            <<c3>>,
            <<c4>>,
            <<c5>>
        ) VALUES (
            :c1,
            :c2,
            :c3,
            NOW(),
            NULL
        )
    ";

    protected $expected_sql_on_duplicate_key_update = "
        INSERT INTO <<t1>> (
            <<c1>>,
            <<c2>>,
            <<c3>>,
            <<c4>>,
            <<c5>>
        ) VALUES (
            :c1,
            :c2,
            :c3,
            NOW(),
            NULL
        ) ON DUPLICATE KEY UPDATE
            <<c4>> = %s
    ";

    public function testHighPriority()
    {
        $this->query->highPriority()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'HIGH_PRIORITY');

        $this->assertSameSql($expect, $actual);
    }

    public function testLowPriority()
    {
        $this->query->lowPriority()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'LOW_PRIORITY');

        $this->assertSameSql($expect, $actual);
    }

    public function testDelayed()
    {
        $this->query->delayed()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'DELAYED');

        $this->assertSameSql($expect, $actual);
    }

    public function testIgnore()
    {
        $this->query->ignore()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'IGNORE');

        $this->assertSameSql($expect, $actual);
    }

    public function testColOnUpdate()
    {
        $this->query->colOnUpdate ('c4')
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf ($this->expected_sql_on_duplicate_key_update, ':on_update_c4');

        $this->assertSameSql($expect, $actual);
    }

    public function testColsOnUpdate()
    {
        $this->query->colsOnUpdate (array('c4' => null))
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf ($this->expected_sql_on_duplicate_key_update, ':on_update_c4');

        $this->assertSameSql($expect, $actual);
    }

    public function testSetOnUpdate()
    {
        $this->query->setOnUpdate ('c4', 'NOW()')
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf ($this->expected_sql_on_duplicate_key_update, 'NOW()');

        $this->assertSameSql($expect, $actual);
    }
}
