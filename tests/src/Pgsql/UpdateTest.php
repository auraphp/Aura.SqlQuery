<?php
namespace Aura\Sql_Query\Pgsql;

use Aura\Sql_Query\Common\UpdateTest as CommonUpdateTest;

class UpdateTest extends CommonUpdateTest
{
    protected $db_type = 'pgsql';

    public function testReturning()
    {
        $this->query->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->returning(['c1', 'c2'])
                    ->returning(['c3']);

        $actual = $this->query->__toString();
        $expect = "
            UPDATE <<t1>>
            SET
                <<c1>> = :c1,
                <<c2>> = :c2,
                <<c3>> = :c3,
                <<c4>> = NULL,
                <<c5>> = NOW()
            WHERE
                foo = :auto_bind_0
                AND baz = :auto_bind_1
                OR zim = gir
            RETURNING
                c1,
                c2,
                c3
        ";

        $this->assertSameSql($expect, $actual);
    }
}
