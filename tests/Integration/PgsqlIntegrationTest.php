<?php
namespace Aura\SqlQuery\Integration;

use PDO;

/**
 *
 * Runs against a real PostgreSQL server. Set DB_PGSQL_DSN (plus DB_PGSQL_USER
 * and DB_PGSQL_PASS as needed) to enable; the test is skipped when they are
 * absent.
 *
 */
class PgsqlIntegrationTest extends AbstractIntegrationTest
{
    protected string $db_type = 'pgsql';

    protected function newPdo(): PDO
    {
        $dsn = getenv('DB_PGSQL_DSN');
        if ($dsn === false || $dsn === '') {
            $this->markTestSkipped('DB_PGSQL_DSN is not set.');
        }

        $user = getenv('DB_PGSQL_USER') ?: null;
        $pass = getenv('DB_PGSQL_PASS') ?: null;

        return new PDO($dsn, $user, $pass);
    }

    protected function getCreateTables(): array
    {
        return [
            'CREATE TABLE test_dept (
                id   INTEGER PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_employee (
                id      SERIAL PRIMARY KEY,
                name    VARCHAR(50) NOT NULL,
                dept_id INTEGER NULL,
                salary  INTEGER NOT NULL,
                seq     INTEGER NOT NULL
            )',
            "CREATE TABLE test_defaults (
                id   SERIAL PRIMARY KEY,
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
        return "CAST({$expr} AS VARCHAR)";
    }

    protected function inCsv(string $col, string $param): string
    {
        // PostgreSQL has no find_in_set()
        return "{$col} = ANY(string_to_array({$param}, ','))";
    }

    public function testInsertReturning()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols(['name' => 'Edna', 'dept_id' => 1, 'salary' => 500, 'seq' => 5])
            ->returning(['id', 'name']);

        $this->assertStatementContains('RETURNING', $insert);

        $row = $this->fetchAll($insert)[0];
        $this->assertSame('Edna', $row['name']);
        $this->assertGreaterThan(0, (int) $row['id']);
    }

