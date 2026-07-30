<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

class UpdateTest extends Common\UpdateTest
{
    protected $db_type = 'pgsql';

    /**
     * Postgres has no UPDATE-level IGNORE, and no REPLACE at all; asking for
     * either has to say so rather than fatal on an undefined method.
     */
    public function testIgnoreNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support IGNORE flag");
        $this->query->ignore();
    }

    public function testOrReplaceNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support OR REPLACE flag");
        $this->query->orReplace();
    }

    public function testReturning()
    {
        $this->query->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
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
                foo = :foo
                AND baz = :baz
                OR zim = gir
            RETURNING
                c1,
                c2,
                c3
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
