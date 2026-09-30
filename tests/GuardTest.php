<?php
namespace Aura\SqlQuery;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 *
 * Queries that cannot be built say so with the package's own exceptions,
 * and the SQL that is built is SQL the dialect accepts.
 *
 */
class GuardTest extends TestCase
{
    public static function provideDialects(): array
    {
        return [
            'common' => ['common'],
            'mysql' => ['mysql'],
            'pgsql' => ['pgsql'],
            'sqlite' => ['sqlite'],
            'sqlsrv' => ['sqlsrv'],
        ];
    }

    #[DataProvider('provideDialects')]
    public function testUpdateWithoutTable(string $db)
    {
        $update = (new QueryFactory($db))->newUpdate()->cols(['a' => 1]);

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage('No table to update.');
        $update->getStatement();
    }

    #[DataProvider('provideDialects')]
    public function testDeleteWithoutTable(string $db)
    {
        $delete = (new QueryFactory($db))->newDelete()->where('a = 1');

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage('No table to delete from.');
        $delete->getStatement();
    }

    #[DataProvider('provideDialects')]
    public function testInsertWithoutTable(string $db)
    {
        $insert = (new QueryFactory($db))->newInsert()->cols(['a' => 1]);

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage('No table to insert into.');
        $insert->getStatement();
    }

    public static function provideOffsetWithoutLimit(): array
    {
        return [
            'common' => ['common', 'OFFSET 10'],
            'pgsql' => ['pgsql', 'OFFSET 10'],
            'mysql' => ['mysql', 'LIMIT 18446744073709551615 OFFSET 10'],
            'sqlite' => ['sqlite', 'LIMIT -1 OFFSET 10'],
            'sqlsrv' => ['sqlsrv', 'OFFSET 10 ROWS'],
        ];
    }

    #[DataProvider('provideOffsetWithoutLimit')]
    public function testSelectOffsetWithoutLimit(string $db, string $expect)
    {
        $select = (new QueryFactory($db))->newSelect()
            ->cols(['*'])
            ->from('t')
            ->orderBy(['id'])
            ->offset(10);

        $statement = $select->getStatement();
        $this->assertStringEndsWith($expect, $statement);
        $this->assertStringNotContainsString('FETCH', $statement);
    }

