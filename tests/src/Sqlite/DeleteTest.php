<?php
namespace Aura\Sql_Query\Sqlite;

use Aura\Sql_Query\DeleteTest as CommonDeleteTest;

class DeleteTest extends CommonDeleteTest
{
    protected $db_type = 'sqlite';

    public function testOrderLimit()
    {
        $this->query->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->orderBy(['zim DESC'])
                    ->limit(5)
                    ->offset(10);
                    
        $actual = $this->query->__toString();
        $expect = "
            DELETE FROM <<t1>>
            WHERE
                foo = :auto_bind_0
                AND baz = :auto_bind_1
                OR zim = gir
            ORDER BY 
                zim DESC
            LIMIT 5 OFFSET 10    
        ";
        
        $this->assertSameSql($expect, $actual);
    }
}
