<?php
namespace Aura\Sql_Query\Sqlite;

use Aura\Sql_Query\Common;

class DeleteTest extends Common\DeleteTest
{
    protected $db_type = 'sqlite';

    public function testOrderLimit()
    {
        $this->query->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->orderBy(array('zim DESC'))
                    ->limit(5)
                    ->offset(10);
                    
        $actual = $this->query->__toString();
        $expect = "
            DELETE FROM <<t1>>
            WHERE
                foo = ?
                AND baz = ?
                OR zim = gir
            ORDER BY
                zim DESC
            LIMIT 5 OFFSET 10    
        ";
        $this->assertSameSql($expect, $actual);
        
        $actual = $this->query->getBindValues();
        $expect = array(
            1 => 'bar',
            2 => 'dib',
        );
        $this->assertSame($expect, $actual);
    }
}
