<?php
namespace Aura\Sql_Query\Pgsql;

use Aura\Sql_Query\DeleteTest as CommonDeleteTest;

class DeleteTest extends CommonDeleteTest
{
    protected $db_type = 'pgsql';

    public function testReturning()
    {
        $this->query->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->returning(['foo', 'baz', 'zim']);

        $actual = $this->query->__toString();
        $expect = "
            DELETE FROM <<t1>>
            WHERE
                foo = :auto_bind_0
                AND baz = :auto_bind_1
                OR zim = gir
            RETURNING
                foo,
                baz,
                zim
        ";

        $this->assertSameSql($expect, $actual);
    }
}
