<?php
namespace Aura\Sql_Query\Mysql;

use Aura\Sql_Query\Common\DeleteTest as CommonDeleteTest;

class DeleteTest extends CommonDeleteTest
{
    protected $query_type = 'Delete';

    protected $db_type = 'mysql';
    
    protected $expected_sql_with_flag = "
        DELETE %s FROM <<t1>>
            WHERE
                foo = :auto_bind_0
                AND baz = :auto_bind_1
                OR zim = gir
    ";

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
    }
}
