<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

class DeleteTest extends Common\DeleteTest
{
    protected $db_type = 'pgsql';

    public function testReturning()
    {
        $this->query->from('t1')
                    ->where('foo = ?', 'bar')
                    ->where('baz = ?', 'dib')
                    ->orWhere('zim = gir')
                    ->returning(array('foo', 'baz', 'zim'));

        $actual = $this->query->__toString();
        $expect = "
            DELETE FROM <<t1>>
            WHERE
                foo = ?
                AND baz = ?
                OR zim = gir
            RETURNING
                foo,
                baz,
                zim
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
