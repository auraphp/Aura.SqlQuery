<?php
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQueryTest;

class UpdateTest extends AbstractQueryTest
{
    protected $query_type = 'update';

    public function testExceptionWithNoCols()
    {
        $this->query->table('t1')->where('foo = :foo', ['foo' => 'bar']);
        $this->expectException('Aura\SqlQuery\Exception');
        $this->query->__toString();
    }

    public function testCommon()
    {
        $this->query->table('t1')
                    ->cols(['c1', 'c2'])
                    ->col('c3')
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir');

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
        ";

        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = array(
            'foo' => 'bar',
            'baz' => 'dib',
        );
        $this->assertSame($expect, $actual);
    }

    /**
     *
     * `UPDATE a, b SET ...` is MySQL-only grammar; Postgres, SQLite and SQL
     * Server each spell a multi-table update differently, and none of them
     * with a comma. The list used to be quoted whole as <<t1,>> <<t2>>, an
     * identifier no database has, so the statement failed at execute time
     * with nothing to say why. Refuse it while the caller can still act on
     * it. See #160.
     *
     */
    public function testTableRejectsMultipleTables()
    {
        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('one table');
        $this->query->table('t1, t2');
    }

    /**
     *
     * The message has to point somewhere: a sub-select in the WHERE is the
     * portable way to update one table against another.
     *
     */
    public function testTableMultipleTablesMessageNamesTheAlternative()
    {
        try {
            $this->query->table('t1, t2');
            $this->fail('Expected a rejection of the multi-table list.');
        } catch (\Aura\SqlQuery\Exception\LogicException $e) {
            $this->assertStringContainsString('t1, t2', $e->getMessage());
            $this->assertStringContainsString('sub-select', $e->getMessage());
        }
    }

    /**
     *
     * A comma inside a quoted identifier is part of the name, so this is one
     * table and must not be refused as a list.
     *
     */
    public function testTableAcceptsAQuotedComma()
    {
        $name = $this->query->getQuoteNamePrefix()
              . 'odd,name'
              . $this->query->getQuoteNameSuffix();

        $this->query->table($name)->cols(array('c1'));

        $actual = $this->query->__toString();
        $expect = "
            UPDATE {$name}
            SET
                <<c1>> = :c1
        ";
        $this->assertSameSql($expect, $actual);
    }

    /**
     *
     * A doubled closing quote escapes it, so this is one table whose name
     * contains a comma, and refusing it as a list would be wrong.
     *
     */
    public function testTableAcceptsAnEscapedQuoteInsideAName()
    {
        $prefix = $this->query->getQuoteNamePrefix();
        $suffix = $this->query->getQuoteNameSuffix();
        $name = $prefix . 'odd' . $suffix . $suffix . ',name' . $suffix;

        $this->query->table($name)->cols(array('c1'));

        $actual = $this->query->__toString();
        $expect = "
            UPDATE {$name}
            SET
                <<c1>> = :c1
        ";
        $this->assertSameSql($expect, $actual);
    }

    public function testHasCols()
    {
        $this->query->table('t1');
        $this->assertFalse($this->query->hasCols());
        $this->query->cols(['c1', 'c2']);
        $this->assertTrue($this->query->hasCols());
    }
}
