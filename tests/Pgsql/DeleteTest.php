<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

class DeleteTest extends Common\DeleteTest
{
    protected $db_type = 'pgsql';

    /**
     * Postgres has no DELETE-level IGNORE; asking for it has to say so rather than
     * fatal on an undefined method.
     */
    public function testIgnoreNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support IGNORE flag");
        $this->query->ignore();
    }

    public function testReturning()
    {
        $this->query->from('t1')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->returning(['foo', 'baz', 'zim']);

        $actual = $this->query->__toString();
        $expect = "
            DELETE FROM <<t1>>
            WHERE
                foo = :foo
                AND baz = :baz
                OR zim = gir
            RETURNING
                foo,
                baz,
                zim
        ";
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }
}
