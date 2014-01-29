<?php
namespace Aura\Sql_Query\Mysql;

use Aura\Sql_Query\Common;

class UpdateTest extends Common\UpdateTest
{
    protected $db_type = 'mysql';

    protected $expected_sql_with_flag = "
        UPDATE%s <<t1>>
            SET
                <<c1>> = :c1,
                <<c2>> = :c2,
                <<c3>> = :c3,
                <<c4>> = NULL,
                <<c5>> = NOW()
            WHERE
                foo = ?
                AND baz = ?
                OR zim = gir
            LIMIT 5
    ";

    public function testOrderByLimit()
    {
        $this->query->table('t1')
                    ->col('c1')
                    ->orderBy(array('c2'))
                    ->limit(10);
                    
        $actual = $this->query->__toString();
        $expect = '
            UPDATE <<t1>>
                SET
                    <<c1>> = :c1
                ORDER BY
                    c2
                LIMIT 10
        ';
        
        $this->assertSameSql($expect, $actual);
    }
    
    public function testLowPriority()
    {
        $this->query->lowPriority()
                    ->table('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, ' LOW_PRIORITY');
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(
            1 => 'bar',
            2 => 'dib',
        );
        $this->assertSame($expect, $actual);
    }

    public function testIgnore()
    {
        $this->query->ignore()
                    ->table('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, ' IGNORE');
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(
            1 => 'bar',
            2 => 'dib',
        );
        $this->assertSame($expect, $actual);
    }
}
