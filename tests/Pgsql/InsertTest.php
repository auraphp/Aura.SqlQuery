<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;
use PHPUnit\Framework\Attributes\DataProvider;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'pgsql';

    /**
     * Postgres has no REPLACE; asking for it has to say so rather than
     * fatal on an undefined method.
     */
    public function testOrReplaceNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support OR REPLACE flag");
        $this->query->orReplace();
    }

    public function testReturning()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null)
                    ->returning(array('c1', 'c2'))
                    ->returning(array('c3'));

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>,
                <<c3>>,
                <<c4>>,
                <<c5>>
            ) VALUES (
                :c1,
                :c2,
                :c3,
                NOW(),
                NULL
            )
            RETURNING
                c1,
                c2,
                c3
        ";

        $this->assertSameSql($expect, $actual);
    }

    public function testGetLastInsertIdName_default()
    {
        $this->query->into('table');
        $actual = $this->query->getLastInsertIdName('col');
        $expect = 'table_col_seq';
        $this->assertSame($expect, $actual);
    }

    public function testIgnore()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->ignore()
                    ->returning(array('c1'));

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>
            ) VALUES (
                :c1,
                :c2
            )
            ON CONFLICT DO NOTHING
            RETURNING
                c1
        ";

        $this->assertSameSql($expect, $actual);
    }

    public function testIgnoreDisable()
    {
        $this->query->into('t1')
                    ->cols(array('c1'))
                    ->ignore()
                    ->ignore(false);

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>
            ) VALUES (
                :c1
            )
        ";

        $this->assertSameSql($expect, $actual);
    }

    public function testOnConflictDoNothing()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->onConflict('c1')
                    ->ignore();

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>
            ) VALUES (
                :c1,
                :c2
            )
            ON CONFLICT (<<c1>>) DO NOTHING
        ";
        $this->assertSameSql($expect, $actual);
    }

    public function testOnConflictDoUpdate()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->onConflict(array('c1', 'c2'))
                    ->doUpdateCols(array('c2'))
                    ->doUpdate('c3', 'excluded.c3');

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>,
                <<c3>>
            ) VALUES (
                :c1,
                :c2,
                :c3
            )
            ON CONFLICT (<<c1>>, <<c2>>) DO UPDATE SET
                <<c2>> = excluded.<<c2>>,
                <<c3>> = <<excluded>>.<<c3>>
        ";
        $this->assertSameSql($expect, $actual);
    }

    public function testOnConflictDoUpdateWhere()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->onConflict('c1')
                    ->doUpdateCol('c2', 'c2-updated')
                    ->doUpdateWhere('t1.c2 != :c2_old', array('c2_old' => 'foo'))
                    ->doUpdateWhere('t1.c3 = :c3_old', array('c3_old' => 'bar'));

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>
            ) VALUES (
                :c1,
                :c2
            )
            ON CONFLICT (<<c1>>) DO UPDATE SET
                <<c2>> = :c2__on_conflict
            WHERE
                <<t1>>.<<c2>> != :c2_old
                AND <<t1>>.<<c3>> = :c3_old
        ";
        $this->assertSameSql($expect, $actual);

        $binds = $this->query->getBindValues();
        $this->assertSame('c2-updated', $binds['c2__on_conflict']);
        $this->assertSame('foo', $binds['c2_old']);
        $this->assertSame('bar', $binds['c3_old']);
    }

    /**
     *
     * A bulk insert renames each row's placeholders and banks them, then
     * clears the working values ready for the next row. Neither the DO UPDATE
     * value nor its WHERE condition is a row value, and the statement goes on
     * spelling both placeholders, so they have to survive that clearing: an
     * unbound placeholder makes execute() fail with HY093.
     *
     */
    public function testBulkInsertKeepsTheDoUpdateBinds()
    {
        $this->query->into('t1')
                    ->cols(array('c1' => 'v1-0'))
                    ->addRow(array('c1' => 'v1-1'))
                    ->onConflict('c1')
                    ->doUpdateCol('c2', 'c2-updated')
                    ->doUpdateWhere('t1.c3 = :c3_old', array('c3_old' => 'bar'));

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>>
                (<<c1>>)
            VALUES
                (:c1_0),
                (:c1_1)
            ON CONFLICT (<<c1>>) DO UPDATE SET
                <<c2>> = :c2__on_conflict
            WHERE
                <<t1>>.<<c3>> = :c3_old
        ";
        $this->assertSameSql($expect, $actual);

        $expect = array(
            'c2__on_conflict' => 'c2-updated',
            'c3_old' => 'bar',
            'c1_0' => 'v1-0',
            'c1_1' => 'v1-1',
        );
        $this->assertSame($expect, $this->query->getBindValues());
    }

    /**
     * The constraint-name conflict target is Postgres-only.
     */
    public function testOnConflictConstraintTarget()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->onConflict('ON CONSTRAINT t1_c1_key')
                    ->doUpdateCols(array('c2'));

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<c2>>
            ) VALUES (
                :c1,
                :c2
            )
            ON CONFLICT ON CONSTRAINT <<t1_c1_key>> DO UPDATE SET
                <<c2>> = excluded.<<c2>>
        ";
        $this->assertSameSql($expect, $actual);
    }

    /**
     * An empty target is the same mistake as no target, but it used to slip
     * past the no-target check and build `ON CONFLICT ()`, which the server
     * rejects as a syntax error.
     *
     * @param string|array $target
     */
    #[DataProvider('provideEmptyConflictTarget')]
    public function testOnConflictThrowsExceptionWhenTargetEmpty($target)
    {
        $this->expectException('Aura\SqlQuery\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('onConflict() requires a column name or constraint.');
        $this->query->onConflict($target);
    }

    public static function provideEmptyConflictTarget()
    {
        return array(
            'empty array' => array(array()),
            'empty string' => array(''),
            'blank string' => array('   '),
            'array of blanks' => array(array('')),
            'array with a blank' => array(array('c1', '')),
            'constraint keyword alone' => array('ON CONSTRAINT'),
            'constraint with no name' => array('ON CONSTRAINT   '),
        );
    }

    public function testOnConflictThrowsExceptionWhenNoTarget()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->doUpdateCols(array('c2'));

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('Database requires a conflict target for DO UPDATE.');
        $this->query->__toString();
    }

    public function testOnConflictDoUpdateAmbiguousColumn()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'hits'))
                    ->onConflict('c1')
                    ->doUpdate('hits', 't1.hits + 1');

        $actual = $this->query->__toString();
        $expect = "
            INSERT INTO <<t1>> (
                <<c1>>,
                <<hits>>
            ) VALUES (
                :c1,
                :hits
            )
            ON CONFLICT (<<c1>>) DO UPDATE SET
                <<hits>> = <<t1>>.<<hits>> + 1
        ";
        $this->assertSameSql($expect, $actual);
    }

    public function testOnConflictThrowsExceptionWhenIgnoreAndUpdate()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->onConflict('c1')
                    ->ignore()
                    ->doUpdateCols(array('c2'));

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('Cannot combine IGNORE / DO NOTHING with DO UPDATE SET.');
        $this->query->__toString();
    }
}