    public function testInsertIgnore()
    {
        // Postgres spells the IGNORE flag ON CONFLICT DO NOTHING; the row
        // already seeded under id 1 must survive untouched
        $insert = $this->query_factory->newInsert()
            ->ignore()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Duplicate']);

        $this->assertStatementContains('ON CONFLICT DO NOTHING', $insert);

        $this->assertSame(0, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    public function testUpdateReturning()
    {
        $update = $this->query_factory->newUpdate()
            ->table('test_employee')
            ->cols(['salary' => 999])
            ->where('seq = :seq', ['seq' => 1])
            ->returning(['name', 'salary']);

        $this->assertStatementContains('RETURNING', $update);

        $rows = $this->fetchAll($update);
        $this->assertSame('Anna', $rows[0]['name']);
        $this->assertSame(999, (int) $rows[0]['salary']);
    }

    public function testDeleteReturning()
    {
        $delete = $this->query_factory->newDelete()
            ->from('test_employee')
            ->where('salary >= :min', ['min' => 300])
            ->returning(['name']);

        $this->assertStatementContains('RETURNING', $delete);

        $rows = $this->fetchAll($delete);
        $names = array_column($rows, 'name');
        sort($names);
        $this->assertSame(['Clara', 'Donna'], $names);
    }

    public function testLateralJoinSubSelect()
    {
        // the top earner in each department
        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'left',
                'SELECT name, salary FROM test_employee
                 WHERE test_employee.dept_id = test_dept.id
                 ORDER BY salary DESC LIMIT 1',
                'top',
                'true'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('LEFT JOIN LATERAL', $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(
            [
                ['dept_name' => 'Engineering', 'employee_name' => 'Betty'],
                ['dept_name' => 'Sales', 'employee_name' => 'Donna'],
            ],
            array_map(
                fn ($row) => [
                    'dept_name' => $row['dept_name'],
                    'employee_name' => $row['employee_name'],
                ],
                $rows
            )
        );
    }

    /**
     * A LATERAL join with no condition has to emit "ON true" or Postgres
     * rejects the statement outright.
     */
    public function testLateralJoinSubSelect_noCondition()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'left',
                'SELECT name FROM test_employee
                 WHERE test_employee.dept_id = test_dept.id
                 ORDER BY salary DESC LIMIT 1',
                'top'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('ON true', $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(
            ['Betty', 'Donna'],
            array_column($rows, 'employee_name')
        );
    }

    public function testLateralJoinSubSelect_cross()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'cross',
                'SELECT name FROM test_employee
                 WHERE test_employee.dept_id = test_dept.id
                 ORDER BY salary DESC LIMIT 1',
                'top'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('CROSS JOIN LATERAL', $select);
        $this->assertStringNotContainsString('ON true', (string) $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(
            ['Betty', 'Donna'],
            array_column($rows, 'employee_name')
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

        // id 1 already holds Engineering, so the insert turns into an update
        $this->assertSame(1, $this->exec($insert));

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

        $this->assertSame(0, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    /**
     * The conflict target may name a constraint instead of its columns.
     */
    public function testInsertOnConflictConstraintTarget()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'ByConstraint'])
            ->onConflict('ON CONSTRAINT test_dept_pkey')
            ->doUpdateCols(['name']);

        $this->assertStatementContains(
            'ON CONFLICT ON CONSTRAINT <<test_dept_pkey>> DO UPDATE SET',
            $insert
        );

        $this->assertSame(1, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('ByConstraint', $sth->fetchColumn());
    }

    /**
     * The WHERE decides whether the conflicting row is updated at all; this
     * one cannot match, so the existing row survives untouched.
     */
    public function testInsertOnConflictDoUpdateWhere()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted'])
            ->onConflict('id')
            ->doUpdateCols(['name'])
            ->doUpdateWhere('test_dept.id > :min', ['min' => 100]);

        $this->assertStatementContains('DO UPDATE SET', $insert);

        $this->assertSame(0, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    /**
     * A raw expression in doUpdate() has to qualify any column it names.
     * Inside DO UPDATE SET, a bare column is ambiguous between the target
     * table and the excluded pseudo-table, and Postgres rejects it -- where
     * SQLite accepts the same expression. See docs/pgsql.md.
     */
    public function testInsertOnConflictRawExpressionMustQualifyColumns()
    {
        // test_dept has explicit ids; test_employee's come from a SERIAL, and
        // sequences are not rolled back with the surrounding transaction, so
        // its ids differ from run to run
        $unqualified = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted'])
            ->onConflict('id')
            ->doUpdate('name', "name || '-updated'");

        // Postgres aborts the surrounding transaction on error, so the
        // failure is isolated behind a savepoint to let the test continue
        $this->pdo->exec('SAVEPOINT before_ambiguous');
        try {
            $this->exec($unqualified);
            $this->fail('Expected an ambiguous-column error.');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('ambiguous', $e->getMessage());
        }
        $this->pdo->exec('ROLLBACK TO SAVEPOINT before_ambiguous');

        // qualifying the column resolves it
        $qualified = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Attempted'])
            ->onConflict('id')
            ->doUpdate('name', "test_dept.name || '-updated'");

        $this->assertSame(1, $this->exec($qualified));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering-updated', $sth->fetchColumn());
    }

    public function testGetLastInsertIdName()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols(['name' => 'Edna', 'dept_id' => 1, 'salary' => 500, 'seq' => 5]);

        $this->assertStatementContains('INSERT INTO <<test_employee>>', $insert);

        $this->exec($insert);

        $name = $insert->getLastInsertIdName('id');
        $this->assertSame('test_employee_id_seq', $name);
        $this->assertGreaterThan(0, (int) $this->pdo->lastInsertId($name));
    }
}
