<?php
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;
use PHPUnit\Framework\Attributes\DataProvider;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'sqlite';

    protected $expected_sql_with_flag = "
        INSERT %s INTO <<t1>> (
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
    ";

    public function testOrAbort()
    {
        $this->query->orAbort()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR ABORT');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrFail()
    {
        $this->query->orFail()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR FAIL');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrIgnore()
    {
        $this->query->orIgnore()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR IGNORE');

        $this->assertSameSql($expect, $actual);
    }

    public function testIgnore()
    {
        $this->query->ignore()
            ->into('t1')
            ->cols(array('c1', 'c2', 'c3'))
            ->set('c4', 'NOW()')
            ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR IGNORE');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrReplace()
    {
        $this->query->orReplace()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR REPLACE');

        $this->assertSameSql($expect, $actual);
    }

    public function testOrRollback()
    {
        $this->query->orRollback()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR ROLLBACK');

        $this->assertSameSql($expect, $actual);
    }

    /**
     * The OR clauses are alternatives to each other, so asking for two of
     * them used to stack both into the statement.
     */
    #[DataProvider('provideConflictClausePair')]
    public function testTwoConflictClauses($first, $second, $message)
    {
        $this->query->$first()
                    ->$second()
                    ->into('t1')
                    ->cols(array('c1'));

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage($message);
        $this->query->__toString();
    }

    public static function provideConflictClausePair()
    {
        return array(
            array('orIgnore', 'orReplace', 'OR IGNORE and OR REPLACE'),
            array('ignore', 'orReplace', 'OR IGNORE and OR REPLACE'),
            array('orAbort', 'orFail', 'OR ABORT and OR FAIL'),
            array('orRollback', 'orAbort', 'OR ABORT and OR ROLLBACK'),
        );
    }

    /**
     * Switching from one clause to another is fine as long as the first is
     * turned off, and all three of them being off is a plain INSERT.
     */
    public function testSwitchConflictClause()
    {
        $this->query->orIgnore()
                    ->orIgnore(false)
                    ->orReplace()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $this->assertSameSql(
            sprintf($this->expected_sql_with_flag, 'OR REPLACE'),
            $this->query->__toString()
        );

        $this->query->orReplace(false);
        $this->assertSameSql(
            str_replace('%s ', '', $this->expected_sql_with_flag),
            $this->query->__toString()
        );
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
                    ->doUpdateWhere('t1.c2 != :c2_old', array('c2_old' => 'foo'));

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
        ";
        $this->assertSameSql($expect, $actual);

        $binds = $this->query->getBindValues();
        $this->assertSame('c2-updated', $binds['c2__on_conflict']);
        $this->assertSame('foo', $binds['c2_old']);
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

    public function testCannotCombineWithOrFlags()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->onConflict('c1')
                    ->orReplace();

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('Cannot combine ON CONFLICT clause with SQLite OR conflict flags: OR REPLACE.');
        $this->query->__toString();
    }

    public function testOnConflictFlagStateNotCorruptedOnException()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2'))
                    ->onConflict('c1')
                    ->ignore()
                    ->orReplace();

        try {
            $this->query->__toString();
            $this->fail('Expected LogicException was not thrown.');
        } catch (\Aura\SqlQuery\Exception\LogicException $e) {
            $this->assertStringContainsString('Cannot combine ON CONFLICT clause with SQLite OR conflict flags: OR REPLACE.', $e->getMessage());
        }

        // The caller fixes the offending flag and reuses the object. The
        // ignore() set earlier has to survive: build() clears OR IGNORE
        // while rendering, so a throw between the clear and the restore
        // used to strip it for good, turning DO NOTHING into a plain
        // INSERT with nothing to report it.
        $this->query->orReplace(false);

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
        $this->assertSameSql($expect, $this->query->__toString());
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

    /**
     * SQLite takes only a column list as the conflict target; the
     * constraint-name form is Postgres-only and is a syntax error here.
     */
    public function testOnConflictConstraintTargetNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support a constraint-name conflict target");
        $this->query->onConflict('ON CONSTRAINT t1_c1_key');
    }
}
