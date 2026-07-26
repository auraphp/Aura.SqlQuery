<?php
namespace Aura\SqlQuery\Integration;

use PDO;

class SqliteIntegrationTest extends AbstractIntegrationTest
{
    protected string $db_type = 'sqlite';

    protected function newPdo(): PDO
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }
        return new PDO('sqlite::memory:');
    }

    protected function getCreateTables(): array
    {
        return [
            'CREATE TABLE test_dept (
                id   INTEGER PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_employee (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                name    VARCHAR(50) NOT NULL,
                dept_id INTEGER NULL,
                salary  INTEGER NOT NULL,
                seq     INTEGER NOT NULL
            )',
            "CREATE TABLE test_defaults (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL DEFAULT 'anon'
            )",
            'CREATE TABLE test_isolate (
                id               INTEGER PRIMARY KEY,
                "species.genus"  VARCHAR(50) NOT NULL,
                "species.name"   VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_compound (
                id                INTEGER PRIMARY KEY,
                isolate_id        INTEGER NOT NULL,
                "compound.group"  VARCHAR(50) NOT NULL,
                "compound.name"   VARCHAR(50) NOT NULL
            )',
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS TEXT)";
    }

    protected function inCsv(string $col, string $param): string
    {
        // SQLite has no find_in_set()
        return "instr(',' || {$param} || ',', ',' || {$col} || ',') > 0";
    }

    public function testInsertIgnore()
    {
        $insert = $this->query_factory->newInsert()
            ->ignore()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Duplicate']);

        $this->assertStatementContains('INSERT OR IGNORE INTO <<test_dept>>', $insert);

        $this->assertSame(0, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    public function testUpdateIgnore()
    {
        // moving Sales onto the primary key Engineering already holds. The
        // condition cannot reuse :id -- cols() already binds that name for
        // the SET clause, and the second binding would win.
        $update = $this->query_factory->newUpdate()
            ->table('test_dept')
            ->cols(['id' => 1])
            ->where('id = :old_id', ['old_id' => 2]);

        // without the flag the collision is an error
        try {
            $this->exec($update);
            $this->fail('Expected a constraint violation.');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
        }

        // with it, the offending row is skipped instead
        $update->ignore();
        $this->assertStatementContains('UPDATE OR IGNORE <<test_dept>>', $update);
        $this->assertSame(0, $this->exec($update));

        $sth = $this->pdo->query('SELECT id, name FROM test_dept ORDER BY id');
        $this->assertSame(
            [['id' => 1, 'name' => 'Engineering'], ['id' => 2, 'name' => 'Sales']],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testInsertOrReplace()
    {
        $insert = $this->query_factory->newInsert()
            ->orReplace()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Replaced']);

        $this->assertStatementContains('INSERT OR REPLACE INTO <<test_dept>>', $insert);

        $this->exec($insert);

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Replaced', $sth->fetchColumn());
    }

    /**
     * An UPDATE carries a conflict clause too. Moving Sales onto the key
     * Engineering holds would violate the primary key; OR REPLACE resolves
     * it by dropping the row that was in the way.
     */
    public function testUpdateOrReplace()
    {
        // the condition cannot reuse :id -- cols() already binds that name
        // for the SET clause, and the second binding would win
        $update = $this->query_factory->newUpdate()
            ->orReplace()
            ->table('test_dept')
            ->cols(['id' => 1])
            ->where('id = :old_id', ['old_id' => 2]);

        $this->assertStatementContains('UPDATE OR REPLACE <<test_dept>>', $update);

        $this->assertSame(1, $this->exec($update));

        $sth = $this->pdo->query('SELECT id, name FROM test_dept ORDER BY id');
        $this->assertSame(
            [['id' => 1, 'name' => 'Sales']],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function testInsertOnConflictUpdate()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted'])
            ->onConflict('id')
            ->doUpdateCols(['name']);

        $this->assertStatementContains('ON CONFLICT ("id") DO UPDATE SET', $insert);

        // id 1 already exists (Engineering). It should be updated to 'Attempted'.
        $this->exec($insert);

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Attempted', $sth->fetchColumn());
    }

    public function testInsertOnConflictDoNothing()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted'])
            ->onConflict('id')
            ->ignore();

        $this->assertStatementContains('ON CONFLICT ("id") DO NOTHING', $insert);

        // id 1 already exists. It should NOT be updated.
        $this->exec($insert);

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    /**
     * A bound value and a raw expression alongside the proposed one, all in
     * the same DO UPDATE SET.
     */
    public function testInsertOnConflictDoUpdateColAndExpression()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols(['id' => 1, 'name' => 'Anna', 'dept_id' => 1, 'salary' => 999, 'seq' => 1])
            ->onConflict('id')
            ->doUpdateCols(['salary'])
            ->doUpdateCol('name', 'Renamed')
            ->doUpdate('seq', 'test_employee.seq + 10');

        $this->assertStatementContains('ON CONFLICT ("id") DO UPDATE SET', $insert);

        $this->assertSame(1, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name, salary, seq FROM test_employee WHERE id = 1');
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Renamed', $row['name']);
        $this->assertSame(999, (int) $row['salary']);
        $this->assertSame(11, (int) $row['seq']);
    }

    /**
     * The WHERE decides whether the conflicting row is updated at all; this
     * one cannot match, so the existing row survives.
     */
    public function testInsertOnConflictDoUpdateWhere()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted'])
            ->onConflict('id')
            ->doUpdateCols(['name'])
            ->doUpdateWhere('test_dept.id > :min', ['min' => 100]);

        $this->assertSame(0, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    /**
     * SQLite takes only a column list as the conflict target. The
     * constraint-name form is Postgres-only and is a syntax error here, so
     * the builder refuses it rather than letting it reach the database.
     */
    public function testInsertOnConflictConstraintTargetNotSupported()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted']);

        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $insert->onConflict('ON CONSTRAINT test_dept_pkey');
    }

    /**
     * The equivalent column-list target, which SQLite does accept, proving
     * the refusal above is about the syntax and not the intent.
     */
    public function testInsertOnConflictMultiColumnTarget()
    {
        $this->pdo->exec(
            'CREATE UNIQUE INDEX test_employee_dept_seq
                ON test_employee (dept_id, seq)'
        );

        // Anna is seeded as dept_id 1, seq 1
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols(['name' => 'Replacement', 'dept_id' => 1, 'salary' => 555, 'seq' => 1])
            ->onConflict(['dept_id', 'seq'])
            ->doUpdateCols(['name', 'salary']);

        $this->assertStatementContains(
            'ON CONFLICT (<<dept_id>>, <<seq>>) DO UPDATE SET',
            $insert
        );

        $this->assertSame(1, $this->exec($insert));

        $sth = $this->pdo->query(
            'SELECT name, salary FROM test_employee WHERE dept_id = 1 AND seq = 1'
        );
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Replacement', $row['name']);
        $this->assertSame(555, (int) $row['salary']);
    }
}
