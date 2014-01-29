<?php
namespace Aura\Sql_Query\Mysql;

use Aura\Sql_Query\Common;

class DeleteTest extends Common\DeleteTest
{
    protected $db_type = 'mysql';
    
    protected $expected_sql_with_flag = "
        DELETE %s FROM <<t1>>
            WHERE
                foo = ?
                AND baz = ?
                OR zim = gir
    ";

    public function testOrderByLimit()
    {
        $this->query->from('t1')
                    ->orderBy(array('c1', 'c2'))
                    ->limit(10);
        
        $actual = $this->query->__toString();
        $expect = '
            DELETE FROM <<t1>>
                ORDER BY
                    c1,
                    c2
                LIMIT 10
        ';
        $this->assertSameSql($expect, $actual);
    }
    
    public function testLowPriority()
    {
        $this->query->lowPriority()
                    ->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir');

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'LOW_PRIORITY');
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(
            1 => 'bar',
            2 => 'dib',
        );
        $this->assertSame($expect, $actual);
    }

    public function testQuick()
    {
        $this->query->quick()
                    ->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir');

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'QUICK');
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
                    ->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir');

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'IGNORE');
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(
            1 => 'bar',
            2 => 'dib',
        );
        $this->assertSame($expect, $actual);
    }
}