    public function testSqliteOffsetWithoutLimitRuns()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1), (2), (3)');

        $factory = new QueryFactory('sqlite');

        $select = $factory->newSelect()->cols(['id'])->from('t')->orderBy(['id'])->offset(1);
        $this->assertSame([2, 3], array_map('intval', $pdo->query($select->getStatement())->fetchAll(PDO::FETCH_COLUMN)));

        $update = $factory->newUpdate()->table('t')->set('id', 'id + 10')->orderBy(['id'])->offset(2);
        $this->assertStringEndsWith('LIMIT -1 OFFSET 2', $update->getStatement());

        $delete = $factory->newDelete()->from('t')->orderBy(['id'])->offset(2);
        $this->assertStringEndsWith('LIMIT -1 OFFSET 2', $delete->getStatement());
    }

    public static function provideFlagOrder(): array
    {
        return [
            'insert ignore then low priority' => ['newInsert', ['ignore', 'lowPriority'], 'INSERT LOW_PRIORITY IGNORE'],
            'insert ignore then high priority' => ['newInsert', ['ignore', 'highPriority'], 'INSERT HIGH_PRIORITY IGNORE'],
            'update ignore then low priority' => ['newUpdate', ['ignore', 'lowPriority'], 'UPDATE LOW_PRIORITY IGNORE'],
        ];
    }

    #[DataProvider('provideFlagOrder')]
    public function testMysqlFlagsAreWrittenInGrammarOrder(string $factory, array $calls, string $expect)
    {
        $query = (new QueryFactory('mysql'))->$factory();
        foreach ($calls as $call) {
            $query->$call();
        }

        if ($factory === 'newInsert') {
            $query->into('t')->cols(['a' => 1]);
        } else {
            $query->table('t')->cols(['a' => 1]);
        }

        $this->assertStringStartsWith($expect, $query->getStatement());
    }

    public static function provideJoinsForbiddingCondition(): array
    {
        return [
            'natural, common' => ['common', 'NATURAL'],
            'natural left, sqlite' => ['sqlite', 'NATURAL LEFT'],
            'natural, mysql' => ['mysql', 'NATURAL'],
            'cross, pgsql' => ['pgsql', 'CROSS'],
            'cross, sqlsrv' => ['sqlsrv', 'CROSS'],
        ];
    }

    #[DataProvider('provideJoinsForbiddingCondition')]
    public function testJoinForbiddingConditionRefusesOne(string $db, string $join)
    {
        $select = (new QueryFactory($db))->newSelect()->cols(['*'])->from('t');

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage("A $join JOIN cannot take a condition.");
        $select->join($join, 'u', 't.id = u.id');
    }

    public function testJoinSubSelectForbiddingConditionRefusesOne()
    {
        $factory = new QueryFactory('pgsql');
        $select = $factory->newSelect()->cols(['*'])->from('t');

        $this->expectException(Exception\LogicException::class);
        $select->joinSubSelect('natural', 'SELECT id FROM u', 'u', 't.id = u.id');
    }

    public function testMysqlCrossJoinTakesACondition()
    {
        $select = (new QueryFactory('mysql'))->newSelect()
            ->cols(['*'])
            ->from('t')
            ->join('CROSS', 'u', 't.id = u.id');

        $this->assertStringContainsString('CROSS JOIN `u` ON `t`.`id` = `u`.`id`', $select->getStatement());
    }

    public function testNaturalJoinWithoutCondition()
    {
        $select = (new QueryFactory('sqlite'))->newSelect()->cols(['*'])->from('t')->join('natural', 'u');
        $this->assertStringContainsString('NATURAL JOIN "u"', $select->getStatement());
    }

    public function testUnknownDialect()
    {
        $factory = new QueryFactory('oracle');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown database type 'oracle'");
        $factory->newSelect();
    }

    public function testDistinctInColumnIsNotAnAlias()
    {
        $select = (new QueryFactory('sqlite'))->newSelect()->cols(['DISTINCT a'])->from('t');
        $this->assertStringContainsString('DISTINCT a', $select->getStatement());
        $this->assertStringNotContainsString('AS', $select->getStatement());
    }

    public function testNullOrEmptyAliasMeansNoAlias()
    {
        $select = (new QueryFactory('sqlite'))->newSelect()->cols(['a' => null, 'b' => ''])->from('t');
        $this->assertStringNotContainsString('AS', $select->getStatement());
        $this->assertSame(['a', 'b'], array_values($select->getCols()));
    }

    public function testNonStringAliasThrows()
    {
        $select = (new QueryFactory('sqlite'))->newSelect();

        $this->expectException(Exception\InvalidArgumentException::class);
        $select->cols(['a' => ['alias']]);
    }

    public function testNonStringColumnThrows()
    {
        $select = (new QueryFactory('sqlite'))->newSelect();

        $this->expectException(Exception\InvalidArgumentException::class);
        $select->cols([5]);
    }

    public function testQualifiedColumnGetsAValidPlaceholder()
    {
        $update = (new QueryFactory('mysql'))->newUpdate()->table('t')->cols(['t.a' => 1]);
        $this->assertStringContainsString('`t`.`a` = :t_a', $update->getStatement());
        $this->assertSame(['t_a' => 1], $update->getBindValues());

        $insert = (new QueryFactory('mysql'))->newInsert()->into('t')->cols(['a' => 1])
            ->onDuplicateKeyUpdateCol('t.a', 2);
        $this->assertStringContainsString(':t_a__on_duplicate_key', $insert->getStatement());

        $upsert = (new QueryFactory('sqlite'))->newInsert()->into('t')->cols(['a' => 1])
            ->onConflict('a')->doUpdateCol('x.b', 2);
        $this->assertArrayHasKey('x_b__on_conflict', $upsert->getBindValues());
    }

    public static function provideConflictTargets(): array
    {
        return [
            'comma list' => ['a, b', 'ON CONFLICT ("a", "b")'],
            'comma list, no spaces' => ['a,b', 'ON CONFLICT ("a", "b")'],
            'constraint, extra spaces' => ['ON  CONSTRAINT  t_pkey', 'ON CONFLICT ON CONSTRAINT "t_pkey"'],
            'constraint, lower case' => ['on constraint t_pkey', 'ON CONFLICT ON CONSTRAINT "t_pkey"'],
        ];
    }

    #[DataProvider('provideConflictTargets')]
    public function testConflictTargetString(string $target, string $expect)
    {
        $insert = (new QueryFactory('pgsql'))->newInsert()
            ->into('t')
            ->cols(['a' => 1, 'b' => 2])
            ->onConflict($target)
            ->doUpdateCol('b');

        $this->assertStringContainsString($expect, $insert->getStatement());
    }

    public function testConflictTargetExpressionThrows()
    {
        $insert = (new QueryFactory('pgsql'))->newInsert()->into('t');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage("not the expression 'lower(email)'");
        $insert->onConflict('lower(email)');
    }

    public function testSqliteConflictTargetListRuns()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE t (a INTEGER, b INTEGER, c TEXT, UNIQUE (a, b))');
        $pdo->exec("INSERT INTO t VALUES (1, 2, 'old')");

        $insert = (new QueryFactory('sqlite'))->newInsert()
            ->into('t')
            ->cols(['a' => 1, 'b' => 2, 'c' => 'new'])
            ->onConflict('a, b')
            ->doUpdateCol('c');

        $pdo->prepare($insert->getStatement())->execute($insert->getBindValues());
        $this->assertSame('new', $pdo->query('SELECT c FROM t')->fetchColumn());
    }
}
