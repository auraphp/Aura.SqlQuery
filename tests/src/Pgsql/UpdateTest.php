<?php
namespace Aura\Sql_Query\Pgsql;

use Aura\Sql_Query\Common;

class UpdateTest extends Common\UpdateTest
{
    protected $db_type = 'pgsql';

    public function testReturning()
    {
        $this->query->table('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->returning(array('c1', 'c2'))
                    ->returning(array('c3'));

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
                foo = ?
                AND baz = ?
                OR zim = gir
            RETURNING
                c1,
                c2,
                c3
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
