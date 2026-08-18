<?php
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;
use PHPUnit\Framework\Attributes\DataProvider;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'mysql';

    protected function supportsWith()
    {
        return false;
    }

    /**
     *
     * MySQL allows a CTE only inside the SELECT an `INSERT ... SELECT` draws
     * from, which this package does not build; the clause is refused rather
     * than rendered into a statement that can only fail at execute time.
     *
     */
    public function testWithIsRefused()
    {
        $this->expectException('Aura\\SqlQuery\\Exception\\BadMethodCallException');
        $this->expectExceptionMessage('MySQL does not allow a WITH clause on INSERT.');
        $this->query->with('cte', $this->query_factory->newSelect());
    }

    public function testWithRecursiveIsRefused()
    {
        $this->expectException('Aura\\SqlQuery\\Exception\\BadMethodCallException');
        $this->query->withRecursive('cte', $this->query_factory->newSelect());
    }

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
                    ->cols(['c1', 'c2', 'c3'])
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
                    ->cols(['c1', 'c2', 'c3'])
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
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_replace_sql_with_flag, $flag);

        $this->assertSameSql($expect, $actual);
    }

    public static function provideReplaceFlagAllowed()
    {
        return [
            ['lowPriority', 'LOW_PRIORITY'],
            ['delayed', 'DELAYED'],
        ];
    }

    public static function provideReplaceFlagForbidden()
    {
        return [
            ['highPriority', 'HIGH_PRIORITY'],
            ['ignore', 'IGNORE'],
        ];
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
                    ->cols(['c1']);

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
                    ->cols(['c1']);

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage("A REPLACE cannot take the $flag flag.");
        $this->query->__toString();
    }

    /**
     * LOW_PRIORITY, HIGH_PRIORITY and DELAYED are alternatives to each
     * other on INSERT as much as on REPLACE, so two of them is a syntax
     * error either way. Each pair is given in both orders.
     */
    #[DataProvider('providePriorityModifierPair')]
    public function testTwoPriorityModifiers($first, $second, $message)
    {
        $this->query->$first()
                    ->$second()
                    ->into('t1')
                    ->cols(['c1']);

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage($message);
        $this->query->__toString();
    }

    public static function providePriorityModifierPair()
    {
        $pairs = [
            ['lowPriority', 'highPriority', 'LOW_PRIORITY and HIGH_PRIORITY'],
            ['lowPriority', 'delayed', 'LOW_PRIORITY and DELAYED'],
            ['highPriority', 'delayed', 'HIGH_PRIORITY and DELAYED'],
        ];

        $both_orders = [];
        foreach ($pairs as $pair) {
            $both_orders[] = $pair;
            $both_orders[] = [$pair[1], $pair[0], $pair[2]];
        }
        return $both_orders;
    }

    /**
     * REPLACE takes LOW_PRIORITY or DELAYED, but still only one of them.
     */
    public function testOrReplaceWithTwoPriorityModifiers()
    {
        $this->query->orReplace()
                    ->lowPriority()
                    ->delayed()
                    ->into('t1')
                    ->cols(['c1']);

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('LOW_PRIORITY and DELAYED');
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
                    ->cols(['c1', 'c2', 'c3'])
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
                    ->cols(['c1', 'c2', 'c3'])
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
                    ->cols(['c1', 'c2', 'c3'])
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
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', 'NOW()')
                    ->set('c5', null);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'IGNORE');

        $this->assertSameSql($expect, $actual);
    }

    public function testOnDuplicateKeyUpdate()
    {
        $this->query->into('t1')
                    ->cols(['c1', 'c2' => 'c2-inserted', 'c3'])
                    ->set('c4', 'NOW()')
                    ->set('c5', null)
                    ->onDuplicateKeyUpdateCols(['c1', 'c2' => 'c2-updated', 'c3'])
                    ->onDuplicateKeyUpdate('c4', null)
                    ->onDuplicateKeyUpdateCol('c5', 'c5-updated');

        $actual = $this->query->__toString();
        $expect = $this->expected_sql_on_duplicate_key_update;
        $this->assertSameSql($expect, $actual);

        $expect = [
            'c2' => 'c2-inserted',
            'c2__on_duplicate_key' => 'c2-updated',
            'c5__on_duplicate_key' => 'c5-updated',
        ];
        $actual = $this->query->getBindValues();
        $this->assertSame($expect, $actual);
    }

    /**
     *
     * A bulk insert renames each row's placeholders and banks them, then
     * clears the working values ready for the next row. The ON DUPLICATE KEY
     * UPDATE value is not one of those row values, and the statement goes on
     * spelling its placeholder, so it has to survive that clearing: an
     * unbound placeholder makes execute() fail with HY093.
     *
     */
    public function testBulkInsertKeepsTheOnDuplicateKeyUpdateBind()
    {
        $this->query->into('t1')
                    ->cols(['c1' => 'v1-0'])
                    ->addRow(['c1' => 'v1-1'])
                    ->onDuplicateKeyUpdateCol('c1', 'c1-updated');

        $actual = $this->query->__toString();
        $expect = '
            INSERT INTO <<t1>>
                (<<c1>>)
            VALUES
                (:c1_0),
                (:c1_1) ON DUPLICATE KEY UPDATE
                <<c1>> = :c1__on_duplicate_key
        ';
        $this->assertSameSql($expect, $actual);

        $expect = [
            'c1__on_duplicate_key' => 'c1-updated',
            'c1_0' => 'v1-0',
            'c1_1' => 'v1-1',
        ];
        $this->assertSame($expect, $this->query->getBindValues());
    }

    public function testOnConflictNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support ON CONFLICT clause");
        $this->query->onConflict('id');
    }

    public function testOrReplaceWithOnDuplicateKeyUpdateThrowsException()
    {
        $this->query->orReplace()
                    ->into('t1')
                    ->cols(['c1'])
                    ->onDuplicateKeyUpdate('c1', 'new-val');

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage('A REPLACE statement cannot take an ON DUPLICATE KEY UPDATE clause.');
        $this->query->__toString();
    }
}
