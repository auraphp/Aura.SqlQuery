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
}
