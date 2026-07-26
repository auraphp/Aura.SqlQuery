<?php
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;
use PHPUnit\Framework\Attributes\DataProvider;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'mysql';

    protected $expected_sql_no_cols = "
        INSERT INTO <<t1>> () VALUES ()
    ";

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

    protected $expected_sql_on_duplicate_key_update = "
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
        ) ON DUPLICATE KEY UPDATE
            <<c1>> = :c1__on_duplicate_key,
            <<c2>> = :c2__on_duplicate_key,
            <<c3>> = :c3__on_duplicate_key,
            <<c4>> = NULL,
            <<c5>> = :c5__on_duplicate_key
    ";

    protected $expected_replace_sql = "
        REPLACE INTO <<t1>> (
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

    protected $expected_replace_sql_with_flag = "
        REPLACE %s INTO <<t1>> (
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

    public function testHighPriority()
    {
        $this->query->highPriority()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'HIGH_PRIORITY');

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
        $this->assertSameSql($this->expected_replace_sql, $actual);
    }

    /**
     * REPLACE takes LOW_PRIORITY and DELAYED, so those still build.
     */
    #[DataProvider('provideReplaceFlagAllowed')]
    public function testOrReplaceWithAllowedFlag($method, $flag)
    {
        $this->query->orReplace()
                    ->$method()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_replace_sql_with_flag, $flag);

        $this->assertSameSql($expect, $actual);
    }

    public static function provideReplaceFlagAllowed()
    {
        return array(
            array('lowPriority', 'LOW_PRIORITY'),
            array('delayed', 'DELAYED'),
        );
    }

    public static function provideReplaceFlagForbidden()
    {
        return array(
            array('highPriority', 'HIGH_PRIORITY'),
            array('ignore', 'IGNORE'),
        );
    }

    /**
     * HIGH_PRIORITY and IGNORE are INSERT-only; combining them with REPLACE
     * used to build a statement MySQL rejects at parse time.
     */
    #[DataProvider('provideReplaceFlagForbidden')]
    public function testOrReplaceWithForbiddenFlag($method, $flag)
    {
        $this->query->orReplace()
                    ->$method()
                    ->into('t1')
                    ->cols(array('c1'));

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage("A REPLACE cannot take the $flag flag.");
        $this->query->__toString();
    }

    /**
     * The flag order must not matter -- the check happens at build time, so
     * it catches the flag whether it was set before or after orReplace().
     */
    #[DataProvider('provideReplaceFlagForbidden')]
    public function testForbiddenFlagBeforeOrReplace($method, $flag)
    {
        $this->query->$method()
                    ->orReplace()
                    ->into('t1')
                    ->cols(array('c1'));

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage("A REPLACE cannot take the $flag flag.");
        $this->query->__toString();
    }

    /**
     * Unsetting the flag again clears the way, and unsetting orReplace()
     * leaves the plain INSERT alone.
     */
    public function testForbiddenFlagDisabled()
    {
        $this->query->orReplace()
                    ->ignore()
                    ->ignore(false)
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $this->assertSameSql($this->expected_replace_sql, $this->query->__toString());

        $this->query->orReplace(false);
        $this->assertSameSql(
            str_replace('%s ', '', $this->expected_sql_with_flag),
            $this->query->__toString()
        );
    }

    public function testLowPriority()
    {
        $this->query->lowPriority()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'LOW_PRIORITY');

        $this->assertSameSql($expect, $actual);
    }

    public function testDelayed()
    {
        $this->query->delayed()
                    ->into('t1')
                    ->cols(array('c1', 'c2', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'DELAYED');

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
        $expect = sprintf($this->expected_sql_with_flag, 'IGNORE');

        $this->assertSameSql($expect, $actual);
    }

    public function testOnDuplicateKeyUpdate()
    {
        $this->query->into('t1')
                    ->cols(array('c1', 'c2' => 'c2-inserted', 'c3'))
                    ->set('c4', 'NOW()')
                    ->set('c5', null)
                    ->onDuplicateKeyUpdateCols(array('c1', 'c2' => 'c2-updated', 'c3'))
                    ->onDuplicateKeyUpdate('c4', null)
                    ->onDuplicateKeyUpdateCol('c5', 'c5-updated');

        $actual = $this->query->__toString();
        $expect = $this->expected_sql_on_duplicate_key_update;
        $this->assertSameSql($expect, $actual);

        $expect = array (
            'c2' => 'c2-inserted',
            'c2__on_duplicate_key' => 'c2-updated',
            'c5__on_duplicate_key' => 'c5-updated',
        );
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }
}
